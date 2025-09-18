// cspell:ignore uuidv
import { v4 as uuidv4 } from 'uuid';
import { createSelector, createSlice } from '@reduxjs/toolkit';

import { syncPropSourcesToResolvedValues } from '@/components/form/InputBehaviorsComponentPropsForm';
import { selectEditorFrameContext } from '@/features/ui/uiSlice';
import { previewApi } from '@/services/preview';
import { componentHasFieldData } from '@/types/Component';
import {
  getCanvasSettings,
  setCanvasDrupalSetting,
} from '@/utils/drupal-globals';

import {
  findComponentByUuid,
  findNodePathByUuid,
  insertNodeAtPath,
  moveNodeToPath,
  recurseNodes,
  removeComponentByUuid,
  replaceUUIDsAndUpdateModel,
} from './layoutUtils';

import type { PayloadAction } from '@reduxjs/toolkit';
import type { StateWithHistory } from 'redux-undo';
import type { AppThunk, RootState } from '@/app/store';
import type { CanvasComponent, ComponentsList } from '@/types/Component';
import type { UUID } from '@/types/UUID';

const canvasSettings = getCanvasSettings();

export enum NodeType {
  Region = 'region',
  Component = 'component',
  Slot = 'slot',
}

export interface RegionNode {
  name: string;
  id: string;
  nodeType: NodeType.Region;
  components: ComponentNode[];
}

export interface ComponentNode {
  nodeType: NodeType.Component;
  uuid: UUID;
  type: string;
  slots: SlotNode[];
}

export interface SlotNode {
  nodeType: NodeType.Slot;
  id: string;
  name: string;
  components: ComponentNode[];
}

export type LayoutNode = RegionNode | ComponentNode | SlotNode;
export type LayoutChildNode = ComponentNode | SlotNode;

export interface RootLayoutModel {
  layout: Array<RegionNode>;
  model: ComponentModels;
}

export interface LayoutModelPiece {
  layout: ComponentNode[];
  model: ComponentModels;
}

export type ComponentModels = Record<string, ComponentModel>;

export interface LayoutModelSliceState extends RootLayoutModel {
  updatePreview: boolean;
  isInitialized?: boolean;
}

export const initialState: LayoutModelSliceState = {
  layout: [
    {
      nodeType: NodeType.Region,
      name: 'content',
      components: [],
      id: 'content',
    },
  ],
  model: {},
  updatePreview: false,
  isInitialized: false,
};

// This wrapper is necessary because when using slices with redux-undo,
// you reference state.[sliceName].present.
export interface StateWithHistoryWrapper {
  layoutModel: StateWithHistory<LayoutModelSliceState>;
}

type MoveNodePayload = {
  uuid: string | undefined;
  to: number[] | undefined;
};

type ShiftNodePayload = {
  uuid: string | undefined;
  direction: 'up' | 'down';
};

type DuplicateNodePayload = {
  uuid: string;
};

type InsertMultipleNodesPayload = {
  to: number[] | undefined;
  layoutModel: LayoutModelPiece;
  /**
   * Pass an optional UUID that will be assigned to the last, top level node being inserted. Allows you to define the UUID
   * so that you can then do something with the newly inserted node using that UUID.
   */
  useUUID?: string;
};

type AddNewNodePayload = {
  to: number[];
  component: CanvasComponent;
  withValues?: Record<string, any>;
};

type AddNewPatternPayload = {
  to: number[] | undefined;
  layoutModel: LayoutModelPiece;
};

type SortNodePayload = {
  uuid: string | undefined;
  to: number | undefined;
};

type AnyValue = string | boolean | [] | number | {} | null;

// @see \Drupal\canvas\PropSource\PropSource::parse()
interface BasePropSource {
  sourceType: string;
  value?: any;
}
// @see \Drupal\canvas\PropSource\DynamicPropSource
export interface DynamicPropSource extends BasePropSource {
  expression: string;
}

