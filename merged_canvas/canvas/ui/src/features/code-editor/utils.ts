import { camelCase, isEqual } from 'lodash';
import { v4 as uuidv4 } from 'uuid';

import derivedPropTypes from '@/features/code-editor/component-data/derivedPropTypes';
import { CONFIG_EXAMPLE_URLS } from '@/features/code-editor/component-data/forms/FormPropTypeVideo';
import { getCanvasModuleBaseUrl } from '@/utils/drupal-globals';

import type { File } from '@babel/types';
import type {
  CodeComponent,
  CodeComponentProp,
  CodeComponentPropImageExample,
  CodeComponentPropPreviewValue,
  CodeComponentPropSerialized,
  CodeComponentPropVideoExample,
  CodeComponentSerialized,
  CodeComponentSlot,
  CodeComponentSlotSerialized,
  DataDependencies,
} from '@/types/CodeComponent';

export function getPropMachineName(name: string) {
  return camelCase(name);
}

/**
 * Parses a prop value for the code editor preview.
 *
 * @see ui/src/features/code-editor/Preview.tsx
 *
 * @param prop - The prop to parse.
 * @returns The parsed prop value.
 */
export function parsePropValueForPreview(
  prop: CodeComponentProp,
): CodeComponentPropPreviewValue {
  switch (prop.type) {
    case 'integer':
      return Number(prop.example);
    case 'number':
      return Number(prop.example);
    case 'boolean':
      return String(prop.example) === 'true';
    default:
      return prop.example as string;
  }
}

/**
 * Returns prop values for the code editor preview.
 *
 * @see ui/src/features/code-editor/Preview.tsx
 *
 * @param props - The props to get the values for.
 * @returns The prop values.
 */
export function getPropValuesForPreview(
  props: CodeComponentProp[],
): Record<string, CodeComponentPropPreviewValue> {
  let propValues = {} as Record<string, CodeComponentPropPreviewValue>;
  props
    .filter((prop) => prop.name)
    .forEach((prop) => {
      propValues[getPropMachineName(prop.name)] =
        parsePropValueForPreview(prop);
    });
  return propValues;
}

/**
 * Returns slot names for the code editor preview.
 *
 * @see ui/src/features/code-editor/Preview.tsx
 *
 * @param slots - The slots to get the names for.
 * @returns The slot names.
 */
export function getSlotNamesForPreview(slots: CodeComponentSlot[]): string[] {
  return slots
    .filter((slot) => slot.name && slot.example)
    .map((slot) => getPropMachineName(slot.name));
}

/**
 * Returns JS for the code editor preview for slots.
 *
 * @see ui/src/features/code-editor/Preview.tsx
 *
 * @param slots - The slots to get the JS for.
 * @returns The JS for the slots.
 */
export function getJsForSlotsPreview(slots: CodeComponentSlot[]) {
  return slots
    .filter((slot) => slot.name && slot.example)
    .map((slot) => {
      // Wrap the slot's example value in a function so that it can be
      // rendered by Preact.
      return `export function ${getPropMachineName(slot.name)}() { return (${slot.example as string});}`;
    })
    .join('\n');
}

/**
 * Serializes props for saving in the JS Component config entity.
 *
 * @see ui/tests/fixtures/code-component-props.json
 * @see ui/tests/unit/code-editor-utils.cy.jsx
 *
 * @param props - The props to serialize.
 * @returns The serialized props.
 */
export function serializeProps(props: CodeComponentProp[]) {
  // Filter out props without a name since they are not valid yet.
  return props
    .filter((prop) => prop.name)
    .reduce(
      (acc, prop) => {
        const {
          name,
          type,
          example,
          enum: enumValues,
          $ref,
          format,
          contentMediaType,
          'x-formatting-context': xFormattingContext,
          derivedType,
        } = prop;
        const isNumberType = ['integer', 'number'].includes(type);
        const isVideo = derivedType === 'video';
        const processed: CodeComponentPropSerialized = {
          title: name,
          type,
          // The example is taken from the prop if it's a truthy value, or a
          // boolean false value (which could be an example of a boolean prop).
          ...((example || example === false) && {
            examples: [
              isNumberType
                ? Number(example)
                : isVideo && typeof example === 'object'
                  ? serializeVideoSrc(example as CodeComponentPropVideoExample)
                  : example,
            ],
          }),
          ...(enumValues && {
            enum: isNumberType ? enumValues.map(Number) : enumValues,
            'meta:enum': Object.fromEntries(
              enumValues.map((value) => [
                value.replace('.', '_'),
                isNumberType ? Number(value) : value,
              ]),
            ),
          }),
          ...($ref && { $ref }),
          ...(format && { format }),
          ...(contentMediaType && { contentMediaType }),
          ...(xFormattingContext && {
            'x-formatting-context': xFormattingContext,
          }),
        };
        return { ...acc, [getPropMachineName(name)]: processed };
      },
      {} as Record<string, CodeComponentPropSerialized>,
    );
}

