export interface ConfigAction {
  name: string;
  input: Record<string, string>;
}

export interface StagedConfig {
  data: {
    id: string;
    label: string;
    target: string;
    actions: ConfigAction[];
  };
  autoSaves: '';
}
