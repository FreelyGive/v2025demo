# Drupal Canvas's Semi-Coupled theme engine

In the rest of this document, `Drupal Canvas` will be written as `Canvas`.

## Finding issues 🐛, code 🤖 & people 👯‍♀️
Related Canvas issue queue components:
- [Semi-Coupled theme engine](https://www.drupal.org/project/issues/canvas?component=Semi-Coupled+theme+engine)
- [Redux-integrated field widgets](https://www.drupal.org/project/issues/canvas?component=Redux-integrated+field+widgets)

## 1. Terminology

### 1.1 Existing Drupal terminology that is crucial for Canvas

- `render array`: A PHP array used by Drupal that can be processed by Drupal's Render API to become markup.
- `render element`: A renderable item within a `render array` (also an array).
- `template`: A file that is a mixture of markup and content provided by a render array. Usually this is `Twig`, but Canvas's `Semi-Coupled theme engine` allows `React component`s to be used as well.
- `theme engine`: A Drupal extension type that allows themes to be developed in a particular `template` language.
- `theme suggestions`: Templates are determined based on their name — theme suggestions provide additional filename candidates for which template is used, allowing for broad templates such as ones that render all nodes, or more specific templates like ones that are only used for nodes of a specific content type.
- [`Twig`](https://twig.symfony.com/): A PHP-based `template` language, used by Drupal's default `theme engine`.

### 1.2 Existing non-Drupal terminology that is crucial for Canvas

- [`JSX`](https://react.dev/learn/writing-markup-with-jsx): A syntax extension to JavaScript that allows writing HTML-like markup, and hence is essentially a JS-based template language.
- [`React`](https://react.dev): A JavaScript library used to create highly interactive user interfaces.
- [`React component`](https://react.dev/learn/your-first-component): a reusable building block consisting of at least markup and potentially functionality. These are created in `js|jsx|ts|tsx` files and typically written using `JSX` syntax. In this document, it is NOT to be confused with `component` as in [`Canvas Components` doc](components.md).
- `React element` is what is returned by a `React component`: an object describing the DOM nodes that a `React component` represents. When passed as an arg to `React.render()`, the markup it represents is returned.
- [HTML `<template>` element](https://developer.mozilla.org/en-US/docs/Web/HTML/Element/template)
- [HTML `slot` attribute](https://developer.mozilla.org/en-US/docs/Web/HTML/Global_attributes/slot)

### 1.3 Canvas terminology

- `component`: see [`Canvas Components` doc](components.md)
- `Semi-Coupled theme engine`: An Canvas-provided `theme engine` that allows a mixture of `Twig` and `React component`s as `template`s.
- [`Hyperscriptify`](https://github.com/drupal-jsx/hyperscriptify): A JS library created for Canvas that converts `Semi-Coupled theme engine` markup (provided by `Twig` and `JSX` templates) into `React elements`.

## 2. Product requirements

This uses the terms defined above to explain which Canvas product requirements need the `Semi-Coupled theme engine`.

(There are [more](https://docs.google.com/spreadsheets/d/1OpETAzprh6DWjpTsZG55LWgldWV_D8jNe9AM73jNaZo/edit?gid=1721130122#gid=1721130122), but these in particular affect Canvas's supported components.)

- MUST allow continuing to use existing Drupal functionality (notably: certain forms).
- MUST have UI chrome that is visually distinct from both the Drupal theme and admin theme.
- MUST NOT have its UI change when changing the Drupal theme or admin theme.


### 3. Using a `React component` instead of a `Twig` `template` for a given `render element`.
_For details on what these steps are doing, see "Implementation" below._

- The routes for the Canvas UI's Props Edit form and Entity Edit form are automatically rendered with the `Canvas Stark` theme, which uses the `Semi-Coupled theme engine` (additional routes will likely be use the `Canvas stark` theme in the future)
- The `Semi-Coupled theme engine` makes it so a `render` array can use a pages that area a combination of `Twig` templates and `React component`s .

For a `render element` to use a `React component` instead of a `Twig` `template`:

  1. In the `/templates` directory (be it in Drupal Canvas or another module), the template __must__ be in a subdirectory named `process_as_jsx` (at any level of depth)
  2. The template naming (and discovery) is the same as if it were a Twig template, with the `.html.twig` extension. However, the contents of these templates are different....
  3. The content will not be Twig, but a JSON object (see "Specifications for the JSON object..." section below). In that sense, this is a "pseudo `Twig` `template`": its name claims it contains `Twig` syntax, but it is treated differently based on it being in a `process_as_jsx` directory.
      - ⚠️This is being improved in <https://www.drupal.org/project/canvas/issues/3480224>.
  3. Create a `React component` equivalent of the `Twig` template — see `/ui/src/components/form` for examples of this.
  4. Update the object `ui/src/components/form/twig-to-jsx-component-map.js` to map the  template to the corresponding `React component`. The property is the template name without extension prefixed by `drupal-` and the value is a reference to the React component. E.g. the `form-element.html.twig` maps to a `FormElement` `React component`.
     - `drupal-form-element': FormElement`.
      - ⚠️This is being improved in <https://www.drupal.org/project/canvas/issues/3480224>.

## 4. Implementation


### 4.1 Preparing a Drupal `render element` to be rendered by a `React component`
When the `Semi-Coupled theme engine` encounters a `template` that is inside a `process_as_jsx` directory thus meant to be rendered by `React`, the `template` contents are different and the resulting `render array` is also different.
- The `render array` contents are informed by the JSON object  the `template` (see section 4.2 for details on the JSON). The primary purpose of this JSON object is to determine which render children should be rendered as HTML within the `theme engine` and which are made available as props to the `React component`.
  - ⚠️ Ideally we would not use the `html.twig` file extension for the `template`s intended for the `Semi-coupled theme engine` as they do not contain Twig markup. There is an [issue for this](https://drupal.org/i/3480223).
  - If there are templates that should not *always* be rendered by `React`, use Drupal's existing theme suggestions system to make the template in `process_as_jsx` more specific so it targets only the needed context.
- When specific `render array` contents are set to be available to the `React component` as props, this includes "unpacking" values that might otherwise need to be accessed via a method. E.g. field values are all present in the object, and referenced entities are already loaded with their field contents similarly unpacked.

### 4.2 Specifications for the JSON object used in `templates` inside a `process_as_jsx` directory
- `props`: a potentially-multilevel object that determines what items within the render array are passed as props
to the `React component`.

**More on `props`**:
![Drupal variables to React props](./images/semi-coupled-prop-mapping.png)

This determines which parts of the `render array` are passed as props to the `React component`. The object properties should match the keys of the `render array` that should be passed as props. The values of each property should either be a string indicating the data Type that it should be sent to React as, or an object/array indicating that there are nested contents to be accessed: `propName: DataType|array|object`.

`DataType` can be one of these values:
- `string`: when the value of `array[property]` is just a string;
- `boolean`: when the value of `array[property]` is just a boolean;
- `object`: the `Semi-Coupled theme engine` will detect what type of object this is and parse accordingly. E.g. if the object has a `toArray` method, the return value of that method becomes the prop value. Classes such as `\Drupal\Core\Template\Attribute` and `\Drupal\Core\Entity\ContentEntityBase` are also detected and their values made directly available within the corresponding React component prop.
- `JSX.Element`: This is a prop that React receives as an already-rendered element. A good example are variables such as `page.breadcrumb`, `page.content`, etc. in core's `page.html.twig` where these variables/props are already rendered.
**Additional technical details**:  This is an all-but-invisible process that makes it possible to pass Elements as props to React even though regular HTML attributes cant have Elements as attribute values.  So lets say `array[property]` is markup or an object/array that can become markup via Drupal PHP rendering. It will _ultimately_ become a prop sent to the `React component`, but it is first rendered inside a `<drupal-html-fragment>` with its [`slot` attribute](https://developer.mozilla.org/en-US/docs/Web/HTML/Element/slot) set to the property name of the render array child being rendered. For example, if this was rendering the array found in `content`, the tag would be  `<drupal-html-fragment slot="content">`.
    - If the value is an array, the contents of the `<drupal-html-fragment>` will be the array as processed by `\Drupal\Core\Render\RendererInterface::render()`.
    - If the value is a string or an object that offers a `(string)` value, that becomes the contents of the `<drupal-html-fragment>`.
    - This `<drupal-html-fragment>` contents are ultimately added as a prop to the `React component`, but has already been processed by Drupal rendering. The name of the `prop` is the value of the fragment's [`slot` attribute](https://developer.mozilla.org/en-US/docs/Web/HTML/Element/slot)
    - This whole process of rendering in `<drupal-html-fragment>` and accessing them via the [`slot` attribute](https://developer.mozilla.org/en-US/docs/Web/HTML/Element/slot) is needed because HTML attributes can't accept HTML elements as attribute values, only string representations. React, however, happily accepts elements as prop values, so we render them inside fragments then use slots to access them.  This makes it more equivalent to Twig and avoids potential problems due to conversion/escaping etc.

**Currently, `props` is the only setting!**

#### Simple example

If we wanted to pass the boolean value of `$render_array['is_active']` so it is available as a prop named `isActive`. Props are defined in camel case and automatically matched to PHP's snake-cased equivalent.
```json
{
  "props": {
    "isActive": "boolean"
  }
}
```

#### Complex example

More complex shapes are possible too. If the render array includes an `items` child with one or more items that have `attributes`, `title`, `content`, and `url` array keys, we could map it like this:

```json
{
  "props": {
    "items": [
      {
        "attributes": "object",
        "title": "string",
        "content": "JSX.Element",
        "url": "string"
      }
    ]
  }
}
```
The `React component` would receive an `items` prop that is an array of objects that _can_ include `attributes`, `title`, `content`, and `url` properties. Note that the properties defined are not **required** to be in the render array — but when they are present, they are passed as props.


### 4.4 Hyperscriptify
Please note this is a *very* broad overview of a complex process.

When a `render array` is processed by the `Semi-Coupled theme engine`, it generates markup that
is not intended for human eyeballs. This markup is instead built to be processed by `hyperscriptify()`,
which converts the markup into a full `React` application. (Hyperscriptify can work with other libraries,
but in Canvas that library happens to be `React`)

The `theme engine` may return something like this:
```html
<!-- Wrapping in a template isn't mandatory, but highly recommended so the to-be-hyperscriptified
     markup is not part of the main document -->
<template data-hyperscriptify>
  <!-- A `drupal-` prefixed custom element means this will be mapped to a  React component and the attributes
     will become the components props. 👇 -->
  <drupal-form all-the-attributes>
    <!--  Render Elements without corresponding React components are rendered as Twig, such as this div
      that could have come from container.html.twig. 👇 -->
    <div class="field--type-string" additional-attributes>
      <!-- The parent element was the Twig-provided <div>, but this can have children that
            will be rendered by React.👇 -->
      <drupal-input additional-attributes>
      </drupal-input>
    </div>
  </drupal-form>
  <!-- Anything specified as JSX.Element in the template JSON (see 4.3) is wrapped in a drupal-html-fragment
      and becomes available as a prop to the parent component. The prop name matches the slot name. This
       fragment wrapping is what makes it possible for the prop to be an actual HTML element and not a
       string representation of it. -->
  <drupal-html-fragment slot="heading">
    <h1>THIS IS A FORM</h1>
    <drupal-link additional-attributes></drupal-link>
  </drupal-html-fragment>
</template>
```

That markup can be sent to `hyperscriptify()` which returns a full tree of [`React.createElement()` elements](https://react.dev/reference/react/createElement) ready for humans.

```javascript
const twigToJSXComponentMap = {
  'drupal-form': Form, // Form is a React component.
  'drupal-input': Input, // Input is a React component.
  'drupal-link': Link, // Link is a React component.
};

hyperscriptify(
  document.querySelector('template[data-hyperscriptify]').content,
  React.createElement,
  React.Fragment,
  twigToJSXComponentMap,
  { propsify },
)
```

## Using `<canvas-something>` elements in Twig templates
This is not technically part of the "engine" part of the  `Semi-Coupled theme engine`, but is syntax that can be used within Twig templates to indicate it should be rendered by a React component. For example:
- You can use the `<canvas-text>` element in a Twig template.
- That element is then mapped in `ui/src/components/form/twig-to-jsx-component-map.js` to the `CanvasText` React component. 
- The attributes from `<canvas-text>` are passed as props to the `CanvasText` component.

This approach makes it possible to render text with the React-defined theming without having to fully override a template to be processed by the `Semi-Coupled theme engine`. This approach could also be used for more complex components as the need arises.

