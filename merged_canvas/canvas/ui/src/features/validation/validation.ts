/**
 * Validates machine name for JS components.
 *
 * @param name - The name to validate.
 * @returns An error message if the name is invalid, or an empty string if it is valid.
 */
export const validateMachineNameClientSide = (name: string) => {
  const cleanedName = name.toLowerCase().replace(/\s+/g, '_');
  if (/^\d/.test(cleanedName)) {
    return 'Name cannot start with a number';
  }
  // @see Regex from config/schema/canvas.schema.yml#canvas.js_component.*.
  if (!/^[a-z]([a-zA-Z0-9_-]*[a-zA-Z0-9])*$/.test(cleanedName)) {
    return 'Special characters are not allowed. Name cannot start or end with a hyphen, underscore, or whitespace.';
  }
  return '';
};
