import { useAppDispatch, useAppSelector } from '@/app/hooks';
import { InputBehaviorsCommon } from '@/components/form/inputBehaviors';
import { FORM_TYPES } from '@/features/form/constants';
import { selectFormValues } from '@/features/form/formStateSlice';
import { setUpdatePreview } from '@/features/layout/layoutModelSlice';
import {
  externalUpdateComplete,
  selectPageData,
  setPageData,
} from '@/features/pageData/pageDataSlice';
import { selectLatestUndoRedoActionId } from '@/features/ui/uiSlice';

import type * as React from 'react';
import type { PropsValues } from '@/types/Form';

// Provides a higher order component to wrap a form element that is part of the
// entity fields form.
export const InputBehaviorsEntityForm = (
  OriginalInput: React.FC,
  props: React.ComponentProps<any>,
): React.ReactElement => {
  const dispatch = useAppDispatch();
  const pageData = useAppSelector(selectPageData);
  const latestUndoRedoActionId = useAppSelector(selectLatestUndoRedoActionId);
  const formState = useAppSelector((state) =>
    selectFormValues(state, FORM_TYPES.ENTITY_FORM),
  );

  const { attributes } = props;
  const fieldName = attributes.name || attributes['data-canvas-name'];
  if (!['changed', 'externalUpdates'].includes(fieldName)) {
    let newValue = pageData[fieldName] || null;

    if (attributes.name === 'form_build_id' && 'form_build_id' in formState) {
      // We always take the latest form_build_id value from form state.
      // We have an event listener in the generic inputBehaviors to react to
      // the update_build_id Ajax command, but that event can fire while the
      // input is not yet mounted, which can result in a stale form_build_id
      // being used.
      newValue = formState.form_build_id;
    }

    const elementType = attributes.type || attributes['data-canvas-type'];
    if (!['radio', 'hidden', 'submit'].includes(elementType as string)) {
      attributes.value = newValue;
    }
    if (elementType === 'checkbox') {
      if (typeof newValue === 'undefined' || newValue === null) {
        attributes.checked = !!attributes?.checked;
      } else {
        attributes.checked = Boolean(Number(newValue));
      }
    }
  }

  const formStateToStore = (newFormState: PropsValues) => {
    const values = Object.keys(newFormState).reduce(
      (acc: Record<string, any>, key) => {
        if (
          !['changed', 'formId', 'formType', 'externalUpdates'].includes(key)
        ) {
          return { ...acc, [key]: newFormState[key] };
        }
        return acc;
      },
      {},
    );
    // Flag that we need to update the preview.
    dispatch(setUpdatePreview(true));
    dispatch(setPageData(values));
  };

  const parseNewValue = (e: React.ChangeEvent) => {
    const target = e.target as HTMLInputElement;
    // If the target is an input element, return its value
    if (target.value !== undefined) {
      // We have a special case for `_none`, which represents an empty value in a
      // select element. It is converted to an empty string so it can leverage
      // the logic for textfields where an empty string results in the prop
      // being removed from the model.
      return target.value === '_none' ? null : target.value;
    }
    // If the target is a checkbox or radio button, return its checked
    if ('checked' in target) {
      return target.checked;
    }
    // If the target is neither an input element nor a checkbox/radio button, return null
    return null;
  };

  const validateNewValue = (e: React.ChangeEvent, newValue: any) => {
    if (!(e.target instanceof HTMLInputElement)) {
      return { valid: true, errors: null };
    }

    // For the page data form, we use native HTML5 validation, but the messages
    // are displayed by the same component that renders JSON Schema errors in
    // the component instance form.
    if (!e.target.checkValidity()) {
      const inputElement = e.target;
      const requiredAndOnlyProblemIsEmpty =
        inputElement.required &&
        Object.keys(inputElement.validity).every((validityProperty: string) =>
          ['valueMissing'].includes(validityProperty)
            ? inputElement.validity[validityProperty as keyof ValidityState]
            : !inputElement.validity[validityProperty as keyof ValidityState],
        );
      return {
        valid: false,
        errorMessage: e.target.validationMessage,
        skipEarlyReturn: requiredAndOnlyProblemIsEmpty,
      };
    }

    return { valid: true, errors: null };
  };

  const forceUpdateInputValue = (fieldName: string, theNewValue: string) => {
    dispatch(externalUpdateComplete(fieldName));
    const syntheticEvent = {
      target: {
        name: fieldName,
        value: theNewValue,
      },
    } as unknown as React.ChangeEvent<HTMLInputElement>;

    // Ignore TS to avoid adding several properties that are not needed for the
    // way that the onChange handler is used.
    // @ts-ignore
    attributes?.onChange?.(syntheticEvent);
  };

  // If this field value is being externally updated (not by interaction with
  // the form), we need to ensure the form input reflects that new value.
  if (
    pageData &&
    pageData?.externalUpdates &&
    pageData.externalUpdates.includes(fieldName) &&
    pageData[fieldName]
  ) {
    setTimeout(() => {
      forceUpdateInputValue(fieldName, pageData[fieldName]);
    });
  }

  return (
    <InputBehaviorsCommon
      key={`${attributes?.name}-${latestUndoRedoActionId}`}
      OriginalInput={OriginalInput}
      props={props}
      callbacks={{
        commitFormState: formStateToStore,
        parseNewValue,
        validateNewValue,
      }}
    />
  );
};
