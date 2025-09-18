import type { PendingChange } from '@/services/pendingChangesApi';

export enum FallbackColor {
  SKY = 'sky',
  MINT = 'mint',
  LIME = 'lime',
  YELLOW = 'yellow',
  AMBER = 'amber',
  ORANGE = 'orange',
  BRONZE = 'bronze',
  JADE = 'jade',
  CYAN = 'cyan',
  INDIGO = 'indigo',
  IRIS = 'iris',
  VIOLET = 'violet',
  PINK = 'pink',
  RUBY = 'ruby',
}

export type UnpublishedChange = PendingChange & {
  pointer: string; // Unique identifier for the change
};

export type UnpublishedChangeGroups = Record<string, UnpublishedChange[]>;
