// Save reference to original trigger method.
const originalTrigger = jQuery.fn.trigger;

// Override jQuery's trigger method.
jQuery.fn.trigger = function(eventName, data) {
  // Events dispatched with jQuery.trigger() do not trigger the corresponding on*
  // callbacks in elements rendered by React. This array is of event types where
  // the React element on* listeners should be aware of jQuery.trigger()
  // dispatches.
  const eventsToVanillaDispatch = ['change'];
  if (eventsToVanillaDispatch.includes(eventName)) {
    const isReactElement = Object.keys(this[0]).some((key) => key.startsWith('__react'));
    if (isReactElement) {
      // Use CustomEvent so we can use detail to indicate that this originated
      // from jQuery.trigger().
      const proxiedEvent = new CustomEvent(eventName, {bubbles: true, detail: {jqueryProxy: true}})
      this[0].dispatchEvent(proxiedEvent)
    }
  }

  return originalTrigger.call(this, eventName, data);
};
