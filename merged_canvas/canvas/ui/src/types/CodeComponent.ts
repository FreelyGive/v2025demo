import type derivedPropTypes from '@/features/code-editor/component-data/derivedPropTypes';

export interface DataFetch {
  id: string;
  data: any;
  error: boolean;
}

export interface CodeComponent {
  machineName: string;
  name: string;
  status: boolean;
  props: CodeComponentProp[];
  required: string[];
  slots: any[];
  sourceCodeJs: string;
  sourceCodeCss: string;
  compiledJs: string;
  compiledCss: string;
  importedJsComponents: string[];
  dataFetches: {
    [key: string]: DataFetch;
  };
  dataDependencies: DataDependencies;
}

export interface DataDependencies {
  drupalSettings?: Array<string>;
  urls?: Array<string>;
}

export interface CodeComponentSerialized
  extends Omit<CodeComponent, 'props' | 'slots'> {
  props: Record<string, CodeComponentPropSerialized>;
  slots: Record<string, CodeComponentSlotSerialized>;
  dataDependencies: DataDependencies;
}

export interface CodeComponentProp {
  id: string;
  name: string;
  type: 'string' | 'integer' | 'number' | 'boolean' | 'object';
  enum?: string[];
  example?:
    | string
    | boolean
    | CodeComponentPropImageExample
    | CodeComponentPropVideoExample;
  $ref?: string;
  format?: string;
  derivedType: (typeof derivedPropTypes)[number]['type'] | null;
  contentMediaType?: string;
  'x-formatting-context'?: string;
}

export interface CodeComponentPropImageExample {
  src: string;
  width: number;
  height: number;
  alt: string;
}

export interface CodeComponentPropSerialized {
  title: string;
  type: 'string' | 'integer' | 'number' | 'boolean' | 'object';
  enum?: (string | number)[];
  examples?: (
    | string
    | number
    | boolean
    | CodeComponentPropImageExample
    | CodeComponentPropVideoExample
  )[];
  $ref?: string;
  format?: string;
  contentMediaType?: string;
  'x-formatting-context'?: string;
}

export interface CodeComponentSlot {
  id: string;
  name: string;
  example?: string;
}

export interface CodeComponentSlotSerialized {
  title: string;
  examples?: string[];
}

export type CodeComponentPropPreviewValue = string | number | boolean;

export interface AssetLibrary {
  id: string;
  label: string;
  css: {
    original: string;
    compiled: string;
  };
  js: {
    original: string;
    compiled: string;
  };
}

export interface CodeComponentPropVideoExample {
  src: string;
  poster: string;
}
