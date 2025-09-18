export type RuleTypeHandle = 'utm_parameters' | 'date' | 'referrer';

export interface SegmentRule {
  id: string;
  negate: boolean;
  all: boolean;
  parameters?: Array<{
    key: string;
    value: string;
    matching: string;
  }>;
}

export interface Segment {
  id: string;
  label: string;
  description?: string;
  status: boolean;
  weight: number;
  rules?: Record<RuleTypeHandle, SegmentRule>;
}
