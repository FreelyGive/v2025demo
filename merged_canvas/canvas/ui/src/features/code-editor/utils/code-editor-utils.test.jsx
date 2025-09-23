/* cspell:ignore Mycomponentname HelloWorldexample */
import { describe, expect, it } from 'vitest';
import { parse } from '@babel/parser';
import fixtureProps from '@tests/fixtures/code-component-props.json';
import fixtureSlots from '@tests/fixtures/code-component-slots.json';

import {
  deserializeProps,
  deserializeSlots,
  formatToValidImportName,
  getDataDependenciesFromAst,
  getImportsFromAst,
  getPropValuesForPreview,
  serializeProps,
  serializeSlots,
} from '@/features/code-editor/utils';

const {
  deserialized: deserializedPropsFixture,
  serialized: serializedPropsFixture,
} = fixtureProps;

const {
  deserialized: deserializedSlotsFixture,
  serialized: serializedSlotsFixture,
} = fixtureSlots;

/**
 * Custom matcher for serialized props
 */
function matchSerializedProps(received, fixtureKeys) {
  const expected = {};
  fixtureKeys.forEach((prop) => {
    expected[prop] = serializedPropsFixture[prop];
  });

  expect(received).toEqual(expected);
}

/**
 * Custom matcher for deserialized props
 */
function matchDeserializedProps(received, propIndices) {
  // Deserialized props should be an array
  expect(Array.isArray(received)).toBe(true);

  // Get the expected values from the fixture using the provided indices
  const expected = [];
  propIndices.forEach((index) => {
    expected.push(deserializedPropsFixture[index]);
  });

  // Check that each deserialized prop has an `id` key with a string in it
  received.forEach((prop) => {
    expect(prop).toHaveProperty('id');
    expect(typeof prop.id).toBe('string');
  });

  // Compare the rest of the props by removing IDs first
  const actualWithoutIds = received.map((prop) => {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const { id, ...rest } = prop;
    return rest;
  });
  const expectedWithoutIds = expected.map((prop) => {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const { id, ...rest } = prop;
    return rest;
  });

  expect(actualWithoutIds).toEqual(expectedWithoutIds);
}

describe('Code editor utilities', () => {
  describe('serialize props', () => {
    it('of type text', () => {
      const result = serializeProps([
        deserializedPropsFixture[0],
        deserializedPropsFixture[1],
      ]);
      matchSerializedProps(result, [
        'stringWithNoExampleValue',
        'stringWithExampleValue',
      ]);
    });

    it('of type integer', () => {
      const result = serializeProps([
        deserializedPropsFixture[2],
        deserializedPropsFixture[3],
      ]);
      matchSerializedProps(result, [
        'integerWithNoExampleValue',
        'integerWithExampleValue',
      ]);
    });

    it('of type number', () => {
      const result = serializeProps([
        deserializedPropsFixture[4],
        deserializedPropsFixture[5],
      ]);
      matchSerializedProps(result, [
        'numberWithNoExampleValue',
        'numberWithExampleValue',
      ]);
    });

    it('of type boolean', () => {
      const result = serializeProps([
        deserializedPropsFixture[6],
        deserializedPropsFixture[7],
      ]);
      matchSerializedProps(result, [
        'booleanWithExampleValueTrue',
        'booleanWithExampleValueFalse',
      ]);
    });

    it('of type text list', () => {
      const result = serializeProps([
        deserializedPropsFixture[8],
        deserializedPropsFixture[9],
      ]);
      matchSerializedProps(result, [
        'textListWithNoExampleValue',
        'textListWithExampleValue',
      ]);
    });

    it('of type integer list', () => {
      const result = serializeProps([
        deserializedPropsFixture[10],
        deserializedPropsFixture[11],
      ]);
      matchSerializedProps(result, [
        'integerListWithNoExampleValue',
        'integerListWithExampleValue',
      ]);
    });

    it('of type formatted text', () => {
      const result = serializeProps([
        deserializedPropsFixture[12],
        deserializedPropsFixture[13],
      ]);
      matchSerializedProps(result, [
        'formattedTextWithNoExampleValue',
        'formattedTextWithExampleValue',
      ]);
    });
  });

  describe('deserialize props', () => {
    it('of type text', () => {
      const result = deserializeProps([
        serializedPropsFixture.stringWithNoExampleValue,
        serializedPropsFixture.stringWithExampleValue,
      ]);
      matchDeserializedProps(result, [0, 1]);
    });

    it('of type integer', () => {
      const result = deserializeProps([
        serializedPropsFixture.integerWithNoExampleValue,
        serializedPropsFixture.integerWithExampleValue,
      ]);
      matchDeserializedProps(result, [2, 3]);
    });

    it('of type number', () => {
      const result = deserializeProps([
        serializedPropsFixture.numberWithNoExampleValue,
        serializedPropsFixture.numberWithExampleValue,
      ]);
      matchDeserializedProps(result, [4, 5]);
    });

    it('of type boolean', () => {
      const result = deserializeProps([
        serializedPropsFixture.booleanWithExampleValueTrue,
        serializedPropsFixture.booleanWithExampleValueFalse,
      ]);
      matchDeserializedProps(result, [6, 7]);
    });

    it('of type text list', () => {
      const result = deserializeProps([
        serializedPropsFixture.textListWithNoExampleValue,
        serializedPropsFixture.textListWithExampleValue,
      ]);
      matchDeserializedProps(result, [8, 9]);
    });

    it('of type integer list', () => {
      const result = deserializeProps([
        serializedPropsFixture.integerListWithNoExampleValue,
        serializedPropsFixture.integerListWithExampleValue,
      ]);
      matchDeserializedProps(result, [10, 11]);
    });

    it('of type formatted text', () => {
      const result = deserializeProps([
        serializedPropsFixture.formattedTextWithNoExampleValue,
        serializedPropsFixture.formattedTextWithExampleValue,
      ]);
      matchDeserializedProps(result, [12, 13]);
    });

    it('of type image', () => {
      const result = deserializeProps([
        serializedPropsFixture.imageWithNoExampleValue,
        serializedPropsFixture.imageWithExampleValue,
      ]);
      matchDeserializedProps(result, [14, 15]);
    });

    it('of type video', () => {
      const result = deserializeProps([
        serializedPropsFixture.videoWithNoExampleValue,
        serializedPropsFixture.videoWithExampleValue,
      ]);
      matchDeserializedProps(result, [22, 23]);
    });

    it('of type link', () => {
      const result = deserializeProps([
        serializedPropsFixture.relativePathLinkWithNoExampleValue,
        serializedPropsFixture.relativePathLinkWithExampleValue,
        serializedPropsFixture.fullUrlLinkWithNoExampleValue,
        serializedPropsFixture.fullUrlLinkWithExampleValue,
      ]);
      matchDeserializedProps(result, [16, 17, 18, 19]);
    });

    // Backwards compatibility
    // @see https://www.drupal.org/i/3520843
    it('of type deprecated text area - backwards compatibility', () => {
      const result = deserializeProps([
        serializedPropsFixture.deprecatedTextAreaWithNoExampleValue,
        serializedPropsFixture.deprecatedTextAreaWithExampleValue,
      ]);
      matchDeserializedProps(result, [20, 21]);
    });
  });

  it('serialize slots', () => {
    expect(serializeSlots(deserializedSlotsFixture)).toEqual(
      serializedSlotsFixture,
    );
  });

  it('deserialize slots', () => {
    const result = deserializeSlots(serializedSlotsFixture);
    expect(Array.isArray(result)).toBe(true);
    result.forEach((slot, index) => {
      expect(slot).toHaveProperty('id');
      expect(typeof slot.id).toBe('string');
      // Compare the slot without the `id` key to the expected fixture
      const withoutId = { ...slot, id: undefined };
      expect(withoutId).toEqual({
        ...deserializedSlotsFixture[index],
        id: undefined,
      });
    });
  });
});

