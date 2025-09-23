export interface TransliterateOptions {
  unknown?: string;
  replace?: Record<string, string>;
  ignore?: string[];
}

/**
 * Transliterates non-Latin characters to Latin characters.
 *
 * @param str - The input string to transliterate
 * @param options - Configuration options for transliteration
 * @returns The transliterated string
 */
export type transliterate = (
  str: string,
  options?: TransliterateOptions,
) => string;
