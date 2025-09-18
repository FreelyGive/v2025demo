import { useRef } from 'react';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import { useComponentTransforms } from '@/components/ComponentInstanceForm';
import {
  ComponentPreviewUpdateEvent,
  getPropSchemas,
  getPropsValues,
  propInputData,
  shouldSkipPropValidation,
  toPropName,
  validateProp,
} from '@/components/form/formUtil';
import {
  InputBehaviorsCommon,
  POLLED_BACKGROUND_TIMEOUT,
} from '@/components/form/inputBehaviors';
import { FORM_TYPES } from '@/features/form/constants';
import {
  selectCurrentComponent,
  selectFormValues,
} from '@/features/form/formStateSlice';
import {
  isEvaluatedComponentModel,
  selectLayout,
  selectModel,
} from '@/features/layout/layoutModelSlice';
import { findComponentByUuid } from '@/features/layout/layoutUtils';
import { setPreviewBackgroundUpdate } from '@/features/pagePreview/previewSlice';
import { selectEditorFrameContext } from '@/features/ui/uiSlice';
import { useGetComponentsQuery } from '@/services/componentAndLayout';
import { useUpdateComponentMutation } from '@/services/preview';
import { componentHasFieldData } from '@/types/Component';
import { flaggedForRemoval, parseValue } from '@/utils/function-utils';

import type {
  ComponentModels,
  ResolvedValues,
  Sources,
} from '@/features/layout/layoutModelSlice';
import type { CanvasComponent } from '@/types/Component';
import type { InputUIData, PropsValues } from '@/types/Form';

