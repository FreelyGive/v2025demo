import type {
  ComponentModels,
  LayoutNode,
} from '@/features/layout/layoutModelSlice';
import type { ComponentsList } from '@/types/Component';

export interface PropsValues {
  [key: string]: any;
}

export interface InputMessage {
  type: 'error' | 'warning' | 'info';
  message: string;
}

export interface InputUIData {
  selectedComponent: string;
  components: ComponentsList | undefined;
  selectedComponentType: string;
  layout: Array<LayoutNode>;
  model?: ComponentModels;
  node?: LayoutNode | null;
  version: string;
}
