# 6. One field row per component instance

Date: 2025-08-11

Issue: <https://www.drupal.org/project/canvas/issues/3520449>

## Status

Accepted

## Context

_This supersedes [ADR #2](../0002-Use-SDC-slots-to-build-component-tree-and-field-types-for-populating-SDC-props.md.md)._

### Contrast
After ~1 year of work on Canvas (Drupal Canvas), the context in ADR #2 is no longer accurate:
- Canvas no longer only supports [Single-Directory Components](https://www.drupal.org/project/sdc), but also Block plugins and "code components", with a (still maturing) [abstraction layer to support more](../../components.md).
- Storing 2 JSON blobs (`tree` and `inputs`) per revision was a fine start, but had to be refactored away from for multiple reasons:
  - [Drupal core's #3343634 Add "json" as core data type to Schema and Database API](https://www.drupal.org/project/drupal/issues/3343634) did not land by Drupal 11.2, which is the core version during which Canvas will see its 1.0 release
  - Updating JSON blobs, for example when a component implementation changes and requires an update path (e.g. SDC changing the type of a prop, block plugins changing the type of a setting, removing one SDC in favor of another with similar but different props, etc.), is brittle and risky

Point 5 in ADR #2 does still stand:

> 5. To populate SDCs' props, existing field types and widgets are reused. A lot of infrastructure is necessary for this: matching SDC's props' JSON schemas against Drupal field types as well as field instances

But that functionality has since been generalized (`GeneratedFieldExplicitInputUxComponentSourceBase`), to allow other `Component Source Plugin`s to also use this: when a new type of component does not have a native input UX, this can be used (assuming the component's inputs are described using JSON Schema).

Unchanged from ADR #2: ideally, all existing Drupal functionality continues to work, because that means:

- Drupal Canvas gets to start with an existing ecosystem, instead of having to start from zero
- prior _investments_ in functionality are not forfeited

### Recap

In summary, Canvas's (server-side) data model was originally designed with:

* Only SDCs
* Dynamic data fetched from host entity fields via expressions (i.e. `DynamicPropSources`)
* Static data stored in independent field item objects (i.e. `StaticPropSources`)
* Both symmetric translations (`tree` locked, all `inputs` translatable) and asymmetric translations (`tree` and `inputs` both translatable)
* The same component tree representation to be used for content and config entities
* Support both revisions and translations when a component tree lives in a content entity

This model served well in the prototype and early implementation stages, but it needs to be finalized to minimize
disruption after [`1.0.0-beta1`](https://www.drupal.org/project/canvas/issues/3515932). Since then, we are seeing a need to evolve the data model to support:

### Functional requirements
1. Support multiple types of components, not just SDCs → ComponentSource plugins
2. Must be compatible with the ability to auto-save Pages and Nodes with component trees for later publication
3. Must be able to track dependencies that content and config entities have on components for update safety, caching, and auditability
4. Must be able to evolve input schemas over time for explicit inputs
5. Must be able to have some of the inputs of a symmetric translations of _some_ component instances be untranslatable (e.g., an image field: one component instance with an image may need to be the same across all translations because it shows the CEO, but another component instance may need a different image per translation because it shows the local product name: "Lotus" in Belgium, "Biscoff" in the U.S.)
6. Must be able to store one or multiple component trees per content entity: one per exposed slot from a content template, or a single tree if there is no content template
7. Must be compatible with the ability to utilize component tree and inputs via web APIs (e.g., JSON:API) for external systems
8. Must be able to store variants of component trees for use cases such as personalization, and responsive design

### Non-functional requirements:
1. Must be able to support a site with a large number of content entities that use component trees (e.g., 50000+)
2. Must be able to support a content entity with a large number of component instances (e.g., 1000+)
3. Must be able to support a site with multiple languages (e.g., 10+)
4. Must be able to support revisions for a Page or a Node with component instances (e.g., 100+)
5. Must be able to support component instances with a large number of inputs (e.g., 150+)

### What is out of scope:
1. Creating multiple view modes with each their own content templates and exposed slots using a single component tree and inputs — Canvas 1.x will be constrained to a single content template with exposed slots: for the canonical/full view mode. We may decide to add support for content templates for additional view modes, but if so, Canvas 1.x will not support exposed slots for them.
2. Using component props for structured data – Canvas components are designed to consume structured data, not vice versa.

## Decision

Stop:

- storing 1 row containing 2 columns (`tree`, `inputs`, both JSON blobs) per content entity revision component tree.
- using a `Component` config entity whose settings and fallback metadata evolve

Instead, track multiple versions _within_ a `Component` config entity, reflecting the changes over time in:

1. the components themselves (new slots, changed SDC prop type, changed block plugin settings config schema, etc.)
2. the way the values to populate instances of it are stored (e.g. a different field type is used to populate some SDC prop)

Instead, use an approach that is more typical/familiar; store N (# component instances) rows (field item list deltas):

- decompose the component tree in a list ("field item list") that can be resolved back into a tree
- each component instance ("field item") is stored as 1 row, and has a delta
- each delta (field item/row) contains the necessary field properties (columns) to reconstruct the tree:
  1. `uuid` — REQUIRED — identifies component instance in the tree
  2. `component_id` — REQUIRED — identifies `Component` config entity
  3. `component_version` — REQUIRED — identifies version (deterministic hash) of the `Component`
  3. `parent_uuid` — OPTIONAL — identifies parent component instance in the tree, if any
  4. `slot` — OPTIONAL — identifies the slot in the parent component instance in the tree, if any
  5. `inputs` — REQUIRED — JSON blob that can be interpreted by the `Component Source Plugin` providing this component to populate this component instance
- deltas ONLY have meaning within the same `parent_uuid`, `slot` pair
- every field property of every delta is available as Typed Data

Detailed documentation is available (and updated as things evolve beyond this ADR) for:
- the [data model](../../data-model.md)
- the [versioned `Component` config entity](../../config-management.md#3.1)

## Consequences

In order of importance, with the following markers:
- positives (`+`) vs negatives (`-` vs status quo (`≃`)
- impact types: technical (`T`) vs operational (`O`) vs business with (`B`)

1. `+TO` It is simple to query a component tree (using SQL), resulting in:
  - Efficient updating of component trees/instances as components or `Component Source Plugin`s evolve (or even disappear)
  - The ability to retrieve a subset of a component tree, for example to support alternative renderings
2. `+T` Because everything in the component tree is available as Typed Data, it is simple to add JSON:API read/write support (or GraphQL, or …) — including filtering trees
3. `+TOB` It is simple to add _new_ capabilities that are per-component instance:
  - For example, adding a `label` field property/column to allow Content Creators to _name_ component instances: [#3460958](https://www.drupal.org/project/canvas/issues/3460958)
  - In the future: adding support for _component variants_: add a `variant` field property/column, which would be `NULL` when it is the default variant (or the component has no variants), making it possible to query across all component instances and surface how much usage each variant sees
  - In the future: adding support for _locking a subset of the explicit inputs when using asymmetric translations_: add an `untranslatable_inputs` field property/column, which would have its key-value pairs merged with those in `inputs` (and always be `NULL` when using symmetric translations)
4. `+T` Supporting "exposed slots defined in content templates" functionality is as simple as allowing `slot` to be an exposed slot defined by the `ContentTemplate` config entity
3. `+TOB` Improving storage efficiency (deduplicating the same `inputs` data across different revisions of the same component instance and/or across component instances within the same revision) can be done in a generic way (not specific to Canvas), benefiting many field types at once
6. `≃T` The JSON blob for each component instance's `inputs` field property/column still remains that: a _blob_, which only the corresponding `Component Source Plugin` can load, validate, and understand.
7. `≃TOB` This shifts away from an _almost_ document-oriented data storage model to a relational data storage model
  - `+TOB`This makes Canvas more in line with the rest of the Drupal ecosystem
  - `+TOB` This mitigates the risks associated with relying on per-database differences in their JSON support (see [#3343634](https://www.drupal.org/project/drupal/issues/3343634))
  - `+TO` This strikes a careful balance between making all relations between component instances and `Component` versions be queryable, but not the `inputs` for each component instance.
