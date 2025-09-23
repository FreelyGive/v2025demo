import { useCallback, useEffect, useRef, useState } from 'react';

import TextField from '@/components/form/components/TextField';
import InputBehaviors from '@/components/form/inputBehaviors';
import { useEntityTitle } from '@/hooks/useEntityTitle';
import { a2p } from '@/local_packages/utils.js';
import { useGetPageLayoutQuery } from '@/services/componentAndLayout';
import { getDrupalSettings } from '@/utils/drupal-globals';

import type { Attributes } from '@/types/DrupalAttribute';
import type { transliterate as TransliterateType } from '@/types/transliterate';

const getTransliterate = (): TransliterateType => {
  const { transliterate: drupalTransliterate } = window;
  return drupalTransliterate;
};
const drupalSettings = getDrupalSettings();
const getPathAlias = (titleValue: string) => {
  const drupalTransliterate = getTransliterate();
  let alias = titleValue
    .toLowerCase()
    .replace(/\s+/g, '-')
    .replace(/[^\w-]+/g, '')
    .replace(/_+/g, '-')
    .replace(/--+/g, '-')
    .replace(/^-+/, '')
    .replace(/-+$/, '');

  if (alias.length) {
    const langcode = drupalSettings.langcode;
    const languageOverrides =
      drupalSettings?.transliteration_language_overrides?.[langcode];

    const replace: Record<string, string> = {};
    if (languageOverrides) {
      Object.keys(languageOverrides).forEach((key) => {
        replace[String.fromCharCode(parseInt(key, 10))] =
          languageOverrides[key];
      });
    }

    return `/${drupalTransliterate(alias, { replace })}`;
  }

  return '';
};

/**
 * Path widget with automatic URL alias generation from title.
 */
const DrupalPathWidget = ({
  attributes = {},
}: {
  attributes?: Attributes & {
    onChange?: (e: React.ChangeEvent<HTMLInputElement>) => void;
  };
  children?: React.ReactNode;
}) => {
  const initialValue = attributes?.value || '';
  const [pathValue, setPathValue] = useState<string>(initialValue.toString());
  const autoGenerateOn = useRef<boolean>(false);

  const titleInput = useEntityTitle();
  const { data: fetchedLayout } = useGetPageLayoutQuery();
  const isPublished = fetchedLayout?.isPublished;

  const handleChange = useCallback(
    (e: React.ChangeEvent<HTMLInputElement>) => {
      setPathValue(e.target.value);
      if (attributes.onChange) {
        attributes.onChange(e);
      }
    },
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [attributes.onChange],
  );

  const handleChangeRaw = useCallback(
    (e: React.ChangeEvent<HTMLInputElement>) => {
      autoGenerateOn.current = false;
      handleChange(e);
    },
    [handleChange],
  );

  useEffect(() => {
    if (!titleInput || !autoGenerateOn.current) {
      return;
    }

    const newValue = getPathAlias(titleInput ?? '');
    const syntheticEvent = {
      target: {
        value: newValue,
        name: attributes.name,
      },
    } as React.ChangeEvent<HTMLInputElement>;
    handleChange(syntheticEvent);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [titleInput]);

  useEffect(() => {
    // If path is empty, always generate a new path.
    if (pathValue === '') {
      autoGenerateOn.current = true;
      return;
    }

    const isOverridden = getPathAlias(titleInput ?? '') !== pathValue;
    // Only auto-generate if the path hasn't been set manually and the content
    // hasn't been published.
    if (!isOverridden && !isPublished) {
      autoGenerateOn.current = true;
      return;
    }

    autoGenerateOn.current = false;
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isPublished]);

  const processedAttrs = a2p(attributes, {
    onChange: handleChangeRaw,
    value: pathValue,
  });

  return <TextField attributes={processedAttrs} />;
};

export default InputBehaviors(DrupalPathWidget);
