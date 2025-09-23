// @cspell:ignore vnode
import type { VNode } from 'preact';

interface AstroIslandElement extends HTMLElement {
  attributeChangedCallback(): void;
}

interface AstroIslandElementConstructor {
  new (...params: any[]): AstroIslandElement;
}

(() => {
  const AstroIsland = customElements.get(
    'astro-island',
  ) as AstroIslandElementConstructor;

  if (AstroIsland === undefined) {
    throw new Error();
  }

  const isVnode = (element: any): element is VNode => {
    // Expected keys are 'type', 'key' and 'props'.
    const expected = ['type', 'key', 'props'];
    const keys = Object.keys(element);
    return expected.filter((key) => keys.includes(key)).length === 3;
  };

  class CanvasIsland extends AstroIsland {
    static observedAttributes = ['props'];
    constructor() {
      super();
    }
    attributeChangedCallback() {
      // Add the ssr attribute back so we can re-hydrate when props change.
      this.setAttribute('ssr', '');
      // Astro hydration's preact renderer clears the innerHTML of the element
      // before rendering. During rendering preact sets a _children property on
      // the element that it uses for diffing the virtual-dom during rendering.
      // When the innerHTML has been removed, the presence of this property
      // means re-rendering doesn't work. The property is named _children in
      // development but in production the property name is minified. The type
      // of the property is a Preact VNode. We find this property by filtering
      // all properties of this element and then removing those that are VNodes.
      Object.entries(this)
        .filter(([key]) => key.startsWith('_'))
        .filter(([, value]) => isVnode(value))
        .forEach(([key]) => {
          delete this[key as keyof this];
        });
      super.attributeChangedCallback();
    }
  }
  customElements.define('canvas-island', CanvasIsland);
})();