/**
 * Deserializes props from the JS Component config entity.
 *
 * @see ui/tests/fixtures/code-component-props.json
 * @see ui/tests/unit/code-editor-utils.cy.jsx
 *
 * @param props - The props to deserialize.
 * @returns The deserialized props.
 */
export function deserializeProps(
  props: Record<string, CodeComponentPropSerialized>,
): CodeComponentProp[] {
  if (!props) {
    return [];
  }
  return Object.entries(props).map(([key, prop]) => {
    const {
      title,
      type,
      examples,
      enum: enumValues,
      $ref,
      format,
      contentMediaType,
      'x-formatting-context': xFormattingContext,
    } = prop;
    let example: CodeComponentProp['example'] = '';
    const derivedType =
      derivedPropTypes.find((type) => type.derive(prop))?.type ?? null;
    const isVideo = derivedType == 'video';

    if (examples?.length) {
      if (type === 'object') {
        example = examples[0] as unknown as
          | CodeComponentPropImageExample
          | CodeComponentPropVideoExample;
      } else if (type === 'boolean') {
        example = examples[0] as unknown as boolean;
      } else {
        example = String(examples[0]);
      }
    }

    const deserializedProp = {
      id: uuidv4(),
      name: title,
      type,
      example:
        isVideo && typeof example === 'object'
          ? deserializeVideoSrc(example as CodeComponentPropVideoExample)
          : example,
      ...(enumValues && { enum: enumValues.map(String) }),
      ...($ref && { $ref }),
      ...(format && { format }),
      ...(contentMediaType && { contentMediaType }),
      ...(xFormattingContext && { 'x-formatting-context': xFormattingContext }),
      derivedType,
    };

    // Backwards compatibility
    // @see https://www.drupal.org/i/3520843
    if (derivedType === 'formattedText' && prop.$ref?.includes('textarea')) {
      deserializedProp.contentMediaType = 'text/html';
      deserializedProp['x-formatting-context'] = 'block';
      delete deserializedProp.$ref;
    }

    return deserializedProp;
  });
}

/**
 * Serializes slots for saving in the JS Component config entity.
 *
 * @see ui/tests/fixtures/code-component-slots.json
 * @see ui/tests/unit/code-editor-utils.cy.jsx
 */
export function serializeSlots(slots: CodeComponentSlot[]) {
  // Filter out slots without a name since they are not valid yet.
  return slots
    .filter((slot) => slot.name)
    .reduce(
      (acc, slot) => {
        const { name, example } = slot;
        return {
          ...acc,
          [getPropMachineName(name)]: {
            title: name,
            ...(example && { examples: [example] }),
          },
        };
      },
      {} as Record<string, CodeComponentSlotSerialized>,
    );
}

/**
 * Deserializes slots from the JS Component config entity.
 *
 * @see ui/tests/fixtures/code-component-slots.json
 * @see ui/tests/unit/code-editor-utils.cy.jsx
 */
export function deserializeSlots(
  slots: Record<string, CodeComponentSlotSerialized>,
): CodeComponentSlot[] {
  if (!slots) {
    return [];
  }
  return Object.entries(slots).map(([key, slot]) => ({
    id: uuidv4(),
    name: slot.title,
    example: slot.examples?.length ? slot.examples[0] : '',
  }));
}

/**
 * Deserializes a code component.
 */
export function deserializeCodeComponent(
  codeComponent: CodeComponentSerialized,
): CodeComponent {
  return {
    ...codeComponent,
    props: deserializeProps(codeComponent.props),
    slots: deserializeSlots(codeComponent.slots),
  };
}

/**
 * Extracts import statements from an AST.
 *
 * @param ast - ast object.
 * @param scope - An optional string to filter imports by a specific scope. If provided, only imports starting with this scope are included.
 */
export const getImportsFromAst = (ast: File, scope?: string) =>
  ast.program.body
    .filter((d) => d.type === 'ImportDeclaration')
    .reduce<string[]>((carry, d) => {
      const source = d.source.value;
      if (scope && !source.startsWith(scope)) {
        return carry;
      }
      return [...carry, scope ? source.slice(scope.length) : source];
    }, []);

/**
 * Exports data dependencies from AST.
 */
