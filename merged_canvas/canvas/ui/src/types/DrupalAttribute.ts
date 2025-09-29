// Represents a single HTML attribute value.
// @see https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Template%21Attribute.php/class/Attribute/11.x
export type Attribute =
  | string
  | number
  | boolean
  | string[]
  | Function
  | null
  | object;

// Represents a collection of HTML attributes.
export interface Attributes {
  [key: string]: Attribute;
}
