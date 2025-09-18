import type { DrupalSettings } from '../../src/types/DrupalSettings';

declare global {
  interface Window {
    drupalSettings: DrupalSettings;
  }
}
