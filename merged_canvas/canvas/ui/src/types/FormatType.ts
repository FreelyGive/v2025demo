export interface FormatType {
  format: string;
  editor?: string;
  editorSettings?: {
    toolbar: any[];
    plugins: string[];
    config: {
      [key: string]: any;
    };
    language: Record<string, any>;
  };
  [key: string]: any;
}
