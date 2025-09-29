import clsx from 'clsx';
import { Cross2Icon, TextIcon } from '@radix-ui/react-icons';
import { Flex, Text } from '@radix-ui/themes';

import { useAppSelector } from '@/app/hooks';
import {
  isEvaluatedComponentModel,
  selectLayout,
  selectModel,
} from '@/features/layout/layoutModelSlice';
import { findComponentByUuid } from '@/features/layout/layoutUtils';
import {
  EditorFrameContext,
  selectSelectedComponentUuid,
} from '@/features/ui/uiSlice';
import { useGetComponentsQuery } from '@/services/componentAndLayout';
import { useUpdateComponentMutation } from '@/services/preview';

import type {
  ComponentModel,
  EvaluatedComponentModel,
} from '@/features/layout/layoutModelSlice';
import type {
  CanvasComponent,
  DefaultValues,
  FieldDataItem,
} from '@/types/Component';

import styles from './LinkedFieldBox.module.css';

const LinkedFieldBox = ({
  title,
  propName,
}: {
  title: string;
  propName: string;
}) => {
  const { data: components } = useGetComponentsQuery();
  const model = useAppSelector(selectModel);
  const layout = useAppSelector(selectLayout);
  const selectedComponent = useAppSelector(selectSelectedComponentUuid);
  const selectedComponentId: string = selectedComponent || 'noop';
  const selectedModel: ComponentModel | EvaluatedComponentModel =
    model[selectedComponentId] || {};
  const node = findComponentByUuid(layout, selectedComponentId);
  const [selectedComponentType, version] = (
    node ? (node.type as string) : 'noop'
  ).split('@');
  const [patchComponent] = useUpdateComponentMutation({
    fixedCacheKey: selectedComponentId,
  });
  const unlinkField = () => {
    const component: CanvasComponent | undefined =
      components?.[selectedComponentType];
    if (!component) {
      return;
    }

    const propData: FieldDataItem | undefined =
      component.propSources?.[propName];
    if (!propData) {
      return;
    }
    const default_values: DefaultValues = propData?.default_values;
    if (isEvaluatedComponentModel(selectedModel)) {
      patchComponent({
        type: EditorFrameContext.TEMPLATE,
        componentInstanceUuid: selectedComponentId,
        componentType: `${selectedComponentType}@${version}`,
        model: {
          source: {
            ...selectedModel.source,
            [propName]: {
              expression: propData.expression,
              sourceType: propData.sourceType,
            },
          },
          resolved: {
            ...selectedModel.resolved,
            [propName]: default_values.resolved,
          },
        },
      });
    }
  };

  return (
    <Flex className={styles.wrapper} mb="4">
      <Text className={clsx(styles.linkIcon, styles.iconBox)}>
        <TextIcon />
      </Text>
      <Text className={styles.text}>{title}</Text>
      <button
        className={clsx(styles.iconBox, styles.closeIcon)}
        onClick={unlinkField}
      >
        <Cross2Icon />
      </button>
    </Flex>
  );
};

export default LinkedFieldBox;