// @see \Drupal\canvas\PropSource\StaticPropSource
export interface StaticPropSource extends BasePropSource {
  expression: string;
  // This can be omitted if it duplicates the resolved value. There are some
  // scenarios where the resolved value will differ from the source value, e.g.
  // a media reference - in that case the source value will be the target ID,
  // whilst the resolved value will be the image URI or similar.
  value?: AnyValue;
  sourceTypeSettings: Record<string, AnyValue>;
}

// @see \Drupal\canvas\PropSource\AdaptedPropSource
export interface AdaptedPropSource extends BasePropSource {
  adapterInputs: Record<string, PropSource>;
}

export type PropSource =
  | AdaptedPropSource
  | StaticPropSource
  | DynamicPropSource;

export type ResolvedValues = Record<string, AnyValue>;

export interface ComponentModel {
  // The (resolved) explicit component inputs that are used to:
  // - render previews
  // - perform client-side preview updates (for ComponentSources that support this — impossible if server-rendered)
  // - populate forms (unless EvaluatedComponentModel is used)
  // TRICKY: any ComponentSource that wants to support client-side preview updates MUST ensure that `resolved` contains
  // values that are considered valid by the component's logic; otherwise rendering will fail. IOW: they must meet the
  // specified types/schema.
  // @see \Drupal\canvas\ComponentSource\ComponentSourceBase::getExplicitInputDefinitions()
  // TRICKY: conversely, this means that any ComponentSource that does not support client-side rendering is free to use
  // whichever structure it likes, as long as its ::inputToClientModel() and ::clientModelToInput() methods are each
  // others' inverses. This is why for example the `block` ComponentSource opted to transform the stored (server-side)
  // explicit inputs to the form structure for its client-side `resolved` values.
  // @see \Drupal\canvas\Plugin\Canvas\ComponentSource\BlockComponent
  // @see docs/components.md#3.2.1
  // @todo Make this less confusing in https://www.drupal.org/project/canvas/issues/3521041
  resolved: ResolvedValues;
}

export type Sources = Record<string, PropSource>;

export interface EvaluatedComponentModel extends ComponentModel {
  // The (source) explicit component inputs that are used to:
  // - populate the component instance form
  // - store the explicit inputs
  // Note: The server evaluates `source` into `resolved`.
  // (PropSources are used by ComponentSources without an explicit input UX, but only a schema — such as SDCs. The
  // schema is mapped to PropSources that are able to meet the schema expectations, and to resolve the values stored in
  // those PropSources, evaluation is needed.)
  // @see \Drupal\canvas\Plugin\Canvas\ComponentSource\GeneratedFieldExplicitInputUxComponentSourceBase
  // @see docs/components.md#3.1.1
  // @see \Drupal\canvas\Plugin\Canvas\ComponentSource\SingleDirectoryComponent
  // @see docs/components.md#3.3.1
  // @see \Drupal\canvas\Plugin\Canvas\ComponentSource\JsComponent
  source: Sources;
}

export const isEvaluatedComponentModel = (
  model: ComponentModel,
): model is EvaluatedComponentModel => {
  return 'source' in model;
};

