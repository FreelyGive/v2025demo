# Drupal Canvas's Redux-integrated field widgets

In the rest of this document, `Drupal Canvas` will be written as `Canvas`.

## Finding issues 🐛, code 🤖 & people 👯‍♀️
Related Canvas issue queue components:
1. [Redux-integrated field widgets](https://www.drupal.org/project/issues/canvas?component=Redux-integrated+field+widgets)
2. [Semi-Coupled theme engine](https://www.drupal.org/project/issues/canvas?component=Semi-Coupled+theme+engine)
3. [Shape matching](https://www.drupal.org/project/issues/canvas?component=Shape+matching)

## 1. Terminology

### 1.1 Existing Drupal terminology that is crucial for Canvas

- `field widget`: a class that uses Form API to specify the editing UX for a `field type`.

### 1.2 Existing non-Drupal terminology that is crucial for Canvas

- [`HTML form control element`](https://developer.mozilla.org/en-US/docs/Web/API/HTMLFormControlsCollection)
- [`Redux`](https://redux.js.org): A JS library that manages state data in a centralized manner.

### 1.3 Canvas terminology

This uses _most_ of the Canvas terminology in the:
- [`Canvas Components` doc](components.md)
- [`Canvas Shape Matching into Field Types` doc](shape-matching-into-field-types.md)
- [`Canvas Semi-Coupled theme engine` doc](semi-coupled-theme-engine.md)

## 2. Product requirements

This uses the terms defined above.

(There are [more](https://docs.google.com/spreadsheets/d/1OpETAzprh6DWjpTsZG55LWgldWV_D8jNe9AM73jNaZo/edit?gid=1721130122#gid=1721130122), but these in particular affect Canvas's supported components.)

- MUST support a real-time preview;
- MUST allow continuing to use existing Drupal functionality (notably: `field widget`s).


## 3. Implementation

See:
- `ui/src/components/form/inputBehaviors.tsx`
- `ui/src/components/form/Form.tsx`

Integrating `Redux` with the `component input`s form (and likely other forms in the future) allows us to integrate Drupal Form API-generated forms into the larger Canvas application. The reasons for this include:
- Canvas's undo functionality is fully aware of changes made in these forms;
- Changes made in these forms can trigger real-time updates of the UI, including the preview.


### 3.1 Why?

If you have not yet read [`Canvas Shape Matching into Field Types` doc](shape-matching-into-field-types.md), and
specifically section 3.1.2 ("Finding fitting `field type`: `conjured field`s and `field instance`s") because the data
model parts are less relevant/interesting, that is fine. Here's a summary that explains why this document/infrastructure
is needed:
1. Canvas is about composing components (into a `component tree`) to craft an experience
2. a `component` has `component input`s, which must be populated to render the `component`
3. Canvas aims to use Drupal's existing `field type` functionality to store values for those `component input`s
4. for Canvas to choose a `field type` that is appropriate for a particular `component input`, Canvas analyzes its `prop shape`
5. each `field type` has one or more `field widget`s, which Canvas must use to be able to actually use that `field type`
6. a `field widget` uses one or more `HTML form control element`s (and may or may not use AJAX)
7. this document explains what the infrastructure is that is needed to make `field widget`s allow for real-time updates
   of the preview of the `component tree`, i.e. without having to rely on `field widget`s historical reliance on
   submitting HTML forms to the server (which incurs significant latency, resulting in inferior UX)

**The goal: getting all this data flowing exactly as intended all the way from each `HTML form control element` via all
intermediary concepts (`field widget`, `field type`, `field prop`, etc.) into a `component input`, to achieve real-time
 previews.**

### 3.2 How?
The `Semi-Coupled theme engine` makes it possible to process Drupal `render element`s with `React component`s  instead of `Twig`. See the [`Canvas Semi-Coupled theme engine` doc](semi-coupled-theme-engine.md) for more details.

To redux-sync a `React`-rendered `HTML form control element`, it should be wrapped by `inputBehaviors`:

```javascript
import inputBehaviors from './inputBehaviors';

const Input = (props) => {
  // Your component...
};

// Wrap the export in inputBehaviors and it will be integrated with Experience
// Builder's Redux store.
export default inputBehaviors(Input);
```

`inputBehaviors` automatically takes care of several things:
- Value changes update the `Redux` store. This means it can update the `component tree` preview in real time and it is tracked by Canvas's undo history.
- Client-side validation based on the [JSON Schema definition](https://json-schema.org/understanding-json-schema) of each `component input`.
- Value changes are also written to the [formState slice](../ui/src/features/form/formStateSlice.ts)
- Applies 'transforms' (see [3.4 Transforms](#34-transforms)) in order to map the data from the form structure to the expected value

*Note:* The presence of the `data-canvas-no-update` attribute on an input will stop it
from updating the [formState slice](../ui/src/features/form/formStateSlice.ts) / applying
transforms / updating the preview. This can be useful for scenarios such as autocomplete inputs, where there's little benefit in updating the store & preview with partial values and this attribute
is present while composing, but removed when the final value is present.

#### 3.2.1 Source vs resolved input

Some components have a different representations for the source and resolved form of their input. For example, an Image SDC
might have an `image` property with a `prop source` that is stored as a reference to a media entity
but via a `prop expression` this might evaluate to an object with keys `src`, `alt`, `width` and `height`.
The client-side representation of the data model keeps track of both the source and resolved values.
In the case of `SDC` and `JS` `components`, the source will take the form of an array representation
of the `prop source`. e.g. for a "text" `field item` this might be

```
"text": {
  "sourceType": "static:field_item:string",
  "expression": "..."
},
```

And the resolved would be the value in the field

```
"text": "hello, world!",
```

Ordinarily a `static prop source` needs a value key in order to be evaluated. In cases where the resolved value duplicates
the `prop source`'s value for the "value" key, this can be omitted. When the source is evaluated, the resolved value will be merged with the other
source values.

There are some `prop source`s where the expression used means the resolved value is entirely different to that of the source value, such as the media example above.

In that case, the source values should include the actual value.

```
"image": {
  "sourceType": "static:field_item:entity_reference",
  "value": {
    "target_id": 3
  },
  "expression": "...",
  "sourceTypeSettings": {
    "storage": {
      "target_type": "media"
    },
    "instance": {
      "handler": "default:media",
      "handler_settings": {
        "target_bundles": {
          "image": "image"
        }
      }
    }
  }
}
```

which when evaluated resolves to

```
"image": {
  "src": "public://2024-07/framer.png",
  "alt": "asd",
  "width": 518,
  "height": 1118
}
```

### 3.3 The spectrum of `HTML form control element` → `component input` flows

A `component input` has a `prop shape`, resulting in >=1 `field prop`s being used, which needs 1 `field widget` that has
multiple `HTML form control element`s.

6 axes have been identified, each with their own spectrum, that cause (necessary) complexity this infrastructure has to
tame, because each combination across all 6 axes must work correctly:
1. **Number of `field prop`s:** non-list `prop shape`s: the shape of the value needed by a `component input` ranges between:
  - simple value like a number, needs single `field prop` from 1 `field item`
  - key-value pairs ("objects" in JSON Schema), needs multiple `field prop`s from 1 `field item`
2. **List `prop shape`s ("array" in JSON schema):** the same as above, but 2 or more times, in a `field item list` ("multiple cardinality")
3. **Computed `field prop`s:** a `field item` that has >=1 _computed_ `field prop`s, _if_ needed by the `component input`.
   For example: `\Drupal\text\Plugin\Field\FieldType\TextLongItem` has a `processed` computed `field prop`.
4. **HTML form structure of a `field widget`:** a `field widget`'s use of `HTML form control element`s for a single
   `field item`. This is solved using the [3.4 Transforms](#34-transforms) infrastructure.
  - 1:1: one `HTML form control element` to one `field prop`.
    For example: `\Drupal\Core\Field\Plugin\Field\FieldWidget\NumberWidget::formElement()`.
  - N:1: multiple `HTML form control element` to one `field prop`.
    For example: `\Drupal\datetime\Plugin\Field\FieldWidget\DateTimeDefaultWidget::formElement()` has separate "date"
    and "time" `HTML form control element`s.
  - 1:N: one `HTML form control element` to multiple `field prop`s.
    For example: `\Drupal\image\Plugin\Field\FieldWidget\ImageWidget::formElement()` uses `<input type="file">` and
    populates the following `field prop`s: `target_id`, `width` and `height`.
5. **AJAX**: a `field widget`'s reliance on AJAX or not. (Often for _computed_ `field prop`s, but not always.)
   For example: `\Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget::formElement()`.
6. **Multi-value `field widget`s**: some `field widget`s natively support multiple values, without the need
   repeating the form structure multiple times.
   For example: `\Drupal\media_library\Plugin\Field\FieldWidget\MediaLibraryWidget` has
   ```
   multiple_values: true
   ```

Supported:

| ?   | Field props | List | Computed | HTML | AJAX | Multi |                 Example | Infrastructure | Issue to add support                                                         |
|-----|:------------|:----:|:--------:|:----:|:----:|:-----:|------------------------:|----------------|:-----------------------------------------------------------------------------|
| 🟢️ | single      |  N   |    N     | 1:1  |  N   |   N   |          `NumberWidget` | \              | \                                                                            |
| 🟢️ | single      |  N   |    N     | N:1  |  N   |   N   | `DateTimeDefaultWidget` | Transforms     | \                                                                            |
| 🔴 | multiple    |  N   |    N     | 1:1  |  N   |   N   |           `ImageWidget` |                | [#3499550](https://www.drupal.org/project/canvas/issues/3499550) |
| ∅  | multiple    |  N   |    N     | N:1  |  N   |   N   |         Does not exist? |                | \                                                                            |
| 🔴 | multiple    |  Y   |    N     | 1:1  |  N   |   N   |                         |                | [#3467870](https://www.drupal.org/project/canvas/issues/3467870) |
| 🔴 | multiple    |  Y   |    N     | 1:1  |  N   |   N   |                         |                | [#3467870](https://www.drupal.org/project/canvas/issues/3467870) |

Legend:
- 🟢 = supported
- 🟡 = limited support, currently uses hacks
- 🔴 = not supported yet.

_⚠️The table is incomplete: many permutations are missing!_

How to interpret the above table?
- If a given `component input` has a simple, single value `prop shape` — such as a string, number, or boolean, the relationship to its corresponding `HTML form control element` is straightforward: a "first name" prop that stores a string is easily synchronized with a "first name" `<input type="text">` where that value can be changed. These types of props already work well with Canvas.
- Most other things require research for how to achieve them. In [#3487284](https://www.drupal.org/project/canvas/issues/3487284) we will discover the different cases and try to find a way forward.

Ideally the solutions for the above would be ones where the custom per-field-type logic largely occurs server side. This means the field-specific logic is being added in the same place the field is defined. It also reduces the chances of a majority-PHP contrib module having to write custom JS to work with Canvas.

The server can still provide data that dictates front-end functionality. It's something we're already doing in the props form by performing client-side validation based on server-provided JSON Schemas.

One possible direction for a generic solution: <https://git.drupalcode.org/issue/canvas-3463842/-/compare/0.x...3463842-outline-of-possible-generic-solution>.

### 3.4 Transforms

[Transforms](../ui/src/utils/transforms.ts) are applied on a per-widget basis. Transforms are named functions and may or may not take optional configuration.
For each property, the component list may provide a list of transforms and their respective configuration.
When a property is edited in the UI, the transforms are applied in sequence in order to take the value input by the user and the HTML form structure and transform it to the expected SDC prop value.
As transforms are specific to each widget, on the Drupal side we implement `hook_field_widget_info_alter()` and add additional metadata to each widget's plugin definition.
See [`canvas_field_widget_info_alter`](../canvas.redux_integrated_field_widgets.inc) for an example.
If your module provides a custom widget, you should implement this hook and add the transforms required in a similar fashion.

Built-in transforms include:
- `mainProperty` - which takes configuration of the `name` and an optional `list` boolean
- `firstRecord` - which will return all child values for the first record in a list
- `mediaSelection` - which will return 'selection' from input form values
- `dateTime` - which will combine child `date` and `time` fields into a valid ISO-8601 datetime string

ℹ️ The completeness of this is tested by `\Drupal\Tests\canvas\Kernel\EcosystemSupport\FieldWidgetSupportTest`.

The transforms that apply to each prop are attached to the [ComponentInstanceForm](../src/Form/ComponentInstanceForm.php) by
the [Generated UX source base plugin](../src/Plugin/Canvas/ComponentSource/GeneratedFieldExplicitInputUxComponentSourceBase.php)
which is used by both SDC and Code (JavaScript) components.

* Example *
Let's say you have a widget plugin with ID 'trousers' for a field named `zipper`. When the form is built, the widget's form
element ends up with the following HTML input:

```html
<input name="zipper[0][lizard]" type="text" />
```

In the Redux [form state slice](../ui/src/features/form/formStateSlice.ts) the value will be saved as follows:

```json
{
   "zipper[0][lizard]": "the user entered value"
}
```
In order to transform this into a prop with the value `"the user entered value"` you would apply the following transform

```php
function hook_field_widget_info_alter(array &$info): void {
  $info['trousers']['canvas']['transforms'] = [
    'mainProperty' => [
      'name' => 'lizard',
    ]
  ];
}
```

If however, your input does not contain a list like so:

```json
{
   "zipper[lizard]": "the user entered value"
}
```

you could pass `false` for the `list` option to this transform

```php
function hook_field_widget_info_alter(array &$info): void {
  $info['trousers']['canvas']['transforms'] = [
    'mainProperty' => [
      'name' => 'lizard',
      'list' => FALSE,
    ]
  ];
}
```

If your HTML input is much flatter, like that from the `options_select` widget, then you do not need to provide a transform at all.

```html
<input name="zipper" type="text" />
```

#### 3.4.1 Defining your own transform

If the built-in transforms are not suitable for your widget, you can define your own using a JavaScript file in your module.

Create a new JS file and define your transform and then add it to the global `Drupal.canvasTransforms`:

```javascript
// my_module/js/noodles-or-pasta-transform.js
((Drupal) => {
  const noodlesOrPastaTransform = (value, options, propSource) => {
    // Options are as defined in widget plugin definitions (see below).
    const { useNoodles = false } = options;
    if (useNoodles && 'noodles' in value) {
      return value.noodles;
    }
    // Field storage settings mapped from the prop shape are also available, so
    // if the HTML structure depends on instance or storage settings for the
    // field type, you can return a different value. For an example of this see
    // the dateTime transform in transforms.ts in the canvas
    // codebase.
    if (propSource.sourceTypeSettings.storage.type === 'spaghetti') {
      return 'spaghetti';
    }
    if ('pasta' in value) {
      return value.pasta;
    }
    return null;
  }
  Drupal.canvasTransforms.noodlesOrPasta = noodlesOrPastaTransform;
})(Drupal)
```

Then create a `*.libraries.yml` file in your module and declare this asset library.

Make sure the library name uses `canvas.transform` in its prefix.

```yml
# my_module.libraries.yml
canvas.transform.noodlesOrPasta:
  js:
    js/noodles-or-pasta-transform.js: {  }
  dependencies:
    # Depends on global Drupal object
    - core/drupal
    # And needs to be loaded after the canvas-ui behavior
    - canvas/canvas-ui
```

Drupal Canvas will automatically attach any libraries that use the `canvas.transform` prefix to the page builder UI.
- see [canvas_library_info_alter](../canvas.redux_integrated_field_widgets.inc) and [\Drupal\canvas\Controller\CanvasController](../src/Controller/CanvasController.php)

```php
function hook_field_widget_info_alter(array &$info): void {
  $info['noodles']['canvas']['transforms'] = [
    'noodlesOrPasta' => [
      // The options you define here will be passed as the options parameter to
      // your transform function.
      'noodles' => TRUE,
    ]
  ];
  $info['pasta']['canvas']['transforms'] = [
    'noodlesOrPasta' => []
  ];
}
```
### 3.5 Limitations / Tradeoffs
We've already established this system makes it possible to render Drupal render arrays with React. This makes it possible to use existing Drupal core functionality as if it were rendered by Twig. As powerful as this is, this isn't a 100% seamless solution. There are some limitations to be aware of:

#### 3.5.1 CKEditor 5 (and perhaps anything with existing support for use in React.)
CKSource maintains a  [CKEditor 5 React component](https://www.npmjs.com/package/@ckeditor/ckeditor5-react). While it is _possible_ to leverage Drupal core's Vanilla JS implementation of CKEditor 5, we have opted to use the version explicitly built to work in React. To accomplish this without considerable front end complexity, some theme-level extensibility was sacrificed. Most notably, the `text_format` _render element_ never makes it to the Drupal Canvas UI. The information necessary to render the text format `<select>` is instead passed to (and rendered by) the text area itself. 

In other words, we surrender a bit of render array purity to take advantage of some well maintained open source software specifically created for use in this context. It also eliminates the need for complex workarounds to get the core approach working with Radix.

When React optimized alternatives to core functionality are available, the tradeoffs of using them will be evaluated on a case by case basis. The CKEditor 5 example is a good one because it is a well maintained open source project that is already built to work in React. The CKEditor 5 example above may or may not be representative of how similar situations will be approached in the future. 

#### 3.5.2 Vanilla JS that causes reflows or has perceptible load times
As to be expected with React, there is a great deal of re-rendering happening, much of the time occurring invisible. At minimum, a React-controlled form element will re-render any time its value changes, and in the case of Canvas managed forms, these elements rerender when *any* element in the form has a value change.

Although it's not typically an issue, these re-renders mean that elements using Drupal Behaviors have the behaviors re-applied after each render. This additional overhead is, in most cases, imperceptible - functionality such as autocomplete simply works as expected even though it's being added multiple times.

However, if there is Vanilla JS that impacts the layout of elements within the form, (such as adding elements to the form markup) there might be flickering as the JS-altered layout reverts and is reintroduced. Similarly, if there is a Vanilla JS process that takes considerable time to initialize, there could be issues with this long process being run repeatedly. There are no current examples of this, but it's worth noting this is a potential issue.

#### 3.5.3 Auto-save and real time preview considerations
Some of the complexity introduced by Drupal Canvas based component + entity forms is not due to them being rendered in React, but instead because of the need to accommodate auto-save and real time preview updates. Reshaping of data that would occur on form submission is instead done in real time, which necessitates some extra plumbing such as the [3.4 Transforms](#34-transforms) covered earlier in this document.

One example is additional logic required for autocomplete. Although the form element is rerendering to display the autocomplete suggestions, additional logic is required to ensure the autocomplete suggestion process is not triggering a preview update until a selection is made.
