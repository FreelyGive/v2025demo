import { useEffect, useState } from 'react';
import { Flex, Select } from '@radix-ui/themes';

import { useAppDispatch } from '@/app/hooks';
import { updateProp } from '@/features/code-editor/codeEditorSlice';
import {
  Divider,
  FormElement,
  Label,
} from '@/features/code-editor/component-data/FormElement';
import { getCanvasModuleBaseUrl } from '@/utils/drupal-globals';

import type {
  CodeComponentProp,
  CodeComponentPropVideoExample,
} from '@/types/CodeComponent';

const moduleBaseUrl = getCanvasModuleBaseUrl() || '';

const POSTER_SERVICE_URL = 'https://placehold.co/';
export const CONFIG_EXAMPLE_URLS = {
  '16:9': `/ui/assets/videos/mountain_wide.mp4`,
  '9:16': `/ui/assets/videos/bird_vertical.mp4`,
};
// The live preview of the code component must be able to render
const VIDEO_SERVICE_URLS = {
  '16:9': `${moduleBaseUrl}${CONFIG_EXAMPLE_URLS['16:9']}`,
  '9:16': `${moduleBaseUrl}${CONFIG_EXAMPLE_URLS['9:16']}`,
};
const NONE_VALUE = '_none_';

// Generate the URL for the poster using the selected Aspect ratio.
export const EXAMPLE_ASPECT_RATIO_VALUES = [
  {
    value: '16:9',
    label: 'Widescreen',
    width: 1920,
    height: 1080,
    exampleSrc: VIDEO_SERVICE_URLS['16:9'],
  },
  {
    value: '9:16',
    label: 'Vertical',
    width: 1080,
    height: 1920,
    exampleSrc: VIDEO_SERVICE_URLS['9:16'],
  },
];

const DEFAULT_ASPECT_RATIO = EXAMPLE_ASPECT_RATIO_VALUES[0].value;

export const parseExampleSrc = (src: string): any => {
  if (!src || !src.startsWith(POSTER_SERVICE_URL)) {
    return DEFAULT_ASPECT_RATIO;
  }
  try {
    // Match dimensions in formats like 800x600, 1200x900, etc.
    const regex = /(\d+)x(\d+)/;
    const match = src.match(regex);
    if (!match) {
      return DEFAULT_ASPECT_RATIO;
    }
    const [, width, height] = match;
    return (
      EXAMPLE_ASPECT_RATIO_VALUES.find(
        (ratio) =>
          ratio.width === Number(width) && ratio.height === Number(height),
      )?.value || DEFAULT_ASPECT_RATIO
    );
  } catch (error) {
    console.error('Error parsing example URL:', error);
    return DEFAULT_ASPECT_RATIO;
  }
};

export default function FormPropTypeVideo({
  id,
  example,
  isDisabled = false,
  required,
}: Pick<CodeComponentProp, 'id'> & {
  example: CodeComponentPropVideoExample;
  isDisabled?: boolean;
  required: boolean;
}) {
  const exampleAspectRatio = parseExampleSrc(example.poster);
  const dispatch = useAppDispatch();
  const [aspectRatio, setAspectRatio] = useState(exampleAspectRatio);
  const [localRequired, setLocalRequired] = useState(required);

  useEffect(() => {
    // Track changes to the required prop, update aspect ratio if needed.
    setLocalRequired(required);
    if (required !== localRequired && required && aspectRatio === NONE_VALUE) {
      setAspectRatio(DEFAULT_ASPECT_RATIO);
    }
  }, [required, localRequired, aspectRatio]);

  useEffect(() => {
    if (aspectRatio === NONE_VALUE) {
      dispatch(
        updateProp({
          id,
          updates: {
            example: '',
          },
        }),
      );
      return;
    }
    const aspectRatioData =
      EXAMPLE_ASPECT_RATIO_VALUES.find(
        (ratio) => ratio.value === aspectRatio,
      ) || EXAMPLE_ASPECT_RATIO_VALUES[0];

    dispatch(
      updateProp({
        id,
        updates: {
          example: {
            // ⚠️ @todo This uses the SAME URL for both the live preview and to send to the server at `canvas/api/v0/config/auto-save/js_component/…`.
            // This needs to send different values for either:
            // - one of CONFIG_EXAMPLE_URLS to the server
            // - one of VIDEO_SERVICE_URLS for the live preview
            src: aspectRatioData.exampleSrc.substring(moduleBaseUrl.length),
            poster: `${POSTER_SERVICE_URL}${aspectRatioData.width}x${aspectRatioData.height}.png?text=${aspectRatioData.label}`,
          },
        },
      }),
    );
  }, [aspectRatio, dispatch, id]);

  return (
    <Flex direction="column" gap="4" flexGrow="1">
      <Divider />
      <FormElement>
        <Label htmlFor={`prop-example-${id}`}>Example aspect ratio</Label>
        <Select.Root
          value={aspectRatio}
          onValueChange={setAspectRatio}
          size="1"
          disabled={isDisabled}
        >
          <Select.Trigger id={`prop-example-${id}`} />
          <Select.Content>
            {!required && (
              <Select.Item value={NONE_VALUE}>- None -</Select.Item>
            )}
            {EXAMPLE_ASPECT_RATIO_VALUES.map((value) => (
              <Select.Item key={value.value} value={value.value}>
                {value.value} ({value.label})
              </Select.Item>
            ))}
          </Select.Content>
        </Select.Root>
      </FormElement>
    </Flex>
  );
}