export const layoutModelSlice = createSlice({
  name: 'layoutModel',
  initialState,
  reducers: (create) => ({
    setUpdatePreview: create.reducer(
      (state, action: PayloadAction<boolean>) => ({
        ...state,
        updatePreview: action.payload,
      }),
    ),
    deleteNode: create.reducer((state, action: PayloadAction<string>) => {
      const deletedComponent = findComponentByUuid(
        state.layout,
        action.payload,
      );

      const removableModelsUuids = [action.payload];
      if (deletedComponent) {
        recurseNodes(deletedComponent, (node: ComponentNode) => {
          removableModelsUuids.push(node.uuid);
        });
      }
      for (const uuid of removableModelsUuids) {
        if (state.model[uuid]) delete state.model[uuid];
      }

      state.layout = removeComponentByUuid(state.layout, action.payload);
      // Flag a preview update.
      state.updatePreview = true;
    }),
    duplicateNode: create.reducer(
      (state, action: PayloadAction<DuplicateNodePayload>) => {
        const { uuid } = action.payload;
        const nodeToDuplicate = findComponentByUuid(state.layout, uuid);

        if (!nodeToDuplicate) {
          console.error(`Cannot duplicate ${uuid}. Check the uuid is valid.`);
          return;
        }

        if (nodeToDuplicate.nodeType !== 'component') {
          console.error(
            `Cannot duplicate Slots or Regions. Check the uuid ${uuid} is a valid Component.`,
          );
          return;
        }

        const { updatedNode, updatedModel } = replaceUUIDsAndUpdateModel(
          nodeToDuplicate,
          state.model,
        );

        // Add the updated model to the state
        state.model = { ...state.model, ...updatedModel };

        const nodePath = findNodePathByUuid(state.layout, uuid);
        if (nodePath === null) {
          console.error(
            `Cannot find ${uuid} in layout. Check the uuid is valid.`,
          );
          return;
        }
        nodePath[nodePath.length - 1]++;
        const rootIndex = nodePath.shift();
        if (rootIndex === undefined) {
          throw new Error(
            'Path should be at least two items long, starting from the root region',
          );
        }
        const root = state.layout[rootIndex];
        const newState = state.layout;
        newState[rootIndex] = insertNodeAtPath(
          root,
          nodePath,
          updatedNode,
        ) as RegionNode;
        state.layout = newState;
        // Flag a preview update.
        state.updatePreview = true;
      },
    ),
    moveNode: create.reducer(
      (state, action: PayloadAction<MoveNodePayload>) => {
        const { uuid, to } = action.payload;
        if (!uuid || !Array.isArray(to)) {
          console.error(
            `Cannot move ${uuid} to position ${to}. Check both uuid and to are defined/valid.`,
          );
          return;
        }

        state.layout = moveNodeToPath(state.layout, uuid, to);
        // Flag a preview update.
        state.updatePreview = true;
      },
    ),
    insertNodes: create.reducer(
      (state, action: PayloadAction<InsertMultipleNodesPayload>) => {
        const { layoutModel, to, useUUID } = action.payload;

        if (!Array.isArray(to)) {
          console.error(
            `Cannot insert nodes. Invalid parameters: newNodes: ${layoutModel}, to: ${to}.`,
          );
          return;
        }

        let updatedModel: ComponentModels = { ...state.model };
        let newLayout: Array<RegionNode> = JSON.parse(
          JSON.stringify(state.layout),
        );
        const components = layoutModel.layout;
        const model = layoutModel.model;

        const rootIndex = to.shift();
        if (rootIndex === undefined) {
          throw new Error(
            'Path should be at least two items long, starting from the root region',
          );
        }
        let regionRoot = newLayout[rootIndex];

        // Loop through each node in reverse order to maintain the correct insert positions
        for (let i = components.length - 1; i >= 0; i--) {
          const node = components[i];
          const specifyUUID = i === 0;
          const { updatedNode, updatedModel: nodeUpdatedModel } =
            replaceUUIDsAndUpdateModel(
              node,
              model,
              specifyUUID ? useUUID : undefined,
            );
          updatedModel = { ...updatedModel, ...nodeUpdatedModel };
          regionRoot = insertNodeAtPath(regionRoot, to, updatedNode);
        }

        state.model = updatedModel;
        state.layout[rootIndex] = regionRoot;
        // Flag a preview update.
        state.updatePreview = true;
      },
    ),
    sortNode: create.reducer(
      (state, action: PayloadAction<SortNodePayload>) => {
        const { uuid, to } = action.payload;
        if (!uuid || to === undefined) {
          console.error(
            `Cannot sort ${uuid} to position ${to}. Check both uuid and to are defined/valid.`,
          );
          return;
        }

        const cloneNode = JSON.parse(
          JSON.stringify(findComponentByUuid(state.layout, uuid)),
        );
        const nodePath = findNodePathByUuid(state.layout, uuid);
        const rootIndex = nodePath?.shift() ?? undefined;
        if (rootIndex === undefined) {
          throw new Error(
            'Path should be at least two items long, starting from the root region',
          );
        }
        if (cloneNode && nodePath) {
          const insertPosition = [...nodePath.slice(0, -1), to];
          const newLayout = removeComponentByUuid(state.layout, uuid);

          state.layout[rootIndex] = insertNodeAtPath(
            newLayout[rootIndex],
            insertPosition,
            cloneNode,
          );
          // Flag a preview update.
          state.updatePreview = true;
        }
      },
    ),
    shiftNode: create.reducer(
      (state, action: PayloadAction<ShiftNodePayload>) => {
        const { uuid, direction } = action.payload;
        if (!uuid) {
          console.error(
            `Cannot shift ${uuid} ${direction}. Check both uuid and direction are defined/valid.`,
          );
          return;
        }

        const cloneNode = JSON.parse(
          JSON.stringify(findComponentByUuid(state.layout, uuid)),
        );
        const nodePath = findNodePathByUuid(state.layout, uuid);
        const rootIndex = nodePath?.shift() ?? undefined;
        if (rootIndex === undefined) {
          throw new Error(
            'Path should be at least two items long, starting from the root region',
          );
        }
        if (cloneNode && nodePath) {
          const newPos =
            direction === 'down'
              ? nodePath[nodePath.length - 1] + 1
              : Math.max(0, nodePath[nodePath.length - 1] - 1);
          const insertPosition = [...nodePath.slice(0, -1), newPos];
          const newLayout = removeComponentByUuid(state.layout, uuid);

          state.layout[rootIndex] = insertNodeAtPath(
            newLayout[rootIndex],
            insertPosition,
            cloneNode,
          );
          // Flag a preview update.
          state.updatePreview = true;
        }
      },
    ),
    setInitialized: create.reducer((state, action: PayloadAction<boolean>) => {
      state.isInitialized = action.payload;
    }),
    setLayoutModel: create.reducer(
      (state, action: PayloadAction<LayoutModelSliceState>) => {
        const { layout, model, updatePreview } = action.payload;
        state.layout = layout;
        state.model = model;
        state.updatePreview = updatePreview;
      },
    ),
    // Identical to setLayoutModel but with a different type for ensuring this
    // doesn't trigger an undo/redo action.
    setInitialLayoutModel: create.reducer(
      (state, action: PayloadAction<LayoutModelSliceState>) => {
        const {
          layout,
          model,
          updatePreview,
          isInitialized = true,
        } = action.payload;
        state.layout = layout;
        state.model = model;
        state.updatePreview = updatePreview;
        state.isInitialized = isInitialized;
      },
    ),
  }),
});

