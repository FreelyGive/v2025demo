export interface Result {
  itemName: string;
  success: boolean;
  details?: { heading?: string; content: string }[];
}
