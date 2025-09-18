# 5. Keep the front end simple

Date: 2024-11-26

## Status

Accepted

## Context

The React front end is being built on top of a Drupal back end. While Drupal
models most data storage as content or configuration entities, the front end
does not need to share all the same concepts.

## Decision

The front end will not need any knowledge of Drupal's entity system or other
Drupal-specific concepts or data models. The back end will provide the front
end with simple APIs that abstract away the complexity of Drupal's internals.

## Consequences

When modelling a full page layout, the back end will be responsible for
combining everything from the page template, regions, and blocks to the
individual entity or entities into a single tree of components, associated
data model, and HTML preview that the front end can consume and present to the
user. When the front end sends an updated tree and model back, the back end will
be responsible for decomposing these into the relevant Drupal data models.
