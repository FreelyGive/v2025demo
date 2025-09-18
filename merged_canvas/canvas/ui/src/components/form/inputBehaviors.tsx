import { useCallback, useEffect, useState } from 'react';
import { debounce } from 'lodash';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import { getDefaultValue } from '@/components/form/formUtil';

import type * as React from 'react';

import './InputBehaviors.css';

import Ajv from 'ajv';

import { InputBehaviorsComponentPropsForm } from '@/components/form/InputBehaviorsComponentPropsForm';
import { InputBehaviorsEntityForm } from '@/components/form/InputBehaviorsEntityForm';
import { FORM_TYPES } from '@/features/form/constants';
import {
  clearFieldError,
  selectFieldError,
  selectFormValues,
  setFieldError,
  setFieldValue,
} from '@/features/form/formStateSlice';
import { AJAX_UPDATE_FORM_BUILD_ID_EVENT } from '@/types/Ajax';

import type { ErrorObject } from 'ajv/dist/types';
import type { FormId } from '@/features/form/formStateSlice';
import type { AjaxUpdateFormBuildIdEvent } from '@/types/Ajax';
import type { Attributes } from '@/types/DrupalAttribute';
import type { PropsValues } from '@/types/Form';

const ajv = new Ajv();

export const POLLED_BACKGROUND_TIMEOUT = 1000;
export const DEBOUNCE_TIMEOUT = 400;
export const IMMEDIATE_TIMEOUT = 0;

type ValidationResult = {
  valid: boolean;
  errors?: null | ErrorObject[];
  errorMessage?: string;
  skipEarlyReturn?: boolean;
};

type InputBehaviorsForm = (
  OriginalInput: React.FC,
  props: React.ComponentProps<any>,
) => React.ReactElement;

interface InputProps {
  attributes: Attributes & {
    onChange: (e: React.ChangeEvent) => void;
    onBlur: (e: React.FocusEvent) => void;
  };
  options?: { [key: string]: string }[];
}