// This underscore-prefixed function is used internally when the `to`
// value (where it should be placed in the layout) is already known. For example
// drag and drop operations are aware of the destination coordinates, so this
// is the method called.
// The non-underscored version of this function *can* specify a destination, but
// has additional logic for determining the best destination if one is not provided.
//
// Payload properties:
// - component: The component to add to the layout.
// - to: Optional coordinates where the component should be added. If not
//       provided, they are added after the current selection.
// - withValues: Optional values to override the default values of the
//   component.
export const _addNewComponentToLayout =
  (payload: AddNewNodePayload, setSelectedComponent: Function): AppThunk =>
  (dispatch, getState) => {
    const { to, component, withValues } = payload;
    // Populate the model data with the default values
    const buildInitialData = (component: CanvasComponent): ComponentModel => {
      if (componentHasFieldData(component)) {
        const initialData: EvaluatedComponentModel = {
          resolved: {},
          source: {},
        };
        Object.keys(component.propSources).forEach((propName) => {
          const prop = component.propSources[propName];
          // These will be needed when we support client-side preview updates.
          initialData.resolved[propName] = prop.default_values?.resolved || [];
          // These are the values the server needs.
          // @todo Reduce the verbosity of this in https://drupal.org/i/3463996
          //   and https://drupal.org/i/3528043 to send less data.
          initialData.source[propName] = {
            expression: prop.expression,
            sourceType: prop.sourceType,
            value: prop.default_values?.source || [],
            sourceTypeSettings: prop.sourceTypeSettings || undefined,
          };
        });
        return initialData;
      }
      return {
        resolved: {},
      };
    };

    // This is called if withValues is not null. The withValues object
    // specifies values that should override the defaults for the newly inserted
    // component.
    const updateValues = (
      component: CanvasComponent,
      initialData: ComponentModel | EvaluatedComponentModel,
      newValues: Record<string, any>,
    ) => {
      const resolved = {
        ...initialData.resolved,
        ...newValues,
      };
      if (!isEvaluatedComponentModel(initialData)) {
        return { resolved };
      }
      return {
        source: syncPropSourcesToResolvedValues(
          initialData.source,
          component,
          resolved,
        ),
        resolved,
      };
    };

    const slots: SlotNode[] = [];
    const uuid = uuidv4();

    if (componentHasFieldData(component)) {
      // Create empty slots in the layout data for each child slot the component
      // has.
      Object.keys(component.metadata.slots || []).forEach((name) => {
        slots.push({
          id: `${uuid}/${name}`,
          name: name,
          nodeType: NodeType.Slot,
          components: [],
        });
      });
    }
    const initialData = buildInitialData(component);
    const layoutModel: LayoutModelPiece = {
      layout: [
        {
          slots,
          nodeType: NodeType.Component,
          type: `${component.id}@${component.version}`,
          uuid: uuid,
        },
      ],
      model: {
        [uuid]: withValues
          ? updateValues(component, initialData, withValues)
          : initialData,
      },
    };

    dispatch(
      insertNodes({
        to,
        layoutModel,
        useUUID: uuid,
      }),
    );

    // Get the new state immediately after the insertNode action was called so that setSelectedComponent will find
    // the newly added component.
    const updatedState = getState();
    const updatedLayout = selectLayout(updatedState);
    setSelectedComponent(uuid, updatedLayout);
  };