export const InputBehaviorsComponentPropsForm = (
  OriginalInput: React.FC,
  props: React.ComponentProps<any>,
): React.ReactElement => {
  /**
   * @todo #3502484 useParams() should be used here to replace getting the value from currentComponent in the formStateSlice
   * Hyperscriptify re-creates the React component for the media library when Drupal ajax completes does not wrap the
   * rendering in the correct React Router context so we can't get the selected component ID from the url in inputBehaviors.tsx.
   * We already have a workaround for this for the Redux provider, could we do the same for the React Router context?
   */
  const currentComponent = useAppSelector(selectCurrentComponent);
  const editorFrameContext = useAppSelector(selectEditorFrameContext);
  const dispatch = useAppDispatch();
  const selectedComponent = currentComponent || 'noop';
  const polledBackgroundUpdate = useRef<number | null>(null);
  const model = useAppSelector(selectModel);
  const { attributes } = props;
  const { data: components } = useGetComponentsQuery();
  const transforms = useComponentTransforms();
  const layout = useAppSelector(selectLayout);
  const node = findComponentByUuid(layout, selectedComponent);
  const [selectedComponentType, version] = (
    node ? (node.type as string) : 'noop'
  ).split('@');
  const inputAndUiData: InputUIData = {
    selectedComponent,
    components,
    selectedComponentType,
    layout,
    node,
    version,
    model,
  };
  const component = components?.[selectedComponentType];

  const [patchComponent] = useUpdateComponentMutation({
    fixedCacheKey: selectedComponent,
  });

  const fieldName = attributes.name || attributes['data-canvas-name'];
  const propName = toPropName(fieldName, selectedComponent);
  // Scalar prop-types might be able to perform real-time updates.
  const isScalarProp = ['number', 'integer', 'string', 'boolean'].includes(
    component?.propSources?.[propName]?.jsonSchema?.type as string,
  );
  // We don't debounce updates for code components where the prop is scalar -
  // but all other components/props should be debounced to avoid thrashing the
  // server with multiple PATCH requests.
  const shouldDebounce =
    !isScalarProp ||
    components?.[selectedComponentType]?.source !== 'Code component';

  const formStateToStore = (newFormState: PropsValues) => {
    // Apply (client-side) transforms for form state.
    const { propsValues: values, selectedModel } = getPropsValues(
      newFormState,
      inputAndUiData,
      transforms,
    );

    // And then send data to backend - this will:
    // a) Trigger server-side validation/transformation (massaging of widget values)
    // b) Update both the preview and the model - see the pessimistic update
    //    in onQueryStarted in preview.ts
    // @see \Drupal\Core\Field\WidgetInterface::massageFormValues()
    const resolved = { ...selectedModel.resolved, ...values };

    // Check the object for any values that are flagged for removal. Note that
    // removal flagging is not necessary for all prop types. It is used for
    // props with complex prop shapes where the empty-indicating value is nested
    // with the structure.
    Object.keys(values).forEach((prop) => {
      if (flaggedForRemoval(values[prop]) && component?.propSources?.[prop]) {
        // If the prop is optional, it can be removed.
        if (!component.propSources[prop]?.required) {
          if (isEvaluatedComponentModel(selectedModel)) {
            // The source value can also be updated to empty when permitted.
            if (!Object.isFrozen(selectedModel.source[prop])) {
              selectedModel.source[prop].value = [];
            }
          }
          resolved[prop] = [];
        } else {
          // If the prop is required, we need to set it back to the default.
          resolved[prop] = component.propSources[prop].default_values.resolved;
        }
      }
    });

    let backgroundPreviewUpdate = false;
    if (isScalarProp) {
      // Fire an event to allow listeners to attempt real-time updates.
      const PreviewUpdateEvent = new ComponentPreviewUpdateEvent(
        selectedComponent,
        propName,
        resolved[propName],
      );
      document.dispatchEvent(PreviewUpdateEvent);
      dispatch(
        // Flag if any listeners were able to perform a real-time update.
        setPreviewBackgroundUpdate(
          PreviewUpdateEvent.getPreviewBackgroundUpdate(),
        ),
      );
      backgroundPreviewUpdate = PreviewUpdateEvent.getPreviewBackgroundUpdate();
    }

    if (isEvaluatedComponentModel(selectedModel) && component) {
      const updateBackend = () => {
        patchComponent({
          type: editorFrameContext,
          componentInstanceUuid: selectedComponent,
          componentType: `${selectedComponentType}@${version}`,
          model: {
            source: syncPropSourcesToResolvedValues(
              selectedModel.source,
              component,
              resolved,
            ),
            resolved,
          },
        });
      };
      if (backgroundPreviewUpdate) {
        if (polledBackgroundUpdate.current !== null) {
          clearTimeout(polledBackgroundUpdate.current);
        }
        // If we're doing a background update, debounce that so we don't make
        // multiple requests for a single update. If we're not doing a
        // background preview update, we should schedule this immediately -
        // debouncing in InputBehaviors will handle preventing this firing too
        // many times in succession.
        polledBackgroundUpdate.current = setTimeout(
          updateBackend,
          POLLED_BACKGROUND_TIMEOUT,
        ) as any as number;
        return;
      }
      updateBackend();
      return;
    }
    patchComponent({
      type: editorFrameContext,
      componentInstanceUuid: selectedComponent,
      componentType: `${selectedComponentType}@${version}`,
      model: {
        ...selectedModel,
        resolved,
      },
    });
  };

  const formState = useAppSelector((state) =>
    selectFormValues(state, FORM_TYPES.COMPONENT_INSTANCE_FORM),
  );

  const propsOverrides: { options?: Object[] } = {};

  const { multipleInputsSingleValue } = propInputData(
    formState,
    inputAndUiData,
  );

  const parseNewValue = (e: React.ChangeEvent) => {
    const schemas = getPropSchemas(inputAndUiData);
    const rawValue = parseValue(
      (e.target as HTMLInputElement | HTMLSelectElement).value,
      e.target as HTMLInputElement,
      schemas?.[propName],
    );
    const fieldName = (e.target as HTMLInputElement | HTMLSelectElement).name;
    if (
      // If there are no transforms, we cannot use them, just return the raw
      // value. Note that the 'undefined' check here is technically not required
      // because at this point the form has loaded and the value will be
      // defined, it is required to satisfy type-checks.
      transforms === undefined ||
      Object.entries(transforms).length === 0 ||
      // Or if there are no transforms for this prop, don't bother with the
      // overhead of transforms.
      !(propName in transforms) ||
      // Or if the prop relies on multiple input fields.
      multipleInputsSingleValue.includes(propName)
    ) {
      return rawValue;
    }
    const { propsValues: values } = getPropsValues(
      { [fieldName]: rawValue },
      inputAndUiData,
      transforms,
    );
    return propName in values ? values[propName] : rawValue;
  };

  const validateNewValue = (e: React.ChangeEvent, newValue: any) => {
    const target = e.target as HTMLInputElement;
    if (
      !shouldSkipPropValidation(fieldName, target, inputAndUiData, newValue)
    ) {
      const [valid, validate] = validateProp(
        toPropName(fieldName, selectedComponent),
        newValue,
        inputAndUiData,
      );
      return {
        valid,
        errors: validate?.errors || null,
      };
    }
    return { valid: true, errors: null };
  };

  if (props.options && !props.attributes.required) {
    // If an element has a `_none` value as one of the render array options,
    // and there is no value stored for this prop, then we set that _none as the
    // selected option. This logic is only necessary in the component instance
    // form, hence it being located here.
    if (
      props.options.some((option: PropsValues) => option.value === '_none') &&
      !inputAndUiData?.model?.[currentComponent as keyof ComponentModels]
        .resolved[propName]
    ) {
      propsOverrides.options = props.options.map((option: PropsValues) =>
        option.value === '_none'
          ? { ...option, selected: true }
          : { ...option, selected: false },
      );
    }
  }

  return (
    <InputBehaviorsCommon
      OriginalInput={OriginalInput}
      props={{ ...props, ...propsOverrides }}
      shouldDebounce={shouldDebounce}
      callbacks={{
        commitFormState: formStateToStore,
        parseNewValue,
        validateNewValue,
      }}
    />
  );
};

