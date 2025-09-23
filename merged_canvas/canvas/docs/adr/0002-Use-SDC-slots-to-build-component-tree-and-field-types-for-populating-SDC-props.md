# 2. Use SDC slots to build component tree and field types for populating SDC props

Date: 2024-08-14

Issue: <https://www.drupal.org/project/canvas/issues/3461490>

## Status

Superseded by [ADR #6](../0006-One-field-row-per-component-instance.md.md).

## Context

To get the Drupal Canvas project off the ground, we must prioritize some things over others. Implementing all product requirements simultaneously is impractical and unrealistic.

After [the research phase](https://dri.es/evolving-drupal-layout-builder-to-an-canvas), work began on [two of the three lanes identified as next steps](https://www.drupal.org/about/core/blog/working-toward-an-canvas#:~:text=and%20use%20cases.-,Next%20steps,-We%20have%20identified).

This ADR captures the _back-end_ decisions made for the first lane, which was defined as:

> Creating a revamped user experience that is optimized for creating pages using components, as well as defining the layout for structured data.

In order to build a "revamped user experience", the server (Drupal's Drupal Canvas module) must provide information to the client (a React UI). The back-end architecture + code will _eventually_ need to be all the things the Drupal community expects: performant, scalable, flexible.  
But in this early phase, it is _unimportant_. **The thing that is important above all else, is achieving a user experience with a higher quality than anything else in the Drupal ecosystem.**

Ideally, all existing Drupal functionality continues to work, because that means:

- Drupal Canvas gets to start with an existing ecosystem, instead of having to start from zero
- prior _investments_ in functionality are not forfeited

Finally, by first building something constrained but with approximately the envisioned user experience, it becomes easier to expand the functionality to meet [all product requirements](https://docs.google.com/spreadsheets/d/1OpETAzprh6DWjpTsZG55LWgldWV_D8jNe9AM73jNaZo/edit#gid=1721130122): the user experience bar should not be lowered, while the functionality grows richer.

In other words: until this ADR is superseded, all back-end work is in service of meeting [Milestone 0.1.0: Drupal Canvas Demo](https://www.drupal.org/project/canvas/issues/3454094).


## Decision

After careful research, countless conversations and hence rooted in real-world experience, a handful of decisions were made that define the _current_ direction but not necessarily the final implementation:

1. The UX (client/UI/front-end) deals only with [Single-Directory Components](https://www.drupal.org/project/sdc) to start with.
2. Hence all back-end infrastructure is Single-Directory Components-centric. Other component types are not supported for now, and if the back end gains support for more component types, the front end will also need to evolve.
3. [Drupal 11 requires a database that has JSON support](https://www.drupal.org/node/3444548). Efficiently storing and retrieving is thought to be efficiently possible using [a JSON blob representing a tree of components](https://www.drupal.org/project/drupal/issues/3440578).
4. To store this component tree, not one but _two_ JSON blobs are stored:
   - one containing the `tree` of components (N components at the root, with optionally components in the slots of those components, and so on)
   - and one containing the `props` of components
   - This should allow for both symmetric (`tree` the same for all translations) and asymmetric translations (`tree` different per translation).
5. To populate SDCs' props, existing field types and widgets are reused. A lot of infrastructure is necessary for this: matching SDC's props' JSON schemas against Drupal field types as well as field instances
6. **These limitations are conscious and temporary. They will be removed. That means this ADR _will be superseded_.**


All these pieces need to have fairly strict validation (or at least assumption checking); this enables faster evolution in the future, when we are expanding its functionality.

[Details for the data model architecture are documented.](../data-model.md)

(Note: in working towards meeting that first lane, discovery is being realized towards the second lane. It is being captured at [[SPIKE] Comprehensive plan for integrating with SDC](https://www.drupal.org/project/canvas/issues/3462705).)


## Consequences

For now, supporting component types other than [Single-Directory Components](https://www.drupal.org/project/sdc) is out of scope. At a minimum, the following component-like building blocks will be integrated into Drupal Canvas at a later time, at which point this ADR will become obsolete:
1. blocks/block instances (to allow for a migration path from Layout Builder)
2. layout plugins (to allow for a migration path from Layout Builder)
3. paragraph types/paragraphs (to allow for a migration path from Paragraphs)

Participate in the discussion at [[META] Support component types other than SDC](https://www.drupal.org/project/canvas/issues/3454519), where we are preparing for these to be added in the future.

