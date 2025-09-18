import type React from 'react';
import type ReactDom from 'react-dom';
// eslint-disable-next-line @typescript-eslint/no-restricted-imports
import type * as ReactRedux from 'react-redux';
import type * as ReduxToolkit from '@reduxjs/toolkit';
import type { DrupalSettings } from '@/types/DrupalSettings';
import type { transliterate as TransliterateType } from '@/types/transliterate';

interface CKEditor5Types {
  editorClassic: {
    ClassicEditor: any;
  };
  [key: string]: any;
}

declare global {
  interface Window {
    drupalSettings: DrupalSettings;
    transliterate: TransliterateType;
    React: typeof React;
    ReactDom: typeof ReactDom;
    Redux: typeof ReactRedux;
    ReduxToolkit: typeof ReduxToolkit;
    Drupal: {
      attachBehaviors: (element: HTMLElement) => void;
      CKEditor5Instances: Map;
    };
    CKEditor5: CKEditor5Types;
    jQuery: any;
  }
}
