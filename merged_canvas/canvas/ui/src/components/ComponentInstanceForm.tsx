import React, {
  createContext,
  useContext,
  useEffect,
  useRef,
  useState,
} from 'react';
import { useErrorBoundary } from 'react-error-boundary';
import { Spinner, Text } from '@radix-ui/themes';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import { getPropsValues } from '@/components/form/formUtil';
import { syncPropSourcesToResolvedValues } from '@/components/form/InputBehaviorsComponentPropsForm';
import twigToJSXComponentMap from '@/components/form/twig-to-jsx-component-map';
import { clearFieldValues } from '@/features/form/formStateSlice';
import {
  isEvaluatedComponentModel,
  selectLayout,
  selectModel,
} from '@/features/layout/layoutModelSlice';
import { findComponentByUuid } from '@/features/layout/layoutUtils';
import {
  selectEditorFrameContext,
  selectLatestUndoRedoActionId,
  selectSelectedComponentUuid,
} from '@/features/ui/uiSlice';
import { useDrupalBehaviors } from '@/hooks/useDrupalBehaviors';
import hyperscriptify from '@/local_packages/hyperscriptify';
import propsify from '@/local_packages/hyperscriptify/propsify/standard/index.js';
import { useGetComponentsQuery } from '@/services/componentAndLayout';
import { useGetComponentInstanceFormQuery } from '@/services/componentInstanceForm';
import {
  selectUpdateComponentLoadingState,
  useUpdateComponentMutation,
} from '@/services/preview';
import { AJAX_UPDATE_FORM_STATE_EVENT } from '@/types/Ajax';
import { componentHasFieldData } from '@/types/Component';
import parseHyperscriptifyTemplate from '@/utils/parse-hyperscriptify-template';

import type {
  ComponentModel,
  EvaluatedComponentModel,
  RegionNode,
} from '@/features/layout/layoutModelSlice';
import type { EditorFrameContext } from '@/features/ui/uiSlice';
import type { AjaxUpdateFormStateEvent } from '@/types/Ajax';
import type { CanvasComponent, FieldData } from '@/types/Component';
import type { InputUIData } from '@/types/Form';
import type { TransformConfig } from '@/utils/transforms';

const TransformsContext = createContext<TransformConfig | undefined>(undefined);

export const useComponentTransforms = () => {
  return useContext(TransformsContext);
};

interface ComponentInstanceFormRendererProps {
  dynamicStaticCardQueryString: string;
  context: EditorFrameContext;
}
interface ComponentInstanceFormProps {}

const ComponentInstanceFormRenderer: React.FC<
  ComponentInstanceFormRendererProps