describe('Code editor preview utilities', () => {
  it('extracts values from props for preview', () => {
    expect(getPropValuesForPreview(deserializedPropsFixture)).toEqual({
      stringWithNoExampleValue: '',
      stringWithExampleValue: 'Drupal Canvas',
      integerWithNoExampleValue: 0,
      integerWithExampleValue: 922,
      numberWithNoExampleValue: 0,
      numberWithExampleValue: 9.22,
      booleanWithExampleValueTrue: true,
      booleanWithExampleValueFalse: false,
      textListWithNoExampleValue: '',
      textListWithExampleValue: 'In Progress',
      integerListWithNoExampleValue: 0,
      integerListWithExampleValue: 2,
      formattedTextWithNoExampleValue: '',
      formattedTextWithExampleValue:
        "Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged.",
      imageWithNoExampleValue: '',
      imageWithExampleValue: {
        src: 'https://placehold.co/1200x900@2x.png',
        width: 1200,
        height: 900,
        alt: 'Example image placeholder',
      },
      relativePathLinkWithNoExampleValue: '',
      relativePathLinkWithExampleValue: 'gerbeaud',
      fullUrlLinkWithNoExampleValue: '',
      fullUrlLinkWithExampleValue: 'https://hazelnut.com',
      // Backwards compatibility
      // @see https://www.drupal.org/i/3520843
      deprecatedTextAreaWithNoExampleValue: '',
      deprecatedTextAreaWithExampleValue:
        "Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book. It has survived not only five centuries, but also the leap into electronic typesetting, remaining essentially unchanged.",
      videoWithNoExampleValue: '',
      videoWithExampleValue: {
        src: '/modules/contrib/canvas/ui/assets/videos/mountain_wide.mp4',
        poster: 'https://placehold.co/1920x1080.png?text=Horizontal',
      },
    });
  });

  it('handles empty props when extracting values for preview', () => {
    const result = getPropValuesForPreview([]);
    expect(result).toEqual({});
  });
});

