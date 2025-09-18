# 5. `Page` Content Entity Type

Date: 2024-11-22

Issue: <https://www.drupal.org/project/canvas/issues/3482259>

## Status

Accepted

## Context

Currently, Drupal Canvas is not integrated with any Drupal content entity type for creating pages. To get started with building content entity support, the goal is for Drupal Canvas to provide the ability to create landing pages. These landing pages do not have configurable fields, nor configurable form displays, nor configurable view displays. This content entity type _does_ use fields (only code-defined base fields) and form and view displays (using the settings specified on the base field definitions). These content entities are managed only through Drupal Canvas. Later, support for other entity types and bundles will be supported.

The page is intended to act opposite of Drupal's normal structured content, by being completely unstructured and content existing solely within Drupal Canvas.

Drupal Canvas must also empower contributed modules to reliably provide such a page without structured content. For example, the [GDPR module](https://drupal.org/project/gdpr) might want to provide a "Privacy policy" page at `/privacy-policy` — without having to make assumptions about what `NodeType`s may exist on a site.
A module's `hook_install()` would be able to call `Page::create()` to create such a content entity and hence guarantee its existence. Module-shipped updates are considered out-of-scope at this time. The shipping module MAY link to the shipped `Page` but only using its _path alias_, i.e. using `internal:/privacy-policy`. Deletion of the `Page` by an authorized user must be respected by the shipping module, and requires checking the existence of the specified path alias prior to generating a link to it.

There are two approaches:

1. Create a locked content type that works solely with Drupal Canvas (i.e. providing a `NodeType`: a bundle for the `Node` content entity type, _somewhat_ locked thanks to `\Drupal\node\Entity\NodeType::isLocked()`, but that does not protect against manipulating config directly, only against UI-based manipulations)
2. Create a custom content entity type that works solely with Drupal Canvas

## Decision

The second approach was chosen. The first approach has unavoidable reliability concerns.


Creating a locked content type (`NodeType`) comes with the following concerns:

* Contributed modules integrated with the `Node` content entity type WILL cause unexpected side effects with Drupal Canvas
* Any business logic specified fields provided by Drupal Canvas may be reused on other content types or possibly modified, leading to unexpected side effects

While these are acceptable for a full release of Drupal Canvas, we are still in early development and need iterate rapidly. Drupal Canvas needs to make direct and opinionated architecture decisions early on. Integrating with an existing system WILL have unknown side effects. These concerns add additional variables that add complexity to the development of Drupal Canvas.

For the content entity type, we will:

* Create the `Page` content entity type:
  - Label: `Page`
  - Machine name: `canvas_page`
  - Class name: `Page` (no prefix needed, since namespace will be `Drupal\canvas\Entity`)
* Provide a `title`, `description`, `path`, and `image` base field.
  - The `title` will be a **required** `string` base field, for the `label` entity key.
  - The `description` will be an **optional** `string_long` base field, usable for SEO purposes and page administration.
  - The `path` will be a **required** `path` base field, adding a dependency on the Path module, so that pages can have custom paths.
  - The `image` will be an **optional** `entity_reference` base field allowing `Media` entities, also adding a dependency on the Media module.
    - The allowed bundles should only `MediaType`s with `allowed_field_types` containing `image`.
    - It will be dynamically determined.
    - Usage for SEO and possibly on page listing, see later bullet for SEO via Metatag.
* The content entity type can use the default `ContentEntityForm` for the `add-form` and `edit-form` form handlers.
* The content entity type's `add-form` and `edit-form` link templates should go to Drupal Canvas, skipping the normal entity edit form experience.
* The content entity type view builder should only render the Drupal Canvas component tree (manually configured entity view display, or hard coded entity view builder handler.)
* If the Metatag module is installed, provide a `metatag` base field so that we can begin integrating with the [Metatag](https://drupal.org/project/metatag) module as an example integration.
* NO bundles supported.
* NO field UI integration, preventing the ability to add fields via the user interface. (This does not prevent programmatic field addition, still enabling extensibility, just not from the user interface.)

The content entity type will require custom work, but the level of effort is equal to creating a locked down `NodeType` (implementing access control hooks, form alters, etc.). We will also be able to easily convert into conditional logic for `Node`, as that is the end goal as well, without much loss of effort due to gains from getting started with a specific implementation.

## Consequences

Using a custom content entity type will deviate from the following functionality that the `Node` content entity type provides:

* Adding a page to a menu, the Menu UI module has logic explicitly tied to `Node` forms.
  - To provide this functionality, we will have to replicate hooks and functions in `menu_ui.module` or the [Menu Link (Field)](https://www.drupal.org/project/menu_link) module.
  - If `menu_ui.module` approach:
    - We can build in the integration more natively, such as a settings form to handle `menu_ui_form_node_type_form_alter()`, `menu_ui_form_node_type_form_validate()`, and `menu_ui_form_node_type_form_builder()`.
    - Same with implementing `menu_ui_form_node_form_alter()`, `menu_ui_node_builder()`, and `menu_ui_form_node_form_submit()`
  - [#2315773: Create a menu link field type/widget/formatter](https://www.drupal.org/project/drupal/issues/2315773) would bring the Menu Link module functionality into Drupal core and solve this problem. Drupal Canvas cannot wait for this to happen, but MUST anticipate for this to happen: it must be built in a way that allows deleting all code in favor of that Drupal core functionality.
* Not being able to create sorted lists of `Page` and `Node` content entities, since Views does not support multiple base tables. User research showed that a [listing mixing both in a single list is confusing](https://www.drupal.org/project/canvas/issues/3482259#comment-15848593:~:text=UX%20has%20identified%20that%20the%20basic%20page%20concept%20is%20somewhat%20distinct%20from%20the%20structured%20content%20and%20that%20at%20least%20in%20some%20UIs%2C%20that%20should%20be%20prioritized%20over%20other%20types%20of%20content.%20Here%27s%20an%20example%20of%20one%20screen%20for%20this%3A), so that is a non-issue.
* Existing [Linkit](https://www.drupal.org/project/linkit) profiles will have to be updated to allow referencing the new entity type. However, existing profiles can be updated.
* Drupal's `link` field type widget is hardcoded to only support `Node` content entities. ([source](https://git.drupalcode.org/project/drupal/-/blob/11.x/core/modules/link/src/Plugin/Field/FieldWidget/LinkWidget.php#L217-220), [issue](https://www.drupal.org/project/drupal/issues/2423093))
* Other entities would need to use [Dynamic Entity Reference](https://www.drupal.org/project/dynamic_entity_reference) to support referencing both `Node`s and `Page`s, if this is a use case.

The new `Page` content entity type described in this ADR is simply a more _explicit_ way of handling unstructured content: it enables a better UX, with less confusion, and with strong guarantees that installing contrib/custom modules will NOT weaken that better UX. Those guarantees are impossible to achieve with a locked `NodeType`. We have the ability to move the `Page` content entity type into its own submodule at a later time before the 1.0 release.

For the 1.0 release, Drupal Canvas WILL support `Node`s and any `NodeType` (and in principle every bundle of every content entity type) for _structured content_.

We lose some integrations users may expect, but we are now aware of them, not all of them would work reliably, and we can make product and engineering decisions off of this feedback.

This proposal allows us to start collecting feedback on a slice of Drupal Canvas before we get there, which should ultimately lead in a better product at the time of release.
