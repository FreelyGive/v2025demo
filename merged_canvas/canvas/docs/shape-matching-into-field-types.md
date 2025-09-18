
# Drupal Canvas's Shape Matching into Field Types

In the rest of this document, `Drupal Canvas` will be written as `Canvas`.

This builds on top of the [`Canvas Components` doc](components.md). Please read that first.

**Also see the [diagram](diagrams/data-model.md).**

## Finding issues 🐛, code 🤖 & people 👯‍♀️
Related Canvas issue queue components:
- [Shape matching](https://www.drupal.org/project/issues/canvas?component=Shape+matching) (see section
   3.1.2 below, and specifically 3.1.2.a)
- [Redux-integrated field widgets](https://www.drupal.org/project/issues/canvas?component=Redux-integrated+field+widgets)
- [Data model](https://www.drupal.org/project/issues/canvas?component=Data+model)

Those issue queue components also have corresponding entries in [`CODEOWNERS`](../CODEOWNERS).

If anything is unclear or missing in this document, create an issue in one of those issue queue components and assign it
to one of us! 😊 🙏

## 1. Terminology

### 1.1 Existing Drupal Terminology that is crucial for Canvas

- `computed field prop`: not every `field prop` has their value _stored_, some may have their value _computed_ (for example: the `file_uri` field type's `url` prop)
- `base field`: a `field instance` that exists for _all_ bundles of an entity type, typically defined in code
- `bundle field`: a `field instance` that exists for _some_ bundles of an entity type, typically defined in config
- `content entity`: an entity that can be created by a Content Creator, containing various `field`s of a particular entity type (e.g. "node")
- `content type`: a definition for content entities of a certain entity type and bundle, and hence every `content entity` of this bundle is guaranteed to contain the same `bundle field`s
- `data type`: Drupal's smallest unit of representing data, defines semantics and typically comes with validation logic and convenience methods for interacting with the data it represents ⚠️ Not all data types in Drupal core do what they say, see `\Drupal\canvas\Plugin\DataTypeOverride\UriOverride` for example. ⚠️
- `field`: synonym of `field item list`
- `field prop`: a property defined by a `field type`, with a value for that property on such a `field item`, represented by a `data type`. Often a single prop exists (typically: `value`), but not always (for example: the `image` field type: `target_id`, `entity`, `alt`, `title`, `width`, `height` — with `entity` a `computed field prop`)
- `field instance`: a definition for instantiating a `field type` into a `field item list` containing >=1 `field item`
- `field item`: the instantiation of a `field type`
- `field item list`: to support multiple-cardinality values, Drupal core has opted to wrap every `field item` in a list — even if a particular `field instance` is single-cardinality
- `field type`: metadata plus a class defining the `field prop`s that exist on this field type, requires a `field instance` to be used
- `field widget`: see [`Redux-integrated field widgets` doc](redux-integrated-field-widgets.md)
- `SDC`: see [`Canvas Components` doc](components.md)

### 1.2 Canvas terminology

- `component`: see [`Canvas Components` doc](components.md)
- `component input`: see [`Canvas Components` doc](components.md)
- `Component Source Plugin`: see [`Canvas Components` doc](components.md)
- `component type`: see [`Canvas Components` doc](components.md)
- `conjured field`: a `field instance` that is not backed by code nor config, but generated dynamically to edit/store a value for a `component input` as `unstructured data`
- `prop expression`: a (compact) string representing what context (entity type+bundle or field type) is required for retrieving one or more properties stored inside of that context; also has a typed PHP object representation to facilitate logic
- `prop shape`: a normalized representation of the schema for a `component input`, without metadata that does not affect the _shape_: a title or description does not affect what values _fit into this shape_; only necessary for `Component Source Plugins` that DO NOT provide their own input UX.
- `prop source`: a source for retrieving a prop value
  - `static prop source`: a `prop source` powered by a `conjured field` (i.e. `unstructured data`)
  - `dynamic prop source`: a `prop source` powered by a `base field` or `bundle field` (i.e. `structured data`)
  - TBD: `remote prop source`: a `prop source` powered by a remote source ("external data"), i.e. data stored outside Drupal
- `structured data`: the data model defined by a Site Builder in a `content type`, and whose smallest units are `field props` — queryable by Views
- `unstructured data`: the ad-hoc data used to populate `component input`s that are not populated using `unstructured data` — NOT queryable by Views, this should be minimized/discouraged

## 2. Product requirements

This uses the terms defined above.

This adds to the product requirements listed in [`Canvas Components` doc](components.md).

(There are [more](https://docs.google.com/spreadsheets/d/1OpETAzprh6DWjpTsZG55LWgldWV_D8jNe9AM73jNaZo/edit?gid=1721130122#gid=1721130122), but these in particular affect Canvas's data model.)

- MUST allow continuing to use existing Drupal functionality (notably: `field type`s and `field widget`s for `Component Source Plugin`s that do not have their own input UX)
- SHOULD encourage Content Creators to use `structured data` whenever possible, `unstructured data` should be minimized except where necessary
- MUST be able to facilitate changes in `component input`s (i.e. schema changes, that may result in a changed `prop shape`)

## 3. Implementation

This uses the terms defined above.

### 3.1 Data Model: from Front-End Developer to an Canvas data model that empowers the Content Creator

⚠️ This only applies to `component`s originating from a `Component Source Plugin` that DO NOT have an input UX (such as
`SDC`), for others the UX and storage are both simply the existing one, and NOTHING in this document applies! ⚠️

#### 3.1.1 Interpreting `component`s without input UX: `prop shapes`

See `\Drupal\canvas\PropShape\PropShape`.

Each `component input` must have a schema that defines the primitive type (string, number,  integer, object, array or
boolean), with typically additional restrictions (e.g. a  string containing a URI vs a date,  or an integer in a certain
range). That primitive type plus additional restrictions identifies a unique `prop shape`.

#### 3.1.2 Finding fitting `field type`: `conjured field`s and `field instance`s

Per the product requirements, existing `field type`s and `field widget`s MUST be used, and ideally `structured data`
SHOULD be used.  But `field type`s can be configured, and depending on the configured settings, they may support rather
different `prop shape`s. For example: Drupal's "datetime" `field type` can, depending on settings, store either:

- date only
- date and time

So, the settings for a `field type` are critical: a `field type` alone is insufficient. How can `Canvas` determine the
appropriate field settings for a `prop shape`? And what about existing `structured data` versus `unstructured data`?

⚠️ _Why even have `unstructured data`? Why not create `structured data` to populate all `component input`s?_, you might
ask. Because:

- `structured data` requires `base field`s or `bundle field`s, and once in use, they cannot be removed
- therefore capturing all values for `component input`s as `structured data` would cause many new `bundle field`s to be
  created that may shortly thereafter no longer be used
- plus, not all `component input`s contain meaningful information to query — many contain purely _presentational_
  information such as the width of a column, the icon to use, et cetera
- in other words: `component input`s should be populated by `structured data` if they contain _semantical_ information,
  otherwise it is _presentational_ information and hence `unstructured data` is more appropriate

##### 3.1.2.a `structured data` → matching `field instance`s ⇒ `dynamic prop source`

See:
- `\Drupal\canvas\ShapeMatcher\JsonSchemaFieldInstanceMatcher`
- `\Drupal\canvas\JsonSchemaInterpreter\JsonSchemaType::toDataTypeShapeRequirements()`

All `structured data` in every `content entity` in Drupal is found in `base field`s and `bundle field`s. These already
have field settings defined. Hence `Canvas` must **match** a `field instance` for a given `prop shape`.

How can this reliably be matched? Drupal's validation constraints for `field type`s and `data type`s determine the
precise shapes of values that are allowed … exactly like a `prop shape` (i.e. the JSON schema for a `component input`)!

Hence the matching works like this:
1. transform the JSON schema of a `prop shape` to the equivalent primitive `data type` + validation constraints (see
   `JsonSchemaType::toDataTypeShapeRequirements()`)
2. iterate over all `field instance`s in the site, and compare the previous step's `data type` + validation constraints
   to find a match

Finally, while the `prop shape` may be the same for many `component input`s, that same `prop shape` may be required for
one `component`'s `component input`, but optional for another. So an additional filtering step is required for optional
versus required occurrences of a `prop shape`:
3. if a `component input` is required, the matching `field instance`s must also be marked as required

The found `field instance` can then be used in a `dynamic prop source`, that can be _evaluated_ to retrieve the stored
value that fits in the `prop shape`.

See `\Drupal\canvas\PropSource\DynamicPropSource`.

⚠️ **Multiple** bits of `structured data` may be able to fit into a given `prop shape`. All viable choices are
suggested by `\Drupal\canvas\ShapeMatcher\FieldForComponentSuggester`. The Content Creator or Site Builder
will choose one.

ℹ️ The completeness of this is tested by `\Drupal\Tests\canvas\Kernel\EcosystemSupport\FieldTypeSupportTest`.

##### 3.1.2.b `unstructured data` → generating `conjured field`s ⇒ `static prop source`

See:
- `\Drupal\canvas\JsonSchemaInterpreter\JsonSchemaType::computeStorablePropShape()`
- `\Drupal\canvas\PropShape\StorablePropShape`
- `hook_storage_prop_shape_alter()`

For any `unstructured data`, no field settings exist yet, so the appropriate settings for a `prop shape` must be
generated. `JsonSchemaType::computeStorablePropShape()` contains logic to that relies only on `field type`s
available in Drupal core. Unlike for `structured data`, no additional complexity is necessary for required versus
optional `component input`s.

Contributed modules can implement `hook_storage_prop_shape_alter()` to make different choices.

The computed `\Drupal\canvas\PropShape\StorablePropShape` can be used to create a `static prop source`
(which contains all information for the `conjured field` that powers it), that can be _evaluated_ to retrieve the stored
value that fits in the `prop shape`.

See `\Drupal\canvas\PropSource\StaticPropSource`.

⚠️ When choosing to use `unstructured data` to populate a `component input`, Canvas decides
using the aforementioned logic what `field type`, `field widget` et cetera to use. Only when using `structured data`,
there is a need for an additional choice (see the `FieldForComponentSuggester` mentioned in 3.1.2.a).

#### 3.1.3 `prop expression`s: evaluating a `dynamic prop source` or `static prop source`

See
- `\Drupal\canvas\PropExpressions\StructuredData\Evaluator`
- `\Drupal\canvas\PropExpressions\StructuredData\StructuredDataPropExpressionInterface`
- `\Drupal\canvas\PropExpressions\StructuredData\FieldPropExpression`
- `\Drupal\canvas\PropExpressions\StructuredData\FieldTypePropExpression`
- `\Drupal\Tests\canvas\Unit\PropExpressionTest`

Many `field type`s contain a single `field prop` (typically named "value"), but not all. Most `field type`s have one
required "main prop", many have additional optional props or even computed props.

To reliable retrieve the value from a `static prop source` or `dynamic prop source`, the `field item` alone is
insufficient: `Canvas` needs to know exactly which `field prop`(s) to retrieve from a `field item`. Plus, it may need to
arrange those retrieved values in a particular layout (for `prop shape`s that use the "object" primitive type the right
key-value pairs must be assembled).

To express that, `prop expression`s exist, which define:

1. what context they need:
  - `field item` or `field item list` of a certain `field type`
  - or a `content entity` of a certain `content type` (which then contains a `field
2. optionally: specify a delta to determine which `field item` from a `field item list` to use. The absence of a delta
   is interpreted as "everything please". For a `field item list` configured for single cardinality that would be a
   single value, versus an array of values for multiple cardinality.
3. what `field prop`s they must retrieve in that context, possibly following entity reference
4. what the resulting shape is: either a single value (typically) or a list of key-value pairs — in the latter case the

`prop expression`s have 2 representations:

- a string representation, to simplify both debugging and storing them (both of those benefit from terseness) — to
  convert to the other representation: `StructuredDataPropExpression::fromString()`)
- a typed PHP object representation, to simplify logic interacting with them — to convert to the other representation:
  cast to string using `(string)`)

Examples:
- `ℹ︎␜entity:node:article␝title␞99␟value` declares it evaluates an "article" `content entity`, and returns the "value"
  prop of the 100th `field item` in the "title" `field`
- `ℹ︎image␟{src↝entity␜␜entity:file␝uri␞␟url,alt↠alt}` declares it evaluates an "image" `field item`, and returns
  two key-value pairs:
  - the first one being "src" for which the first "url" `field prop` of the "uri" `field` on the "file"
    `content entity` that is referenced by the "image" `field type`
  - the second one being "alt", which can be retrieved directly from the "image" `field item`

For more examples, see `\Drupal\Tests\canvas\Unit\PropExpressionTest`.

### 3.2 Additional functionality overlaid on top of the SDC JSON Schema

Drupal Canvas extends SDC JSON Schema to support additional prop shapes to complete the content editing experience.

#### 3.2.1 HTML Content with CKEditor 5 Integration

Drupal Canvas supports rich text editing for `prop shape`s through CKEditor 5 integration. This allows SDC
developers to define props that can contain formatted HTML content.

##### JSON Schema Extensions

Two additional metadata properties are used to indicate HTML content — one is part of the JSON Schema standard, the
other is a [custom annotation](https://json-schema.org/understanding-json-schema/reference/non_json_data#contentmediatype)
(which can be recognized by the `x-` prefix).

```yaml
heading:
  type: string
  contentMediaType: text/html
  x-formatting-context: inline
```

- `contentMediaType: text/html` - Indicates this is a prop expecting to receive HTML content
- `x-formatting-context: inline|block` - Optionally specifies the formatting context (`block` is the default):
  - `inline`: Only inline elements allowed (`<strong>`, `<em>`, `<u>`, `<a>`)
  - `block`: Both inline and block elements allowed (adds `<p>`, `<br>`, `<ul>`, `<ol>`, `<li>`)

##### Text Formats

To allow populating such props, Drupal Canvas provides two predefined text formats:

1. **Canvas HTML Inline Format**
   - Allows only inline elements: `<strong>`, `<em>`, `<u>`, `<a href>`
   - Appropriate for headings, labels, and other inline content

2. **Canvas HTML Block Format**
   - Allows both inline elements and block elements: `<p>`, `<br>`, `<ul>`, `<ol>`, `<li>`
   - Appropriate for longer content blocks, descriptions, etc.

##### Example Component with HTML Props

```yaml
props:
  heading:
    type: string
    title: "Heading"
    contentMediaType: text/html
    x-formatting-context: inline

  description:
    type: string
    title: "Description"
    contentMediaType: text/html
    # This is the default, so it can be omitted.
    x-formatting-context: block
```
