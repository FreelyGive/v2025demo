import clsx from 'clsx';

import PropLinker from '@/components/form/components/drupal/PropLinker';
import FormElementLabel from '@/components/form/components/FormElementLabel';
import { a2p } from '@/local_packages/utils.js';

import type { PropLinkerProps } from '@/components/form/components/drupal/PropLinker';
import type { Attributes } from '@/types/DrupalAttribute';

// Account for linker data being snake_case when it arrives via attributes.
interface PropLinkData extends Omit<PropLinkerProps, 'propName'> {
  prop_name: string;
}

const DrupalFormElementLabel = ({
  title = { '#markup': '' },
  titleDisplay = '',
  required = '',
  attributes = {},
  directLinkerData = undefined,
}: {
  title:
    | {
        '#markup': string;
      }
    | string;
  titleDisplay?: string;
  required?: string;
  attributes?: Attributes;
  directLinkerData?: PropLinkData;
}) => {
  const classes = clsx(
    titleDisplay === 'after' ? 'option' : '',
    titleDisplay === 'invisible' ? 'visually-hidden' : '',
    required ? 'js-form-required' : '',
    required ? 'form-required' : '',
  );
  const show = !!title || !!required;

  // The basic form label
  const theLabel = (
    <FormElementLabel
      attributes={a2p(
        attributes,
        {},
        { skipAttributes: ['class', 'prop_link_data'] },
      )}
      className={classes}
    >
      {typeof title === 'string' ? title : title['#markup']}
    </FormElementLabel>
  );
  const getTheLabel = () => {
    // If there is prop link data, render the PropLinker next to the label.
    // We wrap the label in a div so they can appear next to each other without
    // the linker appearing inside the <label> tag.
    if (attributes?.prop_link_data || directLinkerData) {
      const propLinkData: PropLinkData = directLinkerData
        ? directLinkerData
        : (attributes.prop_link_data as PropLinkData);
      return (
        <div className="canvas-linked-prop-label-wrapper">
          {theLabel}
          <PropLinker
            propName={propLinkData.prop_name}
            linked={propLinkData.linked}
            suggestions={propLinkData.suggestions}
          />
        </div>
      );
    }

    // If there are no prop links, just return the label.
    return theLabel;
  };

  return show && getTheLabel();
};

export default DrupalFormElementLabel;
