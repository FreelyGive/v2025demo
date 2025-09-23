import { describe, expect, it } from 'vitest';

import { transformCss } from './transform-css';

describe('transformCss', () => {
  it('should transpile valid CSS', async () => {
    const inputCss = `
      .parent {
        .child {
          color: red;
        }
      }
    `;
    const outputCss = await transformCss(inputCss);
    expect(outputCss).toBe('.parent .child{color:red}');
  });

  it('should handle media queries correctly', async () => {
    const inputCss = `
      @media (max-width: 600px) {
        .class {
          color: blue;
        }
      }
    `;
    const outputCss = await transformCss(inputCss);
    expect(outputCss).toBe('@media (max-width:600px){.class{color:#00f}}');
  });
});