// This action eventually calls _addNewComponentToLayout, but first determines
// where to insert the new component based on the current selection if there
// isn't a `to` value in the payload specifying destination coordinates.
//
// Payload properties:
// - component: The component to add to the layout.
// - to: Optional coordinates where the component should be added. If not
//       provided, they are added after the current selection.
// - withValues: Optional values to override the default values of the
//   component.
export const addNewComponentToLayout =
  (
    payload: AddNewNodePayload,
    setSelectedComponent: Function = () => {},
  ): AppThunk =>
  (dispatch, getState) => {
    const state = getState();
    let to: number[] = [0, 0];
    const theLayout = selectLayout(state);
    const selectionItems = state?.ui?.selection?.items;
    // If destination coordinates are provided, use them.
    if (payload.to) {
      to = payload.to;
    } else {
      // If no destination coordinates are provided, insert the new component
      // after the current selection.
      const selectedComponent = selectionItems ? selectionItems.at(-1) : null;
      if (selectedComponent) {
        // The component should be inserted after the selected component,
        // so increase the path value if the final item by 1.
        const nodePath = findNodePathByUuid(theLayout, selectedComponent);
        if (nodePath !== null) {
          nodePath[nodePath.length - 1] += 1;
          to = nodePath;
        }
      }
    }

    dispatch(
      _addNewComponentToLayout({ ...payload, to }, setSelectedComponent),
    );
  };

