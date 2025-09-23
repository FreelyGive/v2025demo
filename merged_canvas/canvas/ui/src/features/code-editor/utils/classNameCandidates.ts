/**
 * @file
 * Utility functions for managing class name candidates in a JS comment.
 *
 * The JS comment is used as an index for Tailwind CSS class name candidates
 * extracted from each code component. This allows us to build the global CSS
 * using Tailwind CSS.
 *
 * @example
 *
 * ```js
 * // @classNameCandidates {"card": ["bg-primary", "text-white"], "button": ["font-bold", "text-white"]}
 * ```
 */

const COMMENT = '// @classNameCandidates ' as const;

/**
 * Finds the comment with the class name candidates in the source string.
 */
export function findComment(source: string): {
  parsedData: Record<string, string[]>;
  commentStart: number;
  commentEnd: number;
} | null {
  const commentStart = source.indexOf(COMMENT);
  if (commentStart === -1) {
    return null;
  }
  const commentEnd = source.indexOf('\n', commentStart);
  const json = source.slice(commentStart + COMMENT.length, commentEnd);
  try {
    const parsedData = JSON.parse(json) as Record<string, string[]>;
    return {
      parsedData,
      commentStart,
      commentEnd,
    };
  } catch (error) {
    return null;
  }
}

/**
 * Gets unique class name candidates parsed from a source string.
 *
 * @param source - The source string to parse.
 * @returns An array of unique class name candidates.
 */
export function getClassNameCandidatesFromComment(source: string): string[] {
  const result = findComment(source);
  if (!result) {
    return [];
  }
  const { parsedData } = result;
  return [...new Set(Object.values(parsedData).flat())];
}

/**
 * Upserts class name candidates for a component in the source string.
 *
 * @param source - The source string to update.
 * @param componentName - The name of the component to update or insert.
 * @param candidates - The class name candidates for the component.
 */
export function upsertClassNameCandidatesInComment(
  source: string,
  componentName: string,
  candidates: string[],
): {
  nextSource: string;
  nextClassNameCandidates: string[];
} {
  const initialContent =
    COMMENT +
    JSON.stringify({
      [componentName]: candidates,
    }) +
    '\n' +
    source;

  // If the source is empty, return the initial content.
  if (source === '') {
    return {
      nextSource: initialContent,
      nextClassNameCandidates: candidates,
    };
  }

  // If the comment is not found, return the initial content added at the
  // beginning of the source.
  const result = findComment(source);
  if (!result) {
    return {
      nextSource: initialContent,
      nextClassNameCandidates: candidates,
    };
  }

  // If the comment is found, update the comment with the new candidates.
  const { parsedData, commentStart, commentEnd } = result;
  parsedData[componentName] = candidates;
  // Replace the old comment with the new one
  return {
    nextSource:
      source.slice(0, commentStart) +
      COMMENT +
      JSON.stringify(parsedData) +
      source.slice(commentEnd),
    nextClassNameCandidates: [...new Set(Object.values(parsedData).flat())],
  };
}

/**
 * Deletes class name candidates for a component in the source string.
 *
 * @param source - The source string to update.
 * @param componentName - The name of the component to delete.
 */
export function deleteClassNameCandidatesInComment(
  source: string,
  componentName: string,
) {
  const result = findComment(source);
  if (!result) {
    return source;
  }
  const { parsedData, commentStart, commentEnd } = result;
  if (!parsedData[componentName]) {
    return source;
  }
  delete parsedData[componentName];
  return (
    source.slice(0, commentStart) +
    COMMENT +
    JSON.stringify(parsedData) +
    source.slice(commentEnd)
  );
}