describe('getImportsFromAst', () => {
  it('should return all imports when no scope is provided', () => {
    const code = `
      import React from 'react';
      import { useState } from 'react';
      import MyComponent from '@/components/MyComponent';
      import { Theme } from "@radix-ui/themes";
    `;
    const ast = parse(code, { sourceType: 'module' });
    const result = getImportsFromAst(ast);
    expect(result).toEqual([
      'react',
      'react',
      '@/components/MyComponent',
      '@radix-ui/themes',
    ]);
  });

  it('should return imports filtered by scope', () => {
    const code = `
      import React from 'react';
      import { useState } from 'react';
      import MyComponent from '@/components/MyComponent';
      import MyComponent2 from '@/components/MyComponent2';
    `;
    const ast = parse(code, { sourceType: 'module' });
    const result = getImportsFromAst(ast, '@/components/');
    expect(result).toEqual(['MyComponent', 'MyComponent2']);
  });

  it('should return an empty array if no imports match the scope', () => {
    const code = `
      import React from 'react';
      import { useState } from 'react';
    `;
    const ast = parse(code, { sourceType: 'module' });
    const result = getImportsFromAst(ast, '@/components/');
    expect(result).toEqual([]);
  });

  it('should handle an empty AST gracefully', () => {
    const ast = parse('', { sourceType: 'module' });
    const result = getImportsFromAst(ast);
    expect(result).toEqual([]);
  });
});

describe('getDataDependenciesFromAst', () => {
  it('should detect needed drupalSettings when using getSiteData and getPageData', () => {
    const code = `
      // Random import.
      import useSWR from 'swr';
      // Irrelevant import from drupal-utils, no drupalSettings
      import { sortMenu as drupalSortMenu } from '@/lib/drupal-utils';
      // Things we care about.
      import {
      // With different local identifier.
        getSiteData as iCanHazSiteData,
        getPageData
      } from '@/lib/drupal-utils';
    `;
    const ast = parse(code, { sourceType: 'module' });
    const result = getDataDependenciesFromAst(ast);
    expect(result).to.deep.equal({
      drupalSettings: [
        'v0.baseUrl',
        'v0.branding',
        'v0.breadcrumbs',
        'v0.pageTitle',
      ],
    });
  });

  it('should detect needed drupalSettings when using JsonApiClient', () => {
    const code = `
      // Random import.
      import useSWR from 'swr';
      // JsonApiClient
      import { JsonApiClient } from '@drupal-api-client/json-api-client';
    `;
    const ast = parse(code, { sourceType: 'module' });
    const result = getDataDependenciesFromAst(ast);
    expect(result).to.deep.equal({
      drupalSettings: ['v0.baseUrl', 'v0.jsonapiSettings'],
    });
  });

  it('should detect needed drupalSettings when using both getSiteData and JsonApiClient, and should prevent duplicates', () => {
    const code = `
      // Random import.
      import useSWR from 'swr';
      // JsonApiClient, which depends on v0.baseUrl
      import { JsonApiClient } from '@drupal-api-client/json-api-client';
      // getSiteData, which also depends on v0.baseUrl
      import { getSiteData } from '@/lib/drupal-utils';
    `;
    const ast = parse(code, { sourceType: 'module' });
    const result = getDataDependenciesFromAst(ast);
    expect(result).to.deep.equal({
      drupalSettings: ['v0.baseUrl', 'v0.jsonapiSettings', 'v0.branding'],
    });
  });

  it('should handle an empty AST gracefully', () => {
    const ast = parse('', { sourceType: 'module' });
    const result = getDataDependenciesFromAst(ast);
    expect(result).to.deep.equal({});
  });
});

describe('formatToValidImportName', () => {
  it('should handle basic strings', () => {
    expect(formatToValidImportName('hello world')).toBe('HelloWorld');
    expect(formatToValidImportName('my component')).toBe('MyComponent');
    expect(formatToValidImportName('mixedCase component')).toBe(
      'MixedCaseComponent',
    );
    expect(formatToValidImportName('CamelCase example')).toBe(
      'CamelCaseExample',
    );
  });

  it('should handle special characters', () => {
    expect(formatToValidImportName('hello-world!')).toBe('Helloworld');
    expect(formatToValidImportName('my@component*name')).toBe(
      'Mycomponentname',
    );
    expect(formatToValidImportName('special_chars & symbols    & space')).toBe(
      'SpecialCharsSymbolsSpace',
    );
    expect(formatToValidImportName('hello_world-example')).toBe(
      'HelloWorldexample',
    );
    expect(formatToValidImportName('\ttabbed\nwords')).toBe('TabbedWords');
  });

  it('should handle numbers', () => {
    expect(formatToValidImportName('foo123')).toBe('Foo123');
    expect(formatToValidImportName('hello 42 world')).toBe('Hello42World');
    // Starts with a number.
    expect(formatToValidImportName('123test')).toBe('Component123test');
    expect(formatToValidImportName('42 foo')).toBe('Component42Foo');
  });

  it('should handle empty or invalid inputs', () => {
    expect(formatToValidImportName('')).toBe('');
    expect(formatToValidImportName(null)).toBe('');
    expect(formatToValidImportName('!@#$%^&*()')).toBe('');
  });
});
