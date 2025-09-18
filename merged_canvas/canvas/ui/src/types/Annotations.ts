export interface RegionInfo {
  elements: HTMLElement[];
  regionId: string;
}

export interface ComponentInfo {
  elements: HTMLElement[];
  componentUuid: string;
}

export interface SlotInfo {
  element: HTMLElement;
  componentUuid: string;
  slotName: string;
  stackDirection: StackDirection;
}

export type StackDirection =
  | 'vertical'
  | 'vertical-grid'
  | 'vertical-flex'
  | 'horizontal-flex'
  | 'horizontal-grid';

export type BoundingRect = {
  top: number;
  left: number;
  width: number;
  height: number;
};

export type RegionsMap = Record<string, RegionInfo>;
export type ComponentsMap = Record<string, ComponentInfo>;
export type SlotsMap = Record<string, SlotInfo>;