export const updateExistingComponentValues =
  (payload: any): AppThunk =>
  async (dispatch, getState) => {
    const { componentSelectionUtils } = canvasSettings;
    const state = getState();
    const components: ComponentsList | undefined = state?.componentAndLayoutApi
      ?.queries?.['getComponents(undefined)']?.data as
      | ComponentsList
      | undefined;
    if (!components) {
      console.warn('No components list found, cannot update component values.');
      return;
    }

    let resetSelection: string | undefined = undefined;
    const { values, componentToUpdateId } = payload;
    const selectionItems = state?.ui?.selection?.items;

    // If the component being updated is currently selected, it is temporarily
    // deselected, then re-selected after the value update. This ensures the
    // form re-renders with the updated values.
    if (
      Array.isArray(selectionItems) &&
      selectionItems.includes(componentToUpdateId)
    ) {
      resetSelection = componentToUpdateId;
      componentSelectionUtils.setSelectedComponent(null);
    }

    const layout = selectLayout(state);
    const model = selectModel(state)[componentToUpdateId];
    const node = findComponentByUuid(layout, componentToUpdateId);
    const [selectedComponentType, version] = (
      node ? (node.type as string) : 'noop'
    ).split('@');
    const componentMetadata = components[selectedComponentType];
    Object.keys(values).forEach((key) => {
      if (!componentMetadata?.propSources?.[key]) {
        console.warn(
          `Component ${selectedComponentType} does not have a prop named ${key}. Update cancelled.`,
        );
        return;
      }
    });

    const resolved = {
      ...model.resolved,
      ...values,
    };
    // Help, how do I get the editor frame context here

    const type = selectEditorFrameContext(state);
    if (isEvaluatedComponentModel(model) && componentMetadata) {
      const valuePayload = {
        type,
        componentInstanceUuid: componentToUpdateId,
        componentType: `${selectedComponentType}@${version}`,
        model: {
          source: syncPropSourcesToResolvedValues(
            model.source,
            componentMetadata,
            resolved,
          ),
          resolved,
        },
      };
      await dispatch(
        previewApi.endpoints.updateComponent.initiate(valuePayload, {
          fixedCacheKey: componentToUpdateId,
        }),
      );
    } else {
      const valuePayload = {
        type,
        componentInstanceUuid: componentToUpdateId,
        componentType: `${selectedComponentType}@${version}`,
        model: {
          ...model,
          resolved,
        },
      };
      await dispatch(
        previewApi.endpoints.updateComponent.initiate(valuePayload, {
          fixedCacheKey: componentToUpdateId,
        }),
      );
    }
    if (resetSelection) {
      componentSelectionUtils.setSelectedComponent(resetSelection);
    }
  };
export const addNewPatternToLayout =
  (payload: AddNewPatternPayload, setSelectedComponent: Function): AppThunk =>
  (dispatch, getState) => {
    const uuid = uuidv4();

    const { to, layoutModel } = payload;

    if (!to || !layoutModel) {
      return;
    }

    dispatch(
      insertNodes({
        to,
        layoutModel,
        useUUID: uuid,
      }),
    );

    // Get the new state immediately after the insertNodes action was called so that setSelectedComponent will find
    // the newly added component.
    const updatedState = getState();
    const updatedLayout = selectLayout(updatedState);
    setSelectedComponent(uuid, updatedLayout);
  };

// Action creators are generated for each case reducer function.
export const {
  deleteNode,
  setLayoutModel,
  setInitialized,
  setInitialLayoutModel,
  duplicateNode,
  moveNode,
  shiftNode,
  sortNode,
  setUpdatePreview,
  insertNodes,
} = layoutModelSlice.actions;

export const layoutModelReducer = layoutModelSlice.reducer;

// When using redux-undo, you reference the current state by state.[sliceName].present.[targetKey].
// These selectors are written outside the slice because the type of state is different. Here, we need
// to be able to access the history, so we use the StateWithHistoryWrapper type.
export const selectLayout = (state: StateWithHistoryWrapper) =>
  state.layoutModel.present.layout;
export const selectModel = (state: StateWithHistoryWrapper) =>
  state.layoutModel.present.model;
export const selectLayoutHistory = (state: StateWithHistoryWrapper) =>
  state.layoutModel;
export const selectUpdatePreview = (state: StateWithHistoryWrapper) =>
  state.layoutModel.present.updatePreview;
export const selectIsInitialized = (state: StateWithHistoryWrapper) =>
  state.layoutModel.present.isInitialized;
const selectRegion = (state: RootState, regionName: string) => regionName;

export const selectLayoutForRegion = createSelector(
  [selectLayout, selectRegion],
  (layout: Array<RegionNode>, regionName: string) =>
    layout.find((region) => region.id === regionName) ||
    ({
      components: [],
      name: regionName,
      id: regionName,
      nodeType: 'region',
    } as RegionNode),
);

// Add some of the functionality offered here to drupalSettings, so extensions
// can use it.
const layoutUtils = {
  addNewComponentToLayout,
  addNewPatternToLayout,
  selectLayoutForRegion,
  updateExistingComponentValues,
};
setCanvasDrupalSetting('layoutUtils', layoutUtils);
