import { Provider } from 'react-redux';

import { makeStore } from '@/app/store';

describe('<Preview /> for code editor', () => {
  let previewScript;

  before(() => {
    // Load the preview script content.
    cy.readFile('lib/code-editor-preview.js').then((content) => {
      previewScript = content;
    });
  });

  // @todo: Replace this test with an end-to-end test.
  // The test fails on CI, probably because the iframe manipulation
  // is flaky. If we use an end-to-end test, we don't need to inline the otherwise
  // external preview script.
  // eslint-disable-next-line mocha/no-pending-tests
  it.skip('renders simple JS and CSS in the preview iframe', () => {
    // Mock JavaScript and CSS in the Redux store
    const store = makeStore({
      codeEditor: {
        sourceCodeJs: `
        export default function MyComponent({ title, initialCount, isVisible, additionalContent }) {
          if (!isVisible) {
            return null;
          }
          return <div id="hello">{ title } { initialCount + 1 } { additionalContent }</div>;
        }
      `,
        sourceCodeCss: `
        #hello {
          color: blue;
          font-size: 24px;
        }
      `,
        props: [
          {
            name: 'Title',
            type: 'string',
            example: 'Hello World',
          },
          {
            name: 'Initial count',
            type: 'number',
            example: 1,
          },
          {
            name: 'Is visible',
            type: 'boolean',
            example: true,
          },
        ],
        slots: [
          {
            name: 'Additional content',
            example: '<span>!</span>',
          },
        ],
      },
    });
    cy.mount(<Provider store={store}>{/*<Preview />*/}</Provider>);

    // Compiling the JS code in the preview is debounced to one second.
    // When that happens, the iframe is re-rendered. Waiting here to inject
    // the preview script. It's normally added in the iframe's markup
    // in a <script> tag with `src`, but since this is a component test, that
    // won't work.
    cy.wait(2000); // eslint-disable-line cypress/no-unnecessary-waiting
    cy.getIframe('[data-canvas-iframe="canvas-code-editor-preview"]').then(
      (doc) => {
        const script = doc.createElement('script');
        script.type = 'module';
        script.textContent = previewScript;
        doc.head.appendChild(script);
      },
    );

    cy.waitForElementInIframe(
      '#hello',
      '[data-canvas-iframe="canvas-code-editor-preview"]',
    );
    cy.testInIframe(
      '#hello',
      (el) => {
        const computedStyle = window.getComputedStyle(el);
        expect(el.innerHTML).to.equal('Hello World 2 <span>!</span>');
        expect(computedStyle.fontSize).to.equal('24px');
        expect(computedStyle.color).to.equal('rgb(0, 0, 255)');
      },
      '[data-canvas-iframe="canvas-code-editor-preview"]',
    );
  });
});
