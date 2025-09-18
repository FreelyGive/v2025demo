export interface Component {
  machineName: string;
  name: string;
  status: boolean;
  framework?: 'react' | 'vue' | 'unknown';
  required?: string[];
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  props?: Record<string, any>;
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  slots?: Record<string, any>;
  sourceCodeJs?: string;
  compiledJs?: string;
  sourceCodeCss?: string;
  compiledCss?: string;
  importedJsComponents: string[];
  dataDependencies: string[];
}

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
