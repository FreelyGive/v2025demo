# Drupal Canvas page preview

In the rest of this document, `Drupal Canvas` will be written as `Canvas`.

## The preview

The page preview in the Canvas React app consists of two React components layered on top of each other (using CSS absolute positioning).

At the bottom is the `<iframe>`, which displays the current page being edited. Overlaid on top of that is the overlay UI, allowing users to interact with the preview (e.g., selecting components, drag-and-drop, right-click actions).

## Structure
```
Preview.tsx
├─ Viewport.tsx // 1 or more e.g. desktop and mobile
   ├─ IframeSwapper.tsx
      ├─ <iframe[data-canvas-iframe="A">
      ├─ <iframe[data-canvas-iframe="B"]>
   ├─ ViewportOverlay.tsx
      ├─ regionOverlay.tsx
         ├─ ComponentOverlay.tsx // a nested structure of Components and Slots matching the layout/structure of the page.
            ├─ SlotOverlay.tsx
```


## Viewports
The Canvas UI is designed to support multiple "viewports", each showing a preview of the page currently being edited.

Whenever anything on the page changes (e.g., props data, layout), a request is sent to the backend, which returns the updated page HTML document as a string.

That HTML is then passed as `frameSrcDoc` to each `<Viewport>`, which renders an `<iframe>` using an `srcdoc` attribute.

The `<iframe>` element is rendered by a React component called `<IFrameSwapper>`.

### Synchronizing interactions

A Redux slice called `uiSlice` is used to contain a number of state related variables that allow synchronizing the UI display across not only the multiple `<Viewport>` components, but also the "Layers" view in the left sidebar. So when hovering or selecting a component in the preview, the same component will also display as hovered in the other viewports and the "Layers" view. In turn hovering or selecting a component in the "Layers" view will also show in the preview viewports.

In [`uiSlice.ts`](/web/modules/canvas/ui/src/features/ui/uiSlice.ts) see the properties `dragging`, `selectedComponent`, `hoveredComponent` and  `targetSlot`.

## The `<iframe>` and the `<IFrameSwapper>`
The `<IFrameSwapper>` is so named because it renders two `<iframe>` elements and swaps between them. This approach allows loading the new page into a hidden `<iframe>` and swapping it in only once it has finished loading. This prevents [flickering](https://www.drupal.org/project/canvas/issues/3469677), layout shifts, and/or any [FOUC](https://en.wikipedia.org/wiki/Flash_of_unstyled_content) issues.

This 'swapping' implementation detail is intended to be transparent to the wider app outside the `<IFrameSwapper>` because the currently active `<iframe>` is exposed using a [customized dynamic ref](https://react.dev/reference/react/useImperativeHandle).

## The UI
Inside `<Viewport>`, another component is rendered alongside each `<IFrameSwapper>` called the `<ViewportOverlay>`. The `<ViewportOverlay>` renders an [interactive UI layer](https://www.drupal.org/project/canvas/issues/3475759) over the top of the `<iframe>`.

The UI layer...
1. is dynamically positioned directly over each `<iframe>`;
2. blocks interaction with the document inside the `<iframe>` elements;
3. has a mirrored version of each component and slot in the page so that each can be outlined/annotated without injecting markup into the `<iframe>` document;
4. is responsible for user interactions with the components/slots;
5. is portalled and rendered above the `<EditorFrame>` element.

Let's look at the above points in turn:

### 1. Dynamic positioning
The UI layer responds to [element resizing](https://developer.mozilla.org/en-US/docs/Web/API/ResizeObserver), [browser resizing](https://developer.mozilla.org/en-US/docs/Web/API/Window/resize_event), and [DOM mutations](https://developer.mozilla.org/en-US/docs/Web/API/MutationObserver) to ensure that each `<ComponentOverlay>` is precisely overlaid onto its corresponding component inside the `<iframe>`.

### 2. Blocking interaction
Allowing users to interact with the page displayed in the `<iframe>` is problematic for several reasons. The biggest issue initially faced was capturing various mouse and keyboard events happening inside the `<iframe>` and passing them up to the parent window to be handled by React. For example, if a user focuses on an element inside the `<iframe>` and presses a keyboard shortcut, the `keydown` event is fired inside the `<iframe>`. However, because our event handler is in the React app of the parent window, the keyboard shortcut wouldn't work!

Furthermore, we encountered [numerous](https://www.drupal.org/project/canvas/issues/3458535) [browser](https://www.drupal.org/project/canvas/issues/3466063) [quirks](https://www.drupal.org/project/canvas/issues/3475749) related to pinch, mousemove, and mousewheel events when the mouse cursor moves over an `<iframe>`.

### 3. Mirror universe
For each component and slot, a transparent element is rendered and positioned (see 1. Dynamic positioning) over the top of the corresponding component inside the `<iframe>`. This allows the UI to render borders around components and slots, display the name of the component, and show interactive buttons (e.g., "Add component") without injecting markup into the `<iframe>`, which may cause styling or layout issues.

### 4. User interactions
The overlaid components handle interactions like hover, click, drag, and right-click. This means we don't have to inject event listeners into the `<iframe>`. For instance, showing a border around a component when hovering over it becomes trivial, as we just add a class to the element and apply a border with CSS!

### 5. Portals
It was a [requirement](https://www.drupal.org/project/canvas/issues/3469672) that zooming the Editor Frame should not also scale the Canvas UI. If a user zooms way out, we don't want the component's name in the UI to become illegibly small! To avoid these scaling issues, the `<ViewportOverlay>` uses a React portal to render into a `<div id="canvasPreviewOverlay">` that exists above the element that scales when a user zooms the preview.


## Data Model and HTML Mapping

When the server renders HTML, each region, component, and slot displayed in the preview is annotated with HTML comments. This approach (as opposed to wrapping with HTML elements) ensures that the DOM structure remains unchanged, so CSS selectors and other functionalities are not affected by the introduction of rendered markup from Canvas.

Whenever a new version of the HTML is received from the server, the `<Viewport>` component generates a map. This map links each component's UUID and slot ID to the corresponding HTML element(s) in the preview. This is accomplished using the `useComponentHtmlMap` hook. This hook updates a React Context, which stores the map and is utilized by the overlay React components.

## Drag-and-drop
While this implementation is designed to prevent users from interacting with the content in the `<iframe>` one aspect that still does affect elements in the `<iframe>` is showing the state of DOM elements when they are dragged.

On starting a drag operation (dragging an existing item in the preview overlay) the corresponding DOM element(s) inside the `<iframe>` are assigned a class that causes them to fade out to indicate to the user what they are dragging.

Handling the drop operation in the overlay first applies a pending class to the DOM element(s) that were dragged but then immediately requests a fresh render of the preview from the server.

## The future
It may well become necessary to allow users to interact with the page inside the `<iframe>` in the future. One approach to this might be to introduce a toggleable state that allows a user to switch between a "layout mode" for editing the layout and an "interactive mode" that will allow them to click inside the `<iframe>`.

# Conclusion
Hopefully this approach to handling the quirks and challenges of previewing a page in an `<iframe>` provides a robust intuitive developer experience. This documentation aims to clarify the core concepts and functionality of the preview system, empowering you to make the most of its features. If you have any questions or feedback, feel free to reach out to our team!