export const syncPropSourcesToResolvedValues = (
  sources: Sources,
  component: CanvasComponent,
  resolvedValues: ResolvedValues,
): Sources => {
  if (!componentHasFieldData(component)) {
    return sources;
  }
  const fieldData = component.propSources;

  // We need to include a source entry for any props with a resolved value.
  // We don't store a source entry for empty values, so once the value is no
  // longer empty we need to populate the source data for it from the
  // prop source defaults for this component.
  const missingProps = Object.keys(fieldData).filter(
    (key) => !(key in sources) && Object.keys(resolvedValues).includes(key),
  );

  // Likewise, if a resolved value is now empty, we need to remove it from
  // the source data so it is not evaluated server side.
  const emptyProps = Object.keys(fieldData).filter(
    (key) => !Object.keys(resolvedValues).includes(key) && key in sources,
  );

  return missingProps.reduce(
    (carry: Sources, propName: string) => ({
      ...carry,
      // Add in the missing source.
      [propName]: fieldData[propName],
    }),
    Object.entries(sources).reduce((carry: Sources, [propName, source]) => {
      if (emptyProps.includes(propName)) {
        // Ignore this source as the value is now empty.
        return carry;
      }
      return {
        ...carry,
        [propName]: {
          ...source,
          // Set the value from resolved values. This might duplicate the value
          // in the resolved key for components where the source and resolved
          // values are the same, however this method is generally called before
          // a patchComponent request to Drupal which will remove values from
          // the source key if it duplicates the resolved value. So for a simple
          // component with e.g. a string property, we would have duplication
          // here but this would be removed from the model returned from Drupal
          // during patchComponent and hence the model stored in the redux store
          // after this request. For a component with an expression such as an
          // image component - at this point both resolved and source may be a
          // media entity ID. When patchComponent is called in that instance,
          // Drupal will retain the media entity ID in the source value, but
          // return the evaluated expression for the resolved values - e.g. this
          // might be the src, alt, height and width for the media entity.
          value: resolvedValues[propName],
        },
      };
    }, {}),
  );
};
