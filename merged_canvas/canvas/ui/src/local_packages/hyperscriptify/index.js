/**
 * This function converts a DOM tree (what a browser parses HTML into) into a
 * hyperscript element tree (what JSX is compiled into). For example, given the
 * following HTML existing somewhere on a web page:
 * ```
 * <template id="example">
 *   <my-component>
 *     <span>hello world</span>
 *   </my-component>
 *   <div>goodbye</div>
 * </template>
 * ```
 *
 * Then the following code:
 * ```
 * import { h, Fragment } from 'preact'; // Or any JSX-supporting framework.
 * ...
 * const domFragment = document.getElementById('example').content;
 * const components = {
 *   'my-component': MyComponent
 * }
 * const App = hyperscriptify(domFragment, h, Fragment, components)
 * ```
 *
 * Would be equivalent to the following JSX code:
 * ```
 * const App =
 *   <>
 *     <MyComponent>
 *       <span>hello world</span>
 *     </MyComponent>
 *     <div>goodbye</div>
 *   </>
 * ```
 *
 * A use-case for this is for when you want to use React (or other framework)
 * components within a web application that's partially rendered in a
 * non-JavaScript server-side language.
 *
 * @param domElementOrFragment
 *   A DOM Element or DocumentFragment.
 *
 * @param h
 *   A hyperscript function (e.g., Preact's h() function, React's
 *   createElement() function, or the equivalent from the desired framework).
 *   This function's signature must be (tagNameOrComponent, props, ...children).
 *
 * @param Fragment
 *   The component to use for creating a fragment. I.e., the component that a
 *   JSX compiler would compile <>...</> to. See
 *   https://react.dev/reference/react/Fragment for more details. This is used
 *   when the passed in domElementOrFragment is a DocumentFragment.
 *
 * @param components
 *   An object that maps element tag names to components. For example, if this
 *   object is { 'my-component-x': MyComponentX, 'my-component-y': MyComponentY },
 *   then if domElementOrFragment is a <my-component-x> element, this function
 *   will return h(MyComponentX, ...). Elements with tag names that aren't in
 *   the components object are passed to h() as strings. For example, if 'div'
 *   isn't one of the keys in components, then for <div> elements passed to this
 *   function, this function returns h('div', ...).
 *
 * @param options
 *   An object containing options for controlling this function's behavior.
 *   The only currently supported option is:
 *   - propsify: A function for customizing how the domElementOrFragment's
 *     attributes and slots (child elements with a slot attribute) are converted
 *     to the props object that gets passed to h().
 *
 * @returns {*}
 *   The result of calling h() with the info collected from domElementOrFragment.
 */
export default function hyperscriptify(
  domElementOrFragment,
  h,
  Fragment,
  components,
  options = {},
) {
  // Remove the prefix id.
  // Collect basic info about the element or fragment.
  let element, tagName, component, attributes, childNodes;
  switch (domElementOrFragment.nodeType) {
    case Node.ELEMENT_NODE:
      element = domElementOrFragment;
      tagName = element.nodeName.toLowerCase();
      // Strip empty text nodes from children of table related elements, to
      // prevent a render warning from React.
      if (['table', 'thead', 'tr', 'tbody'].includes(tagName)) {
        element.childNodes.forEach((node) => {
          if (node.nodeType === 3) {
            if (node.nodeValue.replace(/^\s+|\s+$/g, '').length === 0) {
              element.removeChild(node);
            }
          }
        });
      }
      component = components[tagName];
      attributes = Object.fromEntries(
        element
          .getAttributeNames()
          .map((name) => [name, element.getAttribute(name)]),
      );
      childNodes = element.childNodes;
      if (component) {
        element.setAttribute('data-drupal-scriptified', true);
      }
      break;
    case Node.DOCUMENT_FRAGMENT_NODE:
      component = Fragment;
      attributes = {};
      childNodes = domElementOrFragment.childNodes;
      break;
    default:
      return;
  }

  // Collect the element's or fragment's slots and children.
  const slots = {};
  const children = [];
  childNodes.forEach(function (childNode) {
    let child;
    switch (childNode.nodeType) {
      case Node.TEXT_NODE:
        child = childNode.nodeValue;
        break;
      case Node.ELEMENT_NODE:
        const slot = childNode.getAttribute('slot');
        child = hyperscriptify(childNode, h, Fragment, components, options);
        if (element && component && slot) {
          slots[slot] = child;
          child = null;
        }
        break;
    }
    if (child) {
      children.push(child);
    }
  });

  // For elements that have a slot attribute, and are child elements of a
  // component, this function uses that slot attribute to distribute that slot
  // content to the parent component's props (see the code above). Therefore,
  // don't also retain the slot attribute, since that could conflict with the
  // component's props expectations or trigger additional slot distribution by
  // the browser.
  if (
    attributes.slot &&
    components[element.parentNode.nodeName.toLowerCase()]
  ) {
    delete attributes.slot;
  }

  // Create a props object from the attributes and slots.
  const context = { tagName, component, element };
  const props =
    element && options.propsify
      ? options.propsify(attributes, slots, context)
      : { ...attributes, ...slots };

  return h(component || tagName, props, ...children);
}
