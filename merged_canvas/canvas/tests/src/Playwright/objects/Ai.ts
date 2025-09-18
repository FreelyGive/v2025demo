import type { Page } from '@playwright/test';

export class Ai {
  readonly page: Page;

  constructor({ page }: { page: Page }) {
    this.page = page;
  }

  async openPanel() {
    await this.page.getByRole('button', { name: 'Open AI Panel' }).click();
  }

  async submitQuery(query: string) {
    await this.page.getByRole('textbox', { name: 'Build me a' }).fill(query);
    await this.page
      .getByTestId('canvas-ai-panel')
      .getByRole('button')
      .nth(1)
      .click();
  }
}
