# The Drupal Canvas Data Model

In the rest of this document, `Drupal Canvas` will be written as `Canvas`.

This builds on top of the [`Canvas Components` doc](components.md). Please read that first.

Some of the examples here refer to details that `component type`s that use
[`Canvas Shape Matching into Field Types` doc](shape-matching-into-field-types.md). It should be possible to first read this
without having read that, to understand the big picture. It is recommended to first read this, then that one, followed
by a second pass of this document.

It also builds on top of the [`Canvas Config Management` doc](config-management.md), which itself refers back to this one
for a few things. The data model is built on top of the configuration architecture.

**Also see the [diagram](diagrams/data-model.md).**

## Finding issues 🐛, code 🤖 & people 👯‍♀️
Related Canvas issue queue components:
1. [Data model](https://www.drupal.org/project/issues/canvas?component=Data+model)

Those issue queue components also have corresponding entries in [`CODEOWNERS`](../CODEOWNERS).

If anything is unclear or missing in this document, create an issue in one of those issue queue components and assign it
to one of us! 😊 🙏

## 1. Terminology

### 1.1 Existing Drupal Terminology that is crucial for Canvas

- `content entity`: an entity that can be created by a Content Creator, containing various `field`s, potentially including the `Canvas field type`, of a particular entity type (e.g. "node")
- `data type`: Drupal's smallest unit of representing data, defines semantics and typically comes with validation logic and convenience methods for interacting with the data it represents ⚠️ Not all data types in Drupal core do what they say, see `\Drupal\canvas\Plugin\DataTypeOverride\UriOverride` for example. ⚠️
- `field`: synonym of `field item list`
- `field prop`: a property defined by a `field type`, with a value for that property on such a `field item`, represented by a `data type`. Often a single prop exists (typically: `value`), but not always (for example: the `image` field type: `target_id`, `entity`, `alt`, `title`, `width`, `height` — with `entity` a `computed field prop`)
- `field instance`: a definition for instantiating a `field type` into a `field item list` containing >=1 `field item`
- `field item`: the instantiation of a `field type`
- `field item list`: to support multiple-cardinality values, Drupal core has opted to wrap every `field item` in a list — even if a particular `field instance` is single-cardinality
- `field type`: metadata plus a class defining the `field prop`s that exist on this field type, requires a `field instance` to be used
- `SDC`: see [`Canvas Components` doc](components.md)
- `theme region`: see [`Canvas Config Management` doc](config-management.md)
- `view mode`: view modes lets a `content entity` be displayed in multiple ways

### 1.2 Canvas terminology

- `component`: see [`Canvas Components` doc](components.md)
- `Component config entity`: see [`Canvas Config Management` doc](config-management.md)
- `component instance`: a UUID uniquely identifying this instance + `component version` + values for each required `component input` (if any) + optionally values for its `component slot`s (if any)
- `component node`: one of the node types in the UI data model, representing a `component instance` in the `component tree`
- `component input`: see [`Canvas Components` doc](components.md)
- `component slot`: see [`Canvas Components` doc](components.md)
- `Component Source Plugin`: see [`Canvas Components` doc](components.md)
- `component tree`: a tree of `component instance`s, by placing >=1 `component instance`s in a particular order in another `component instance`'s slot
- `component tree field type`: Canvas's field type that allows storing a `component tree` ⚠️ This is currently limited to the "default" `view mode`, and hence one component tree per `content entity`. ⚠️
- `component tree root`: the root of the `component tree` is the special case: it does not exist in another `component`, but it behaves the same as any other `component slot`
- `component type`: see [`Canvas Components` doc](components.md)
- `component version`: a version (a deterministic hash) identifying the _version_ of a `Component config entity` either because the underlying `component` itself changed, or because the default `static prop source`s changed due to modified shape matching
- `content type template`: see [`Canvas Config Management` doc](config-management.md).
- `layout`: synonym of `component tree`
- `prop expression`: see [`Canvas Shape Matching into Field Types` doc](shape-matching-into-field-types.md)
- `prop source`: see [`Canvas Shape Matching into Field Types` doc](shape-matching-into-field-types.md)
- `static prop source`: see [`Canvas Shape Matching into Field Types` doc](shape-matching-into-field-types.md)
- `dynamic prop source`: see [`Canvas Shape Matching into Field Types` doc](shape-matching-into-field-types.md)
- `region node`: one of the node types in the UI data model, representing a `theme region`'s `component tree`
- `slot node`: one of the node types in the UI data model, representing a `component instance`'s `component slot`
- `Canvas field`: an instance of the `component tree field type`
- `Canvas field type`: see `component tree field type`

## 2. Product requirements

This uses the terms defined above.

This adds to the product requirements listed in [`Canvas Components` doc](components.md) and [`Canvas Config Management` doc](config-management.md).

(There are [more](https://docs.google.com/spreadsheets/d/1OpETAzprh6DWjpTsZG55LWgldWV_D8jNe9AM73jNaZo/edit?gid=1721130122#gid=1721130122), but these in particular affect Canvas's data model.)

- MUST have validation logic that generates consistent validation error messages for either content (a `component tree` created by the Content Creator and stored in a `content entity`) or config (a `component tree` created by the Site Builder and stored in a `content type template`)
- MUST support both symmetric and asymmetric translations (same vs different `layout` per translation, respectively)
- SHOULD facilitate real-time collaborative editing

## 3. Implementation

This uses the terms defined above.

Given a component developed by a [Front-End Developer](diagrams/structurizr-SystemContext-001.md): how does Canvas allow a
Content Creator to place a `component instance` in the `component tree`, specify values for the `component input`s and
`component slot`s?

### 3.1 Data Model: from Front-End Developer to an Canvas data model that empowers the Content Creator

Moved to the [`Canvas Shape Matching into Field Types` doc](shape-matching-into-field-types.md).


### 3.2 Data Model: storing a component tree

The `component tree` is represented by a `\Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList`, which
contains one field value for each `component instance` in the tree.
Each `component instance` is represented by a `\Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem`, which
each allow accessing the `Component config entity` and `Component Source Plugin` that represents the `component`.

See `\Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem` + its validation constraint.

Canvas defines a new `Canvas field type` with the following `field prop`s:
- _uuid_ — A unique ID for this `component instance`
- _component_id_ — This is the ID of the `Component config entity` this `component instance` references
- _parent_uuid_ — If this `component instance` is placed inside another `component instance` in the tree, the UUID of the parent `component instance`
- _slot_ — If this `component instance` is placed inside another `component instance` in the tree, the machine name of the `component slot` in which it is placed. This slot must exist in the parent `component instance`.
- _inputs_ — see 3.2.2

When _parent_uuid_ and _slot_ are empty, the `component instance` is at the root of the `component tree`.

Additionally there are two computed `field prop`s:
- _component_ - this is an entity reference to the `Component config entity` the `component instance` uses, meaning also the appropriate version will be loaded. Any methods on the `Component config entity` can be chained. E.g. `$item->get('component')?->getComponentSource()`.
- _parent_item_ - this is a data reference to the sibling `\Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItem` in the tree that represents the `component instance`'s parent `component instance` in the `component tree`. If the `component instance` has no parent, this will be NULL. Any methods on the parent `component instance` can be chained, e.g. `$item->get('parent_item')->getComponent()?->getComponentSource()?->getSlotDefinitions()`

Additionally, convenience methods for accessing/setting values on the `ComponentTreeItem` exist including:
- `getParentUuid(): ?string` - gets the value of _parent_uuid_ if it exists
- `getParentComponentTreeItem(): ?ComponentTreeItem` - gets the parent `component instance` if it exists
- `getSlot(): ?string` - gets the `component slot` machine name if it exists
- `getComponent(): ComponentInterface` - gets the `Component config entity` at the specified version
- `getComponentId(): string` - gets the ID of the `Component config entity`
- `getComponentVersion(): string` - gets the version of the `Component config entity` used for this instance
- `getUuid(): string` - gets the UUID of the `component instance`
- `getInputs(): ?array` - gets the explicit inputs of the `component instance` as an array (JSON decoded)
- `getInput(): ?string` - gets the explicit inputs of the `component instance` as a string (JSON encoded)
- `setInput(array|string $input): static` - sets the inputs, can be passed as either a string (JSON encoded) or an array
- `getLabel(): ?string` - gets the (optional) label for the `component instance` to provide context for content authors
- `setLabel(?string $label): self` - sets the (optional) label for the `component instance` to provide context for content authors

Storing these as separate `field prop`s simplifies supporting both symmetric and asymmetric translations:
- the _inputs_ column group (just the `inputs` column) group SHOULD always be translatable
- the _tree_ column group (comprising `uuid`, `component_id`, `component_version`, `parent_uuid` and `slot`) can be either:
  1. marked translatable for _asymmetric translations_ (a different `component tree` per `content entity` translation)
  2. marked untranslatable for _symmetric translations_ (same `component tree` for all `content entity` translations)

(Drupal's Content Translation module natively supports configuring this.)

#### 3.2.1 The columns (`field prop`s) storing the tree structure

The `uuid`, `component_id`, `component_version`, `parent_uuid` and `slot` columns model the tree structure.

See `\Drupal\canvas\Plugin\DataType\ComponentTreeStructure` + its validation constraint.

These columns always meet the following requirements
1. every `component instance` is represented by a "uuid, component_id, component_version" triple, with:
  - the value for "component_id" being the ID of a `Component config entity` (NOT that of the underlying `component`)
  - the value for "component_version" being a version on the (versioned!) `Component config entity` (see `\Drupal\canvas\Entity\VersionedConfigEntityInterface::getVersions()`)
  - the "uuid" being a randomly generated UUID
2. Any top-level items have NULL for both the `parent_uuid` and `slot`.
3. Nested components must have a value for both the `parent_uuid` and `slot`.
    1. The `parent_uuid` must exist in a sibling field item in the `ComponentTreeItemList`.
    2. The `slot` must be present in the parent `component`'s slot definitions
    3. The `parent_uuid` must not be the same as the `uuid` - you cannot reference yourself as a parent
4. Each `uuid` must be unique in the list of items
5. The `delta` of each field item represents the order that components in the same level of the tree appear in.

#### 3.2.2 The column (`field prop`) storing the `component input` values

See
- `\Drupal\canvas\Plugin\DataType\ComponentInputs`
- `\Drupal\canvas\ComponentSource\ComponentSourceInterface::getExplicitInput()`
- `\Drupal\canvas\ComponentSource\ComponentSourceInterface::validateComponentInput()`

_This uses 3.1._

The `component tree`'s _inputs_ `field prop` has a trivial representation that could easily change. It is stored as a
JSON blob, and meets the following requirements:
1. it contains opaque arrays that are validated by that source's `::validateComponentInput()` and are decodable using that source's
   `::getExplicitInput()`
2. the `inputs` for a given component live in the same field-item as its corresponding `uuid`, `component_id`, `parent_uuid` and `slot`

Note: this simplifies different (symmetric) translation strategies: it's trivial to either reuse another translation's
_inputs_ `field prop` (to show what to translate from) or not reuse anything at all — that needs only array intersection.

Note: a welcome bonus is that when real-time collaborative editing is eventually added, one user can move a
`component instance` while another edits the _inputs_ of that same `component instance`, without causing a conflict.
This is because editing will be specific to a `component instance`, which is modeled as a single delta.

No validation is necessary for this `field prop`, because it is more easily validated at the `field item` level of the
`Canvas field type`, not at the `field prop` level — there, the aforementioned `::validateComponentInput()` method is called
for every `component instance` encountered in the stored `component tree`. If the `Component Source Plugin` complains, a
validation error occurs.

Example: A simple tree showing a root item (`41595148-e5c1-4873-b373-be3ae6e21340`) with a child (`3b305d86-86a7-4684-8664-7ef1fc2be070`) in the `body` slot, plus another root item (`41595148-e5c1-4873-b373-be3ae6e21340`).
```php
[
  'uuid' => '41595148-e5c1-4873-b373-be3ae6e21340',
  'component_id' => 'sdc.canvas_test_sdc.props-slots',
  'component_version' => 'ab4d3ddce315cf64',
  'inputs' => [
    'heading' => [
      'sourceType' => 'static:field_item:string',
      'value' => "Hello, world!",
      'expression' => 'ℹ︎string␟value',
    ],
  ],
],
[
  'uuid' => '3b305d86-86a7-4684-8664-7ef1fc2be070',
  'component_id' => 'sdc.canvas_test_sdc.props-no-slots',
  'component_version' => '95f4f1d5ee47663b',
  'parent_uuid' => '41595148-e5c1-4873-b373-be3ae6e21340',
  'slot' => 'the_body',
  'inputs' => [
    'heading' => [
      'sourceType' => 'static:field_item:string',
      'value' => "It's me!",
      'expression' => 'ℹ︎string␟value',
    ],
  ],
  [
    'uuid' => '41595148-e5c1-4873-b373-be3ae6e21340',
    'component_id' => 'block.system_branding_block',
    'component_version' => '247a23298360adb2',
    // Example, that populates a Block component instance.
    // Note how much simpler the stored information is, because it uses the Block system's native input UX:
    'inputs' => [
      'label' => '',
      'label_display' => FALSE,
      'use_site_logo' => TRUE,
      'use_site_name' => TRUE,
      'use_site_slogan' => TRUE,
    ],
  ],
],
```

#### 3.2.3 Validation

Assuming the _tree_ column groups (`uuid`, `component_id`, `parent_uuid` and `slot`) has already been validated, a `component tree` described in an `Canvas field` then is valid
when: for each `component instance` in the _tree_ `field prop`:
1. getting the explicit input using `ComponentSourceInterface::getExplicitInput()` (which for `Block` requires no extra
   work but for `SDC` involves resolving the stored `prop source`s, resulting in values to be passed to the
   corresponding `component input`s)
2. calling `ComponentSourceInterface::validateComponentInput()` (which for `Block` uses config schema validation and for
  `SDC` checking if `\Drupal\Core\Theme\Component\ComponentValidator::validateProps()` does not throw an exception)

#### 3.2.4 Facilitating `component input`s changes

When a `component` evolves, _some_ `component input`s cannot happen without also updating the stored `component tree`. In
other words: an upgrade path is necessary if a Front-End Developer makes certain drastic changes:
- renaming a `component input`
- changing the schema of a `component input`
- adding a new _required_ `component input`

Here too, storing the _inputs_ as separate `field props` is helpful. An upgrade path for a `component` would
require logic somewhat like this:

1. SQL query to search the `component_id` column for uses of this `component`, capture the UUIDs. If 0 matches: break.
2. If >0 matches, PHP logic computes the necessary changes.
3. Insert the updated _inputs_ JSON blob into that specific delta.

The above sequence assumes doing this per-entity. But this can actually be done _per entity-type_, or more precisely:
per `Canvas field`. So if the `Canvas field type` is only used for one entity type but is used in many bundles (i.e. many
different `content entity type`s), then a single query can find all `component instances` of the evolving `component`.
After that point, the typical Drupal update path best practices apply. The key observation here: it is possible to
efficiently find all uses of a `component`.

### 3.3 Data Model: rendering a stored `component tree`

See `\Drupal\canvas\Plugin\Field\FieldType\ComponentTreeItemList`.

_This uses 3.2.1, 3.2.2 and 3.2.3._

Thanks to the validation in 3.2.3, it is guaranteed that each individual `component instance` _can_ be rendered. But the
goal is of course to render a `component tree` (not `component instance`s), by starting at the root and rendering each
`component instance` in the specified `component slot`.

To hydrate the stored `component tree`:
1. get (flat) list of `component instance`s from the _tree_ `field prop` (3.2.1): a list of `uuid`s
2. load the corresponding `Component config entity` for each `component instance` given its UUID, which in turn enables
   loading the corresponding `Component Source Plugin`
3. get the explicit input from the _inputs_ `field prop` (3.2.2) for each `component instance`, by using the `Component
   Source Plugin`'s `::getExplicitInput()` method
   - for `Component Source Plugin`s with their own input UX (such as `BlockComponent`), that's just forwarding the
     stored values
   - for those without their own input UX (such as `SingleDirectoryComponent`), that may require additional resolving or
     evaluating (such as resolving the stored `prop source` — see 3.1)
4. pass those explicit inputs to each `component instance`, resulting in a list of hydrated `component instance`s
5. transform that list to a tree by respecting the _tree_ `field prop` (3.2.1), by placing nested `component instance`s
   in the specified `component slot` of the specified parent `component instance` (special case: the root)

To render the stored `component tree`, it must first be hydrated it (see above), after which it can be
converted to a render array.

### 3.4 UI Data Model: communicating a `component tree` to the front end

All prior sections refer to the data model that is _stored_ (on the back end). But what makes sense on the back end does
not necessarily make sense on the front end:
- the back end must integrate with many (server-side) Drupal subsystems, and it should as much as possible avoid
  burdening the front end with those implementation details
- the front end has different data structure needs, specifically the need for highly frequent changes, including
  concurrent ones during collaborative real-time editing

The front end needs the `component tree` to generate a preview that the end
user can modify and interact with. For this we split the tree into `layout`
and `model` parts. The `layout` represents the tree's overall structure
and the `model` represents data for each `component` within that tree.
The `model` is stored as a flat structure so it can more easily be queried
by the front end.

The front end `layout` is a set of nodes, where each node can be one of the
following types, represented by the `nodeType` key:

- `'component'` which represents a `component instance`
- `'slot'` which represents a `component slot` in a `component instance`.
- `'region'` which represents a separate `theme region` in the user interface.

Each node in the `component tree` is described with a `nodeType` key which is one of the above 3 strings.

The top level of the `layout` structure is an array of zero or more `region` nodes.

#### 3.4.1 `component node`s

A `component node` represents a single `component instance` in the `component tree` and
will contain zero or more `component slot`s.

`component node`s have the following keys
- `uuid`: a unique identifier for the `component instance`.
- `type`: an opaque string containing a `Component config entity` ID + version that this instantiates
- `name`: a name assigned by a Content Creator, to for example distinguish this particular `component instance` of some
  `component` among the 20 such in the current component tree
- `slots`: an object of `slot node`s representing each `component slot` of this `component instance` (including empty
  slots)

An example simple `component instance` of a `component` with no `component slot`s, and with a name for the `component
instance` specified by the Content Creator:
```json
{
  "nodeType": "component",
  "id": "380aaa26-5678-4c86-9b32-12161ea34196",
  "name": "Most Important Heading",
  "type": "sdc.canvas_test_sdc.heading@1b4f8df7c94d7e3c",
  "slots": []
}
```

An example simple `component instance` of a `component` with a single `component slot` that is empty:
```json
{
  "nodeType": "component",
  "id": "177122af-1679-4ee4-b700-dcf5ab376c4a",
  "type": "sdc.canvas_test_sdc.one_column@f6a3a392e98e8342",
  "slots": [
    {
      "id": "177122af-1679-4ee4-b700-dcf5ab376c4a/content",
      "name": "content",
      "nodeType": "slot",
      "components": []
    }
  ]
}
```

#### 3.4.2 `slot node`s

A `slot node` must be the child of a `component node`.

`slot node`s have the following keys
- `name`: a human-readable name that may be displayed to the user.
- `components`: an array of `component node`s that represent the top-level `component instances` for this `component slot`
- `id`: a unique ID made up of the `uuid` of the parent component followed by the `component slot` name, separated by a slash.

```json
{
  "nodeType": "slot",
  "id": "380aaa26-5678-4c86-9b32-12161ea34196/column_one",
  "name": "Column one",
  "components": []
}
```

#### 3.4.3 `region node`s

A `region node` can only exist at the top level in the `layout` tree and can be
thought of as a special case of a `slot` that applies to the page rather than
a `component`. Just like a `slot node`, it can contain zero or more `component node`s.

`region node`s have the following keys
- `id` is the identifier of the `theme region`.
- `name`: a human-readable name that may be displayed to the user.
- `components`: an array of `component node`s that represent the top-level `component instances` for this `theme region`

The `theme region` with the ID of `content` is treated specially by the server, and assumed to contain
the `content entity`. The front end should not need to do anything special
here except perhaps default to editing the `content` region (but perhaps the
server should express this default via a flag somewhere?).

```json
{
  "nodeType": "region",
  "id": "content",
  "name": "Content",
  "components": []
}
```

#### 3.4.4 The complete API response

The API response contains two top level keys:

- `layout`: the `component tree` described above, using the 3 layout tree node types
- `model`: an array of model data for each `component node` in the tree, keyed by the
UUID of the `component instance`.

(What if the model and layout get out of sync? We could theoretically have
UUIDs that don't have model values, or model values that are orphaned and
don't have corresponding components in the layout. The server side's validation logic forbids saving in this case.)

A complete example, with three `region node`s:
* A `'header'` region with a single component instance.
* A `'content'` region with multiple, nested component instances a tree.
* An empty `'footer'` region.

```json
{
  "layout": [
    {
      "nodeType": "region",
      "id": "header",
      "name": "Header",
      "components": [
        {
          "nodeType": "component",
          "id": "a164fa84-0460-40b0-a428-bf332b4a792a",
          "type": "block.system_branding_block@247a23298360adb2",
          "slots": []
        }
      ]
    },
    {
      "nodeType": "region",
      "id": "content",
      "name": "Content",
      "components": [
        {
          "nodeType": "component",
          "id": "97fb7bb9-4c8e-4fdc-87a8-c39ac9e8e618",
          "type": "sdc.canvas_test_sdc.two_column@e5ef92acda2ee2d1",
          "slots": [
            {
              "nodeType": "slot",
              "id": "97fb7bb9-4c8e-4fdc-87a8-c39ac9e8e618/column_one",
              "components": [
                {
                  "nodeType": "component",
                  "id": "e8ecc571-0221-40d8-9ab2-262389fabd58",
                  "type": "sdc.canvas_test_sdc.heading@1b4f8df7c94d7e3c",
                  "slots": []
                },
                {
                  "nodeType": "component",
                  "id": "baf231e8-b214-4e3e-93d3-5d3f03a1eae9",
                  "type": "sdc.canvas_test_sdc.druplicon@some-version-string",
                  "slots": []
                }
              ]
            },
            {
              "nodeType": "slot",
              "id": "97fb7bb9-4c8e-4fdc-87a8-c39ac9e8e618/column_two",
              "components": [
                {
                  "nodeType": "component",
                  "id": "39648574-b937-4a5a-b1b2-9db0f30ae315",
                  "type": "sdc.canvas_test_sdc.one_column@f6a3a392e98e8342",
                  "slots": [
                    {
                      "nodeType": "slot",
                      "id": "39648574-b937-4a5a-b1b2-9db0f30ae315/content",
                      "components": [
                        {
                          "nodeType": "component",
                          "id": "a1cfa9f1-0088-45d9-b837-39571485b75e",
                          "type": "sdc.canvas_test_sdc.my-hero",
                          "slots": []
                        }
                      ]
                    }
                  ]
                }
              ]
            }
          ]
        }
      ]
    },
    {
      "nodeType": "region",
      "id": "footer",
      "name": "Footer",
      "components": []
    }
  ],
  "model": {
    "a164fa84-0460-40b0-a428-bf332b4a792a": {},
    "97fb7bb9-4c8e-4fdc-87a8-c39ac9e8e618": {},
    "e8ecc571-0221-40d8-9ab2-262389fabd58": {
      "text": "Heading",
      "style": "primary",
      "element": "h1"
    },
    "baf231e8-b214-4e3e-93d3-5d3f03a1eae9": {},
    "39648574-b937-4a5a-b1b2-9db0f30ae315": {},
    "a1cfa9f1-0088-45d9-b837-39571485b75e": {
      "heading": "Hero",
      "subheading": "My subheading"
    }
  }
}
```
