export interface Metadata {
  name: string;
  machineName: string;
  status: boolean;
  required: string[];
  props: {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    properties: Record<string, any>;
  };
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  slots: Record<string, any>;
  importedJsComponents: string[];
}
