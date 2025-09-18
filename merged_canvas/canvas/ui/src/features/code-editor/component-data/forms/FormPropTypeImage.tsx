import { useEffect, useState } from 'react';
import { Box, Flex, Select } from '@radix-ui/themes';

import { useAppDispatch } from '@/app/hooks';
import { updateProp } from '@/features/code-editor/codeEditorSlice';
import {
  Divider,
  FormElement,
  Label,
} from '@/features/code-editor/component-data/FormElement';

import type {
  CodeComponentProp,
  CodeComponentPropImageExample,
} from '@/types/CodeComponent';

const IMAGE_SERVICE_URL = 'https://placehold.co/';

const NONE_VALUE = '_none_';
const EXAMPLE_ASPECT_RATIO_VALUES = [
  { value: '1:1', label: '1:1 (Square)', width: 600, height: 600 },
  { value: '4:3', label: '4:3 (Standard)', width: 800, height: 600 },
  { value: '16:9', label: '16:9 (Widescreen)', width: 1280, height: 720 },
  { value: '3:2', label: '3:2 (Classic Photo)', width: 900, height: 600 },
  { value: '2:1', label: '2:1 (Panoramic)', width: 1000, height: 500 },
  { value: '9:16', label: '9:16 (Vertical)', width: 720, height: 1280 },
  { value: '21:9', label: '21:9 (Ultrawide)', width: 1400, height: 600 },
];
const DEFAULT_ASPECT_RATIO = EXAMPLE_ASPECT_RATIO_VALUES[1].value;

const EXAMPLE_PIXEL_DENSITY_OPTIONS = [
  { value: '1x', label: '1x (Standard density)' },
  { value: '2x', label: '2x (High density)' },
  { value: '3x', label: '3x (Ultra-high density)' },
];
const DEFAULT_PIXEL_DENSITY = EXAMPLE_PIXEL_DENSITY_OPTIONS[1].value;

export const parseExampleSrc = (
  src: string,
): { aspectRatio: string; pixelDensity: string } => {
  // Default values if parsing fails
  const defaults = {
    aspectRatio: DEFAULT_ASPECT_RATIO,
    pixelDensity: DEFAULT_PIXEL_DENSITY,
  };

  if (!src || !src.startsWith(IMAGE_SERVICE_URL)) {
    return defaults;
  }

  try {
    // Extract dimensions and density from URL
    // Example: https://placehold.co/800x600@2x.png
    const match = src.match(/(\d+)x(\d+)(?:@(\d+)x)?/);
    if (!match) return defaults;

    const [, width, height, density = '1'] = match;

    // Find exact matching aspect ratio
    const aspectRatio =
      EXAMPLE_ASPECT_RATIO_VALUES.find(
        (ratio) =>
          ratio.width === Number(width) && ratio.height === Number(height),
      )?.value || DEFAULT_ASPECT_RATIO;

    // Find matching pixel density
    const pixelDensity = `${density}x`;
    if (
      !EXAMPLE_PIXEL_DENSITY_OPTIONS.some(
        (option) => option.value === pixelDensity,
      )
    ) {
      return { aspectRatio, pixelDensity: DEFAULT_PIXEL_DENSITY };
    }

    return { aspectRatio, pixelDensity };
  } catch (error) {
    console.error('Error parsing example URL:', error);
    return defaults;
  }
};

export default function FormPropTypeImage({
  id,
  example,
  isDisabled = false,
  required,
}: Pick<CodeComponentProp, 'id'> & {
  example: CodeComponentPropImageExample;
  isDisabled?: boolean;
  required: boolean;
}) {
  const { aspectRatio: exampleAspectRatio, pixelDensity: examplePixelDensity } =
    parseExampleSrc(example.src);
  const dispatch = useAppDispatch();
  const [aspectRatio, setAspectRatio] = useState(exampleAspectRatio);
  const [pixelDensity, setPixelDensity] = useState(examplePixelDensity);
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

    const pixelDensitySuffix = pixelDensity === '1x' ? '' : `@${pixelDensity}`;
    const aspectRatioData =
      EXAMPLE_ASPECT_RATIO_VALUES.find(
        (ratio) => ratio.value === aspectRatio,
      ) || EXAMPLE_ASPECT_RATIO_VALUES[0];

    const alternateWidths = `${IMAGE_SERVICE_URL}{width}x{height}${pixelDensitySuffix}.png`;
    dispatch(
      updateProp({
        id,
        updates: {
          example: {
            src: `${IMAGE_SERVICE_URL}${aspectRatioData.width}x${aspectRatioData.height}${pixelDensitySuffix}.png?alternateWidths=${encodeURIComponent(alternateWidths)}`,
            width: aspectRatioData.width,
            height: aspectRatioData.height,
            alt: 'Example image placeholder',
          },
        },
      }),
    );
  }, [aspectRatio, pixelDensity, dispatch, id]);

  return (
    <Flex direction="column" gap="4" flexGrow="1">
      <Divider />
      <Flex gap="4" width="100%">
        <Box flexBasis="50%" flexShrink="0">
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
                    {value.label}
                  </Select.Item>
                ))}
              </Select.Content>
            </Select.Root>
          </FormElement>
        </Box>
        {aspectRatio !== NONE_VALUE && (
          <Box flexGrow="1">
            <FormElement>
              <Label htmlFor={`prop-example-pixel-density-${id}`}>
                Pixel density
              </Label>
              <Select.Root
                value={pixelDensity}
                onValueChange={setPixelDensity}
                size="1"
                disabled={isDisabled}
              >
                <Select.Trigger id={`prop-example-pixel-density-${id}`} />
                <Select.Content>
                  {EXAMPLE_PIXEL_DENSITY_OPTIONS.map((value) => (
                    <Select.Item key={value.value} value={value.value}>
                      {value.label}
                    </Select.Item>
                  ))}
                </Select.Content>
              </Select.Root>
            </FormElement>
          </Box>
        )}
      </Flex>
    </Flex>
  );
}
