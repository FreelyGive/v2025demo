/**
 * @file
 *
 * Definition to derive a type of a prop based on its schema.
 *
 * This is used to produce a distinct set of types to be presented on the UI
 * when defining props for a code component.
 *
 * E.g. the serialized prop schema could have the following shape:
 * @code
 * {
 *   "type": "string",
 *   "format": "uri",
 * }
 * @endcode
 *
 * Which would be derived as the "link" type. The same shape without the
 * "format" property would be derived as the "text" type.
 *
 * @see config/schema/canvas.schema.yml#canvas.js_component.*.mapping.props
 */

import type { CodeComponentPropSerialized } from '@/types/CodeComponent';

const derivedPropTypes = [
  {
    type: 'text' as const,
    displayName: 'Text',
    derive: (prop: CodeComponentPropSerialized) =>
      prop.type === 'string' &&
      !prop.$ref &&
      !prop.format &&
      !prop.contentMediaType &&
      !prop['x-formatting-context'] &&
      (!prop.enum || prop.enum.length === 0),
    init: {
      type: 'string',
    },
  },
  {
    type: 'formattedText' as const,
    displayName: 'Formatted text',
    derive: (prop: CodeComponentPropSerialized) =>
      prop.type === 'string' &&
      ((prop.contentMediaType === 'text/html' &&
        prop['x-formatting-context'] === 'block') ||
        // Backwards compatibility
        // @see https://www.drupal.org/i/3520843
        prop.$ref?.includes('textarea')),
    init: {
      type: 'string',
      contentMediaType: 'text/html',
      'x-formatting-context': 'block',
    },
  },
  {
    type: 'link' as const,
    displayName: 'Link',
    derive: (prop: CodeComponentPropSerialized) =>
      prop.type === 'string' &&
      ['uri', 'uri-reference'].includes(prop.format as string),
    init: {
      type: 'string',
      format: 'uri-reference',
    },
  },
  {
    type: 'image' as const,
    displayName: 'Image',
    derive: (prop: CodeComponentPropSerialized) =>
      prop.type === 'object' && prop.$ref?.includes('image'),
    init: {
      type: 'object',
      $ref: 'json-schema-definitions://canvas.module/image',
    },
  },
  {
    type: 'video' as const,
    displayName: 'Video',
    derive: (prop: CodeComponentPropSerialized) =>
      prop.type === 'object' && prop.$ref?.includes('video'),
    init: {
      type: 'object',
      $ref: 'json-schema-definitions://canvas.module/video',
    },
  },
  {
    type: 'boolean' as const,
    displayName: 'Boolean',
    derive: (prop: CodeComponentPropSerialized) => prop.type === 'boolean',
    init: {
      type: 'boolean',
      example: false,
    },
  },
  {
    type: 'integer' as const,
    displayName: 'Integer',
    derive: (prop: CodeComponentPropSerialized) =>
      prop.type === 'integer' && (!prop.enum || prop.enum.length === 0),
    init: {
      type: 'integer',
    },
  },
  {
    type: 'number' as const,
    displayName: 'Number',
    derive: (prop: CodeComponentPropSerialized) =>
      prop.type === 'number' && (!prop.enum || prop.enum.length === 0),
    init: {
      type: 'number',
    },
  },
  {
    type: 'listText' as const,
    displayName: 'List: text',
    derive: (prop: CodeComponentPropSerialized) =>
      prop.type === 'string' && prop.enum && prop.enum.length > 0,
    init: {
      type: 'string',
      enum: [],
    },
  },
  {
    type: 'listInteger' as const,
    displayName: 'List: integer',
    derive: (prop: CodeComponentPropSerialized) =>
      prop.type === 'integer' && prop.enum && prop.enum.length > 0,
    init: {
      type: 'integer',
      enum: [],
    },
  },
];

export default derivedPropTypes;
