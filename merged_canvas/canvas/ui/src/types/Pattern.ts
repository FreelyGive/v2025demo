import type { LayoutModelPiece } from '@/features/layout/layoutModelSlice';

export interface Pattern {
  layoutModel: LayoutModelPiece;
  name: string;
  id: string;
  default_markup: string;
  css: string;
  js_header: string;
  js_footer: string;
}

// Type for the API response, an object keyed by pattern ID
export interface PatternsList {
  [key: string]: Pattern;
}
