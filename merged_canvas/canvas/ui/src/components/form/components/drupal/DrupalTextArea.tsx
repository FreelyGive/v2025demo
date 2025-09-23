import { useRef, useState } from 'react';
import { Flex } from '@radix-ui/themes';

import TextArea from '@/components/form/components/TextArea';
import InputBehaviors from '@/components/form/inputBehaviors';
import { a2p } from '@/local_packages/utils.js';
import { getDrupalSettings } from '@/utils/drupal-globals';

import DrupalFormattedTextArea from './DrupalFormattedTextArea';

import type { Attributes } from '@/types/DrupalAttribute';
import type { FormatType } from '@/types/FormatType';

const drupalSettings = getDrupalSettings();

const DrupalTextArea = ({
  attributes = {},
  wrapperAttributes = {},
}: {
  attributes?: Attributes;
  wrapperAttributes?: Attributes;
}) => {
  const defaultFormatName =
    (attributes?.['data-canvas-text-format'] as string) || '';
  const [format, setFormat] = useState<FormatType>(
    (defaultFormatName &&
      drupalSettings?.editor?.formats?.[defaultFormatName]) || {
      format: defaultFormatName,
    },
  );

  const ref = useRef<HTMLTextAreaElement | null>(null);
  const availableFormats =
    (attributes?.['data-canvas-available-formats'] &&
      JSON.parse(attributes['data-canvas-available-formats'] as string)) ||
    null;

  const selectAttributes =
    (attributes?.['data-canvas-format-select-attributes'] &&
      JSON.parse(`${attributes['data-canvas-format-select-attributes']}`)) ||
    {};

  return (
    <>
      {format?.editor === 'ckeditor5' && format.editorSettings && (
        <DrupalFormattedTextArea
          attributes={attributes}
          format={{
            editorSettings: format.editorSettings,
          }}
          ref={ref}
        />
      )}
      {format?.editor !== 'ckeditor5' && (
        <div {...a2p(wrapperAttributes)}>
          <TextArea
            value={attributes.value?.toString() ?? ''}
            attributes={a2p(attributes, {}, { skipAttributes: ['value'] })}
            ref={ref}
          />
        </div>
      )}
      {availableFormats && format?.format && (
        <WrappedFormatSelect
          attributes={{ ...attributes, ...selectAttributes }}
          selectAttributes={selectAttributes}
          format={format}
          defaultFormatName={defaultFormatName}
          availableFormats={availableFormats}
          setFormat={setFormat}
        />
      )}
    </>
  );
};

interface FormatSelectProps {
  attributes: Attributes;
  selectAttributes: Record<string, any>;
  format: FormatType;
  defaultFormatName: string;
  availableFormats: Record<string, string>;
  setFormat: (format: FormatType) => void;
}

// The select element used to choose the text format.
const FormatSelect = ({
  attributes,
  selectAttributes,
  format,
  defaultFormatName,
  availableFormats,
  setFormat,
}: FormatSelectProps) => {
  return (
    <Flex gap="1" align="center" my="2">
      <label htmlFor={(attributes.id as string) || ''}>Text format</label>
      {/* Using a native select instead of Radix requires less plumbing. */}
      <select
        {...a2p(attributes, {}, { skipAttributes: ['value'] })}
        {...a2p(selectAttributes)}
        defaultValue={format.format || defaultFormatName}
        data-testid="text-format-select"
        onChange={(e) => {
          const formatName = e.target.value;
          const newFormat = drupalSettings.editor.formats[formatName] || {
            format: formatName,
          };
          setFormat(newFormat);
          if (typeof attributes?.onChange === 'function') {
            const changeEvent = new Event('change');
            Object.defineProperty(changeEvent, 'target', {
              writable: false,
              value: e.target,
            });
            attributes.onChange(changeEvent);
          }
        }}
      >
        {Object.entries(availableFormats).map(([key, value], index) => (
          <option key={index} value={key}>
            {value as string}
          </option>
        ))}
      </select>
    </Flex>
  );
};

// We need to create a wrapper for FormatSelect that can be processed by InputBehaviors
// InputBehaviors expects a component that can accept any props, but we have specific prop requirements
const FormatSelectWrapper = (props: any) => {
  // Ensure we're using the right props
  return <FormatSelect {...(props as FormatSelectProps)} />;
};

// Now InputBehaviors can process our component correctly
const WrappedFormatSelect = InputBehaviors(FormatSelectWrapper);

export default InputBehaviors(DrupalTextArea);
