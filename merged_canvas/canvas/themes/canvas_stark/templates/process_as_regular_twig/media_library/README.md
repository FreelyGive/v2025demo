## What is here
These are copies of the system default Twig templates, but named so the Media Library
UI will use them instead of being rendered by React.

## Why we need this
Semi-coupled templates are converted into standard HTML once added to the page, but the Media Library UI
is rendered in a dialog, and the dialog needs the standard HTML before it is on the page so it can do things like copy submit buttons to the buttonpane. There is probably a more elegant solution but this works well and we can explore other solutions if there are use cases beyond media library that require this.

There is probably a more elegant solution but this works well and we can explore other solutions if there are use cases beyond media library that require this.

See https://www.drupal.org/project/canvas/issues/3478287#comment-15828425
