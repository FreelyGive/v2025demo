export interface ContentStub {
  title: string;
  path: string;
  internalPath: string;
  id: number | string;
  status: boolean;
  autoSaveLabel: string | null;
  autoSavePath: string;
  links: {
    'delete-form'?: string;
    'edit-form'?: string;
    'https://drupal.org/project/canvas#link-rel-duplicate'?: string;
    'https://drupal.org/project/canvas#link-rel-set-as-homepage'?: string;
  };
}