// Wraps all form elements to provide common functionality and handle committing
// the form state, parsing and validation of values.
export const InputBehaviorsCommon = ({
  OriginalInput,
  props,
  callbacks,
  shouldDebounce = true,
}: {
  OriginalInput: React.FC<InputProps>;
  props: {
    value: any;
    options?: { [key: string]: string }[];
    attributes: Attributes & {
      onChange: (e: React.ChangeEvent) => void;
      onBlur: (e: React.FocusEvent) => void;
    };
  };
  callbacks: {
    commitFormState: (newFormState: PropsValues) => void;
    parseNewValue: (newValue: React.ChangeEvent) => any;
    validateNewValue: (e: React.ChangeEvent, newValue: any) => ValidationResult;
  };
  shouldDebounce?: boolean;
}) => {
  const { attributes, options, value, ...passProps } = props;
  const { commitFormState, parseNewValue, validateNewValue } = callbacks;
  const dispatch = useAppDispatch();
  const defaultValue = getDefaultValue(options, attributes, value);
  const [inputValue, setInputValue] = useState(defaultValue || '');

  const formValues = useAppSelector((state) =>
    selectFormValues(state, attributes['data-form-id'] as FormId),
  );

  const formId = attributes['data-form-id'] as FormId;
  const fieldName = (attributes.name ||
    attributes['data-canvas-name']) as string;

  const fieldIdentifier = {
    formId,
    fieldName,
  };
  const fieldError = useAppSelector((state) =>
    selectFieldError(state, fieldIdentifier),
  );
  // Include the input's default value in the form state on init - including
  // when an element is added via AJAX.
  const elementType = attributes.type || attributes['data-canvas-type'];
  useEffect(() => {
    if (
      // Ignore radios in indeterminate (initial unset) state.
      // @see https://developer.mozilla.org/en-US/docs/Web/API/HTMLInputElement/indeterminate
      (elementType === 'radios' && inputValue === '') ||
      // Every individual radio element has a value, but it isn't
      // the value of the field unless it is checked. The value of the field is
      // managed by the radios group, not the individual radio elements.
      elementType === 'radio'
    ) {
      return;
    }
    if (fieldName && formId) {
      dispatch(
        setFieldValue({
          formId,
          fieldName,
          value: elementType === 'checkbox' ? !!inputValue : inputValue,
        }),
      );
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    // Special handling for the form_build_id which can be updated by an ajax
    // callback without using hyperscriptify to render a new React component.
    if (fieldName !== 'form_build_id') {
      return;
    }
    // Listen for changes to the form build ID so we can update that in
    // our form state and value.
    const formBuildIdListener = (e: AjaxUpdateFormBuildIdEvent) => {
      if (e.detail.formId === formId) {
        dispatch(
          setFieldValue({
            formId,
            fieldName,
            value: e.detail.newFormBuildId,
          }),
        );
        setInputValue(e.detail.newFormBuildId);
      }
    };
    document.addEventListener(
      AJAX_UPDATE_FORM_BUILD_ID_EVENT,
      formBuildIdListener as unknown as EventListener,
    );
    return () => {
      document.removeEventListener(
        AJAX_UPDATE_FORM_BUILD_ID_EVENT,
        formBuildIdListener as unknown as EventListener,
      );
    };
  }, [dispatch, fieldName, formId, setInputValue]);

  // Use debounce to prevent excessive repaints of the layout if the wrapping
  // component requests it.
  const debounceStoreUpdate = shouldDebounce
    ? debounce(
        commitFormState,
        ['checkbox', 'radio'].includes(elementType as string)
          ? IMMEDIATE_TIMEOUT
          : DEBOUNCE_TIMEOUT,
      )
    : commitFormState;

  // Register the debounced store function as a callback so debouncing is
  // preserved between renders.
  const storeUpdateCallback = useCallback(
    (value: PropsValues) => debounceStoreUpdate(value),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [],
  );

  // Don't track the value of hidden fields except for form_build_id or ones
  // with the 'data-track-hidden-value' attribute set.
  if (
    ['hidden', 'submit'].includes(elementType as string) &&
    fieldName !== 'form_build_id' &&
    !attributes['data-track-hidden-value']
  ) {
    attributes.readOnly = '';
  } else if (!attributes['data-drupal-uncontrolled']) {
    // If the input is not explicitly set as uncontrolled, its state should
    // be managed by React.
    attributes.value = inputValue;

    attributes.onChange = (e: React.ChangeEvent) => {
      delete attributes['data-invalid-prop-value'];

      const formId = attributes['data-form-id'] as FormId;
      if (formId) {
        dispatch(
          clearFieldError({
            formId,
            fieldName,
          }),
        );
      }

      const newValue = parseNewValue(e);
      // Update the value of the input in the local state.
      setInputValue(newValue);

      // The data-canvas-no-update indicates we should return early and not update the
      // store.
      if (
        typeof e?.target?.hasAttribute === 'function' &&
        e.target.hasAttribute('data-canvas-no-update')
      ) {
        return;
      }
      // Update the value of the input in the Redux store.
      if (formId) {
        dispatch(
          setFieldValue({
            formId,
            fieldName,
            value: newValue,
          }),
        );
      }

      // Check the current value against the JSON Schema definition for the
      // prop. If the value is invalid, we return early and skip updating the
      // store.
      if (
        fieldName &&
        (newValue || newValue === '') &&
        e.target instanceof HTMLInputElement &&
        e.target.form instanceof HTMLFormElement
      ) {
        const validationResult = validateNewValue(e, newValue);
        if (!validationResult.valid && formId) {
          dispatch(
            setFieldError({
              type: 'error',
              message:
                validationResult.errorMessage ||
                ajv.errorsText(validationResult.errors),
              formId,
              fieldName,
            }),
          );
          if (!validationResult?.skipEarlyReturn) {
            return;
          }
        }
      }

      storeUpdateCallback({ ...formValues, [fieldName]: newValue });
    };

    attributes.onBlur = (e: React.FocusEvent) => {
      const validationResult = validateNewValue(e, inputValue);
      if (!validationResult.valid) {
        if (formId) {
          attributes['data-invalid-prop-value'] = 'true';
          dispatch(
            setFieldError({
              type: 'error',
              message:
                validationResult.errorMessage ||
                ajv.errorsText(validationResult.errors),
              formId,
              fieldName,
            }),
          );
        }
      }
    };
  }

  // React objects to inputs with the value attribute set if there are no
  // event handlers added via on* attributes.
  const hasListener = Object.keys(attributes).some((key) =>
    /^on[A-Z]/.test(key),
  );

  // The value attribute can remain for hidden and submit inputs, but
  // otherwise dispose of `value`.
  if (!hasListener && !['hidden', 'submit'].includes(elementType as string)) {
    delete attributes.value;
  }

  return (
    <>
      <OriginalInput {...passProps} attributes={attributes} options={options} />
      {fieldError && (
        <span data-prop-message>
          {`${fieldError.type === 'error' ? '❌ ' : ''}${fieldError.message}`}
        </span>
      )}
    </>
  );
};

// Provides a higher order component to wrap a form element that will map to
// a more specific higher order component depending on the element's form ID.
const InputBehaviors = (OriginalInput: React.FC) => {
  const InputBehaviorsWrapper: React.FC<React.ComponentProps<any>> = (
    props,
  ) => {
    const { attributes } = props;
    const formId = attributes['data-form-id'] as FormId;
    const FORM_INPUT_BEHAVIORS: Record<FormId, InputBehaviorsForm> = {
      [FORM_TYPES.COMPONENT_INSTANCE_FORM]: InputBehaviorsComponentPropsForm,
      [FORM_TYPES.ENTITY_FORM]: InputBehaviorsEntityForm,
    };

    if (formId === undefined) {
      // This is not one of the forms we manage, e.g. the media library form
      // popup.
      return <OriginalInput {...props} />;
    }
    if (!(formId in FORM_INPUT_BEHAVIORS)) {
      throw new Error(`No input behavior defined for form ID: ${formId}`);
    }
    return FORM_INPUT_BEHAVIORS[formId](OriginalInput, props);
  };

  return InputBehaviorsWrapper;
};

export default InputBehaviors;
