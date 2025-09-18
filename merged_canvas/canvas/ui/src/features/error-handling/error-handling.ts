import DOMPurify from 'dompurify';

// Extract and sanitize human-readable error message from API error response.
export const extractErrorMessageFromApiResponse = (error: any): string => {
  const fallbackMessage =
    'Error occurred, see browser console for more details.';
  const errors = error?.data?.errors;
  if (!errors || !errors.length) return fallbackMessage;
  // Handle simple string array errors
  if (typeof errors[0] === 'string') {
    return errors.length > 1 ? errors.join('\n') : errors[0];
  }
  // Get error messages, and sort them where details with a pointer (ex. machineName) come first.
  const errorMessages = [...errors]
    .sort((a, b) =>
      a.source?.pointer && !b.source?.pointer
        ? -1
        : !a.source?.pointer && b.source?.pointer
          ? 1
          : 0,
    )
    .map((err) => err.detail)
    .filter(Boolean);
  const errorString = errorMessages.length
    ? errorMessages.length > 1
      ? errorMessages.join(`\n`)
      : errorMessages[0]
    : fallbackMessage;
  return DOMPurify.sanitize(errorString);
};
