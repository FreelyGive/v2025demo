/**
 * Remove newlines and excess whitespace from a string.
 */
export function onlyVisibleChars(inputString) {
  return inputString.replace(/^(?:&nbsp;|\s)+|(?:&nbsp;|\s)+$/gi, '').trim();
}