> = (props) => {
  const { dynamicStaticCardQueryString, context } = props;
  const { showBoundary } = useErrorBoundary();
  const selectedComponent = useAppSelector(selectSelectedComponentUuid);

  const [jsxFormContent, setJsxFormContent] =
    useState<React.ReactElement | null>(null);
  const [currentComponentId, setCurrentComponentId] = useState<string | null>(
    null,
  );
  const formRef = useRef(null);
  const selectedComponentId = selectedComponent || 'noop';
  const skip = useAppSelector((state) =>
    selectUpdateComponentLoadingState(state, selectedComponentId),
  );
  const { currentData, error, originalArgs, isFetching } =
    useGetComponentInstanceFormQuery(
      { queryString: dynamicStaticCardQueryString, type: context },
      {
        skip,
      },
    );
  const model = useAppSelector(selectModel);
  const { data: components } = useGetComponentsQuery();
  const layout = useAppSelector(selectLayout);
  const node = findComponentByUuid(layout, selectedComponentId);
  const [selectedComponentType, version] = (
    node ? (node.type as string) : 'noop'
  ).split('@');
  const inputAndUiData: InputUIData = {
    selectedComponent: selectedComponentId,
    components,
    selectedComponentType,
    layout,
    node,
    model,
    version,
  };
  const [patchComponent] = useUpdateComponentMutation({
    fixedCacheKey: selectedComponentId,
  });

  useEffect(() => {
    if (error) {
      showBoundary(error);
    }
  }, [error, showBoundary]);

  const { html, transforms } = currentData || { html: false, transforms: {} };

  const persistentTransforms = useRef<undefined | TransformConfig>(undefined);

  useEffect(() => {
    if (transforms) {
      persistentTransforms.current = transforms;
    }
  }, [transforms]);

  useEffect(() => {
    if (!html) {
      return;
    }
    const template = parseHyperscriptifyTemplate(html as string);
    if (!template) {
      return;
    }
    // While we have `selectedComponent` and `latestUndoRedoActionId` in the
    // Redux store, we can't rely on those values here, because if they are added
    // as a dependency of this `useEffect` hook, they will cause a re-render
    // using stale data from the Redux Toolkit Query hook — the API call.
    // Instead we rely on fresh data from RTK Query to re-render, and we grab
    // the values from the arg that was passed to the API call which produced
    // the current data.
    const originalUrlSearchParams = new URLSearchParams(
      originalArgs?.queryString,
    );
    const componentId = originalUrlSearchParams.get('form_canvas_selected');
    const latestUndoRedoActionId = originalUrlSearchParams.get(
      'latestUndoRedoActionId',
    );
    setCurrentComponentId(componentId);

    setJsxFormContent(
      // Wrapping the constructed `ReactElement` for the form so we can add a
      // key which tells React when to re-render this subtree. The component ID
      // is granular enough. Using the entire value of
      // `dynamicStaticCardQueryString` would cause the form to re-render while
      // prop values are being updated by the user in the contextual panel,
      // causing the form to lose focus.
      // A `<div>` is used instead of `React.Fragment` so a test ID can be added.
      <div
        key={`${componentId}-${latestUndoRedoActionId}`}
        data-testid={`canvas-component-form-${componentId}`}
      >
        {hyperscriptify(
          template,
          React.createElement,
          React.Fragment,
          twigToJSXComponentMap,
          { propsify },
        )}
      </div>,
    );
  }, [html, originalArgs]);

  // Listen for updates to form state from ajax.
  useEffect(() => {
    const ajaxUpdateFormStateListener: (
      e: AjaxUpdateFormStateEvent,
    ) => void = ({ detail }) => {
      const { updates, formId } = detail;
      // We only care about the component instance form, not the entity form.
      if (formId === 'component_instance_form') {
        // Apply transforms for form state.
        const { propsValues: values, selectedModel } = getPropsValues(
          updates,
          inputAndUiData,
          currentData ? currentData.transforms : {},
        );

        if (Object.keys(values).length === 0) {
          // Nothing has changed, no need to patch.
          return;
        }
        // And then send data to backend - this will:
        // a) Trigger server side validation/transformation
        // b) Update both the preview and the model - see the pessimistic update
        //    in onQueryStarted in preview.ts
        const resolved = { ...selectedModel.resolved, ...values };
        const component = components?.[selectedComponentType];
        if (isEvaluatedComponentModel(selectedModel) && component) {
          patchComponent({
            type: context,
            componentInstanceUuid: selectedComponentId,
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
          return;
        }
        patchComponent({
          type: context,
          componentInstanceUuid: selectedComponentId,
          componentType: `${selectedComponentType}@${version}`,
          model: {
            ...selectedModel,
            resolved,
          },
        });
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
  });

  // Any time this form changes, process it through Drupal behaviors the same
  // way it would be if it were added to the DOM by Drupal AJAX. This allows
  // Drupal functionality like Autocomplete work in this React-rendered form.
  useDrupalBehaviors(formRef, jsxFormContent);

  return (
    <Spinner
      size="3"
      // Display the spinner only when a new component is being fetched.
      loading={isFetching && currentComponentId !== selectedComponent}
    >
      {/* Wrap the JSX form in a ref, so we can send it as a stable DOM element
          argument to Drupal.attachBehaviors() anytime jsxFormContent changes.
          See the useEffect just above this. */}
      {/* Don't accept pointer events while the component is updating */}
      <div
        style={{
          pointerEvents: skip ? 'none' : 'all',
        }}
        ref={formRef}
      >
        <TransformsContext.Provider value={persistentTransforms.current}>
          {jsxFormContent}
        </TransformsContext.Provider>
      </div>
    </Spinner>
  );
};

const ComponentInstanceForm: React.FC<ComponentInstanceFormProps> = () => {
  const dispatch = useAppDispatch();
  const model = useAppSelector(selectModel);
  const layout = useAppSelector(selectLayout);
  const { data: components, error } = useGetComponentsQuery();
  const { showBoundary } = useErrorBoundary();
  const selectedComponent = useAppSelector(selectSelectedComponentUuid);
  const latestUndoRedoActionId = useAppSelector(selectLatestUndoRedoActionId);
  const context = useAppSelector(selectEditorFrameContext);

  const [dynamicStaticCardQueryString, setDynamicStaticCardQueryString] =
    useState('');
  const [emptyProp, setEmptyProp] = useState(false);
  const [componentSource, setComponentSource] = useState('');

  const buildPreparedModel = (
    model: ComponentModel,
    component: CanvasComponent,
  ): ComponentModel => {
    if (!componentHasFieldData(component)) {
      return model;
    }
    // The prepared model combines prop values from the model and prop metadata
    // from the SDC definition.
    const fieldData = component.propSources;
    const missingProps = Object.keys(fieldData).filter(
      (key) => !(key in model.resolved),
    );

    const preparedModel: EvaluatedComponentModel = {
      ...model,
    } as EvaluatedComponentModel;
    missingProps.forEach((propName: string) => {
      preparedModel.source = {
        ...preparedModel.source,
        [propName]: fieldData[propName],
      };
    });
    return preparedModel;
  };

  useEffect(() => {
    dispatch(clearFieldValues('component_instance_form'));
  }, [dispatch, selectedComponent]);

  useEffect(() => {
    if (error) {
      showBoundary(error);
    }
    if (
      !components ||
      !selectedComponent ||
      layout.filter(
        (regionNode: RegionNode) => regionNode.components.length > 0,
      ).length === 0
    ) {
      return;
    }
    const selectedModel = model[selectedComponent];
    const node = findComponentByUuid(layout, selectedComponent);
    if (!node) {
      return;
    }
    const [selectedComponentType] = node.type.split('@');

    // This is metadata about the props of the SDC being edited. This is specific
    // to the SDC *type* but unconcerned with this SDC *instance*.
    const component = components[selectedComponentType];
    const selectedComponentFieldData: FieldData = componentHasFieldData(
      component,
    )
      ? component.propSources
      : {};

    // Check if this component has any props or not.
    if (Object.keys(selectedComponentFieldData).length === 0) {
      setDynamicStaticCardQueryString('');
      setEmptyProp(true);
    } else {
      setEmptyProp(false);
    }

    const preparedModel = buildPreparedModel(selectedModel, component);

    const tree = findComponentByUuid(layout, selectedComponent);
    const query = new URLSearchParams({
      form_canvas_tree: JSON.stringify(tree),
      form_canvas_props: JSON.stringify(preparedModel),
      form_canvas_selected: selectedComponent,
      latestUndoRedoActionId,
    });
    setDynamicStaticCardQueryString(`?${query.toString()}`);
    setComponentSource(components?.[selectedComponentType]?.source || '');
  }, [
    components,
    error,
    showBoundary,
    selectedComponent,
    latestUndoRedoActionId,
    layout,
    model,
  ]);

  return (
    dynamicStaticCardQueryString && (
      <>
        <ComponentInstanceFormRenderer
          context={context}
          dynamicStaticCardQueryString={dynamicStaticCardQueryString}
        />
        {componentSource === 'Module component' && emptyProp ? (
          <Text size="4">This component has no props.</Text>
        ) : (
          ''
        )}
      </>
    )
  );
};

export default ComponentInstanceForm;
