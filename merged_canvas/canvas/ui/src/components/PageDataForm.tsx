import React, { useEffect, useRef, useState } from 'react';
import { useErrorBoundary } from 'react-error-boundary';
import { Box, Spinner } from '@radix-ui/themes';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import twigToJSXComponentMap from '@/components/form/twig-to-jsx-component-map';
import { FORM_TYPES } from '@/features/form/constants';
import { selectFormValues } from '@/features/form/formStateSlice';
import { setUpdatePreview } from '@/features/layout/layoutModelSlice';
import { selectPageData, setPageData } from '@/features/pageData/pageDataSlice';
import { useDrupalBehaviors } from '@/hooks/useDrupalBehaviors';
import hyperscriptify from '@/local_packages/hyperscriptify';
import propsify from '@/local_packages/hyperscriptify/propsify/standard/index.js';
import { useGetPageDataFormQuery } from '@/services/pageDataForm';
import { AJAX_UPDATE_FORM_STATE_EVENT } from '@/types/Ajax';
import parseHyperscriptifyTemplate from '@/utils/parse-hyperscriptify-template';

import type { AjaxUpdateFormStateEvent } from '@/types/Ajax';

const PageDataFormRenderer = () => {
  const { currentData, error, isFetching } = useGetPageDataFormQuery();
  const pageData = useAppSelector(selectPageData);
  const { showBoundary } = useErrorBoundary();
  const [jsxFormContent, setJsxFormContent] =
    useState<React.ReactElement | null>(null);
  const dispatch = useAppDispatch();
  const formState = useAppSelector((state) =>
    selectFormValues(state, FORM_TYPES.ENTITY_FORM),
  );

  const formRef = useRef<HTMLDivElement>(null);
  useDrupalBehaviors(formRef, jsxFormContent);

  const pageDataExists = !!Object.keys(pageData).length;

  useEffect(() => {
    if (error) {
      showBoundary(error);
    }
  }, [error, showBoundary]);

  useEffect(() => {
    // If the HTML for the form has not yet loaded OR the JSON for the page data
    // has not, don't render the form.
    // Were we pulling this data *directly* from an API, doing this would be
    // best accomplished by the isLoading property provided by RTK. This serves
    // the same purpose without adding complexity to our reducers.
    if (!currentData || !pageDataExists) {
      return;
    }

    const template = parseHyperscriptifyTemplate(currentData as string);
    if (!template) {
      return;
    }

    setJsxFormContent(
      <div data-testid="canvas-page-data-form">
        {hyperscriptify(
          template,
          React.createElement,
          React.Fragment,
          twigToJSXComponentMap,
          { propsify },
        )}
      </div>,
    );
  }, [currentData, pageDataExists]);

  useEffect(() => {
    const ajaxUpdateFormStateListener: (
      e: AjaxUpdateFormStateEvent,
    ) => void = ({ detail }) => {
      const { updates, formId } = detail;
      // We only care about the entity form, not the component instance form.
      if (formId === FORM_TYPES.ENTITY_FORM) {
        if (Object.keys(updates).length === 0) {
          // Nothing has changed, no need to change the state.
          return;
        }

        // Flag that we need to update the preview.
        dispatch(setUpdatePreview(true));
        dispatch(setPageData({ ...formState, ...updates }));
      }
    };
    document.addEventListener(
      AJAX_UPDATE_FORM_STATE_EVENT,
      ajaxUpdateFormStateListener as unknown as EventListener,
    );

    return () => {
      document.removeEventListener(
        AJAX_UPDATE_FORM_STATE_EVENT,
        ajaxUpdateFormStateListener as unknown as EventListener,
      );
    };
  }, [formState, dispatch]);

  return (
    <Spinner size="3" loading={isFetching}>
      {/* Add some space above the spinner. */}
      {isFetching && <Box mt="9" />}
      {/* Wrap the JSX form in a ref, so we can send it as a stable DOM element
          argument to Drupal.attachBehaviors() anytime jsxFormContent changes.
          See the useEffect just above this. */}
      <div ref={formRef}>{jsxFormContent}</div>
    </Spinner>
  );
};

export default PageDataFormRenderer;
