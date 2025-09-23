import clsx from 'clsx';
import { Box } from '@radix-ui/themes';

import { a2p } from '@/local_packages/utils.js';

import type { Attributes } from '@/types/DrupalAttribute';

interface DrupalContainerTextFormatFilterProps {
  attributes?: Attributes;
  renderChildren?: JSX.Element | null;
  hasParent?: boolean;
}

/**
 * Mapped to `container--text-format-filter-guidelines.html.twig`.
 * @see https://git.drupalcode.org/project/drupal/-/blob/11.x/core/modules/system/templates/container.html.twig
 */
const DrupalContainerTextFormatFilterGuidelines = ({
  attributes = {},
  renderChildren = null,
  hasParent = false,
}: DrupalContainerTextFormatFilterProps) => {
  return (
    <Box
      {...a2p(attributes, {
        class: clsx(hasParent && ['js-form-wrapper', 'form-wrapper']),
      })}
    >
      {renderChildren}
    </Box>
  );
};

/**
 * Mapped to `container--text-format-filter-help.html.twig`.
 * @see https://git.drupalcode.org/project/drupal/-/blob/11.x/core/modules/system/templates/container.html.twig
 */
const DrupalContainerTextFormatFilterHelp = ({
  attributes = {},
  renderChildren = null,
  hasParent = false,
}: DrupalContainerTextFormatFilterProps) => {
  return (
    <Box
      {...a2p(attributes, {
        class: clsx(hasParent && ['js-form-wrapper', 'form-wrapper']),
      })}
      mb="2"
    >
      {renderChildren}
    </Box>
  );
};

export {
  DrupalContainerTextFormatFilterGuidelines,
  DrupalContainerTextFormatFilterHelp,
};
