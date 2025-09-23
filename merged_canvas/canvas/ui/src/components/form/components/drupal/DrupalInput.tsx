import clsx from 'clsx';

import Checkbox from '@/components/form/components/Checkbox';
import { DrupalRadioItem } from '@/components/form/components/drupal/DrupalRadio';
import Hidden from '@/components/form/components/Hidden';
import TextField from '@/components/form/components/TextField';
import TextFieldAutocomplete from '@/components/form/components/TextFieldAutocomplete';
import InputBehaviors from '@/components/form/inputBehaviors';
import { a2p } from '@/local_packages/utils.js';

import type { Attributes } from '@/types/DrupalAttribute';

const DrupalInput = ({
  attributes = {},
}: {
  attributes?: Attributes & {
    onChange?: (e: React.ChangeEvent<HTMLInputElement>) => void;
  };
}) => {
  switch (attributes?.type) {
    case 'checkbox': {
      return <Checkbox attributes={attributes} />;
    }
    case 'number':
      // The a2p() process converts 'value to 'defaultValue', which is typically
      // what React wants. Explicitly set value but don't cast empty/false-like
      // values to an empty string.
      return (
        <TextField
          attributes={{
            ...a2p(attributes, {}, { skipAttributes: ['value'] }),
            value: attributes.value,
          }}
        />
      );
    case 'radio':
      return <DrupalRadioItem attributes={attributes} />;
    case 'hidden':
    case 'submit':
      if (attributes['data-track-hidden-value']) {
        return <Hidden attributes={attributes} />;
      }
      // The a2p() process converts 'value to 'defaultValue', which is typically
      // what React wants. Explicitly set the value on submit inputs since that
      // is the text it displays.
      return <input {...a2p(attributes)} value={attributes.value || ''} />;
    default:
      if (
        attributes?.class instanceof Array &&
        attributes?.class?.includes('form-autocomplete')
      ) {
        return (
          <TextFieldAutocomplete
            className={clsx(attributes.class)}
            attributes={a2p(attributes, {}, { skipAttributes: ['class'] })}
          />
        );
      }
      return (
        <TextField
          className={clsx(attributes.class)}
          attributes={a2p(attributes, {}, { skipAttributes: ['class'] })}
        />
      );
  }
};

export default InputBehaviors(DrupalInput);
