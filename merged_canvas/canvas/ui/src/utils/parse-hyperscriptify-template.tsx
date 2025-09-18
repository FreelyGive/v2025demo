/**
 * Parse the hyperscriptify template from an HTML string.
 *
 * @param data - The HTML string.
 * @returns The hyperscriptify template or null if not found.
 */
export default function parseHyperscriptifyTemplate(
  data: string,
): DocumentFragment | null {
  const document = new DOMParser().parseFromString(data, 'text/html');

  return (
    document.querySelector(
      'template[data-hyperscriptify]',
    ) as HTMLTemplateElement
  )?.content;
}