export const getDataDependenciesFromAst = (ast: File): DataDependencies =>
  ast.program.body
    .filter((d) => d.type === 'ImportDeclaration')
    .reduce<DataDependencies>((carry, d) => {
      // @todo Parse out any URLs used by the JsonApiClient or fetch in
      //   https://drupal.org/i/3538273
      const source = d.source.value;
      if (
        // Only consider imports from these two modules.
        source !== '@/lib/drupal-utils' &&
        source !== '@drupal-api-client/json-api-client'
      ) {
        return carry;
      }
      const map = {
        JsonApiClient: ['v0.baseUrl', 'v0.jsonapiSettings'],
        getSiteData: ['v0.baseUrl', 'v0.branding'],
        getPageData: ['v0.breadcrumbs', 'v0.pageTitle'],
      };
      const drupalSettingsDependencies = carry.drupalSettings || [];
      return {
        ...carry,
        drupalSettings: drupalSettingsDependencies
          .concat(
            d.specifiers
              // First get the name of the imports from these modules.
              // @see https://github.com/babel/babel/blob/main/packages/babel-parser/ast/spec.md#importdeclaration
              .reduce<string[]>((imports, specifier) => {
                if (!('imported' in specifier)) {
                  // This is a default import e.g. 'import Something from "something"'
                  // but we don't have default exports in drupal-utils.ts or
                  // jsonapi-client.ts, so we can ignore.
                  return imports;
                }
                if ('name' in specifier.imported) {
                  // Identifier.
                  // @see https://github.com/babel/babel/blob/main/packages/babel-parser/ast/spec.md#identifier
                  return [...imports, specifier.imported.name];
                }
                if ('value' in specifier.imported) {
                  // StringLiteral.
                  // @see https://github.com/babel/babel/blob/main/packages/babel-parser/ast/spec.md#stringliteral
                  return [...imports, specifier.imported.value];
                }
                return imports;
              }, [])
              // Remove any imports other than getSiteData, getPageData or
              // JsonApiClient - we don't need drupalSettings for anything else.
              .filter((item) => Object.keys(map).includes(item))
              .reduce<string[]>(
                // Expand the dependencies from the map.
                (settings, item) =>
                  settings.concat(...map[item as keyof typeof map]),
                [],
              ),
          )
          // Filter to unique values.
          .filter((item, ix, settings) => settings.indexOf(item) === ix),
      };
    }, {});

/**
 * Formats a string into a valid JS identifier for imports.
 * ex. import ${formatted} from '@/components/source'
 */
export function formatToValidImportName(name: string): string {
  if (!name) return '';
  // Remove special characters and spaces, keeping alphanumeric and underscore
  let formatted = name.replace(/[^\w\s]/g, '');
  // Convert to PascalCase (capitalize first letter of each word)
  formatted = formatted
    .split(/[\s_-]+/)
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join('');
  // Ensure it doesn't start with a number
  if (/^\d/.test(formatted)) {
    formatted = 'Component' + formatted;
  }
  return formatted;
}

/**
 * Detects if there is a valid change in props or slots.
 * Adding an item with an empty name is not considered a valid change.
 */
export function detectValidPropOrSlotChange(
  current: CodeComponentProp[] | CodeComponentSlot[],
  last: CodeComponentProp[] | CodeComponentSlot[],
): boolean {
  // If arrays are identical, no change
  if (isEqual(current, last)) {
    return false;
  }

  // Create a version of current without empty name items
  const currentWithoutEmpty = current.filter((item) => item.name !== '');
  const lastWithoutEmpty = last.filter((item) => item.name !== '');

  // Check if the only difference is empty-named items
  // by comparing the filtered current with last
  if (isEqual(currentWithoutEmpty, lastWithoutEmpty)) {
    return false;
  }

  // There are other changes besides empty-named items
  return true;
}

function serializeVideoSrc(example: CodeComponentPropVideoExample) {
  const allowedExamplesForServer = Object.values(CONFIG_EXAMPLE_URLS);
  for (const allowedPath of allowedExamplesForServer) {
    if (example.src.endsWith(allowedPath as string)) {
      return { ...example, src: allowedPath as string };
    }
  }
  // If no match, return the original.
  return example;
}

function deserializeVideoSrc(example: CodeComponentPropVideoExample) {
  const moduleBaseUrl = getCanvasModuleBaseUrl();
  const configExampleUrls = Object.values(CONFIG_EXAMPLE_URLS);
  for (const configUrl of configExampleUrls) {
    if (example.src.includes(configUrl)) {
      const pathForPreview = `${moduleBaseUrl}${configUrl}`;
      return { ...example, src: pathForPreview as string };
    }
  }
  return example;
}
