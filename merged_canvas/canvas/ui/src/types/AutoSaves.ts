export type AutoSavesHash = Record<
  string,
  {
    autoSaveStartingPoint: string;
    hash: string;
  }
>;

export type AutoSavesHashRecord = Record<string, AutoSavesHash>;
