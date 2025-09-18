/**
 * Converting from an HTML representation to a hyperscript (JSX) representation
 * requires mapping HTML attribute and slot semantics to JSX props semantics.
 * The details for what mapping is needed depends on the front-end framework and
 * components that are used. This basicPropsifyFactory() function returns a
 * propsify() implementation that's a reasonable starting point that could be
 * suitable for most apps. Apps for which this implementation isn't suitable can
 * implement their own propsify() function instead of using this factory.
 *
 * Usage:
 * ```
 * propsify = basicPropsifyFactory({ ... }); // See docs for parameters below.
 *
 * // See hyperscriptify() docs for details about options.propsify.
 * hyperscriptify( ..., { propsify });
 * ```
 *
 * @param mapKeys
 *   The https://lodash.com/docs/4.17.15#mapKeys function or a similar
 *   implementation, such as https://github.com/angus-c/just/tree/master/packages/object-map-keys
 *   (or some other implementation) if wanting to minimize the app bundle size.
 *
 * @param mapValues
 *   The https://lodash.com/docs/4.17.15#mapValues function or a similar
 *   implementation, such as https://github.com/angus-c/just/tree/master/packages/object-map-values
 *   (or some other implementation) if wanting to minimize the app bundle size.
 *
 * @param set
 *   The https://lodash.com/docs/4.17.15#set function or a similar
 *   implementation, such as https://github.com/angus-c/just/tree/master/packages/object-safe-set
 *   (or some other implementation) if wanting to minimize the app bundle size.
 *
 * @param camelCase
 *   The https://lodash.com/docs/4.17.15#camelCase function or a similar
 *   implementation, such as https://github.com/angus-c/just/tree/master/packages/string-camel-case
 *   (or some other implementation) if wanting to minimize the app bundle size.
 *
 * @param htmlElementAttributeToPropMap
 *   (optional) An object that maps HTML attribute names to the hyperscript
 *   prop name. This map is used for HTML elements that do not correspond to
 *   hyperscript components. Some frameworks, like React, are strict in
 *   requiring that prop names for intrinsic HTML elements are the same as the
 *   element's DOM property name. When using such frameworks, the object
 *   exported by standard/reactHtmlAttributeToPropertyMap.js, or one similar to it,
 *   should be provided as this parameter. Other frameworks, like Preact, allow
 *   prop names for intrinsic HTML elements to be either the DOM property name
 *   or the HTML attribute name. For those frameworks, this parameter may be
 *   omitted.
 *
 * @param componentAttributeToPropMap
 *   (optional) Similar to htmlElementAttributeToPropMap, but this map is used
 *   for elements that do correspond to hyperscript components. Component
 *   attributes not in this map are mapped to the property by calling
 *   camelCase().
 *
 * @returns {function(*, *, *): *}
 *   The function to provide to hyperscriptify() for options.propsify.
 */
export default function basicPropsifyFactory({
  mapKeys,
  mapValues,
  set,
  camelCase,
  htmlElementAttributeToPropMap = {},
  componentAttributeToPropMap = {},
}) {
  return function (attributes, slots, context) {
    let props = { ...attributes };

    // For components (not intrinsic HTML elements)...
    if (context.component) {
      // HTML attribute names are case insensitive, normalized to lowercase, and
      // for custom elements, multi-word attribute names are by convention
      // kebab-cased. JSX prop names are case-sensitive and by convention,
      // camelCased.
      props = mapKeys(
        props,
        (value, key) => componentAttributeToPropMap[key] || camelCase(key),
      );

      // HTML attribute values are strings. JSX prop values can be other data
      // types, such as objects or arrays. Therefore, if the attribute value is
      // a JSON string, parse it.
      props = mapValues(props, (value) => {
        try {
          return JSON.parse(value);
        } catch {
          return value;
        }
      });

      // HTML distinguishes between attributes and slots. In JSX, prop values
      // can be their own elements/components, so simply assign the slots into
      // props. The set() function allows slot names to be deep paths into
      // props. For example, a slot name of "a.b.c" would assign the slot value
      // into props.a.b.c.
      for (const slotName in slots) {
        set(props, slotName, slots[slotName]);
      }
    }
    // For intrinsic HTML elements...
    else {
      props = mapKeys(
        props,
        (value, key) => htmlElementAttributeToPropMap[key] || key,
      );
    }

    return props;
  };
}
