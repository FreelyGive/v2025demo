import clsx from 'clsx';
import { useParams } from 'react-router';
import {
  BoxModelIcon,
  Component1Icon,
  CubeIcon,
  FileTextIcon,
} from '@radix-ui/react-icons';

import { useAppSelector } from '@/app/hooks';
import {
  DEFAULT_REGION,
  selectDragging,
  selectIsComponentHovered,
  selectNoComponentIsHovered,
  selectSelectedComponentUuid,
  selectTargetSlot,
} from '@/features/ui/uiSlice';

import styles from './NameTag.module.css';

const VARIANTS = {
  component: <Component1Icon width={10} height={10} />,
  region: <CubeIcon width={10} height={10} />,
  slot: <BoxModelIcon width={10} height={10} />,
  page: <FileTextIcon width={10} height={10} />,
};

interface NameTagProps {
  name: string;
  id: string;
  nodeType: string;
}

const NameTag: React.FC<NameTagProps> = (props) => {
  const { name, nodeType, id } = props;

  return (
    <div
      data-testid="canvas-name-tag"
      className={clsx(styles.nameTag, {
        [styles.slot]: nodeType === 'slot',
        [styles.region]: nodeType === 'region',
        [styles.page]: nodeType === 'page',
      })}
    >
      {VARIANTS[nodeType as keyof typeof VARIANTS]}
      <span id={`${id}-name`}>{name}</span>
    </div>
  );
};

export const SlotNameTag: React.FC<NameTagProps> = (props) => {
  const { name, id } = props;
  const { isDragging } = useAppSelector(selectDragging);
  const isHovered = useAppSelector((state) => {
    return selectIsComponentHovered(state, id);
  });
  const targetSlot = useAppSelector(selectTargetSlot);
  const isTarget = targetSlot === id;

  // Show the name of the slot when either the slot is hovered in the layers or when it's the target of drag and drop.
  // Desired result is that only one NameTag is shown at a time:
  // either the selected or the hovered component or, when dragging, the target slot or region.
  const showName = isTarget || (!targetSlot && isHovered && !isDragging);

  if (!showName) {
    return null;
  }
  return <NameTag name={name} id={id} nodeType="slot" />;
};

export const RegionNameTag: React.FC<NameTagProps> = (props) => {
  const { name, id } = props;
  const { isDragging } = useAppSelector(selectDragging);
  const isHovered = useAppSelector((state) => {
    return selectIsComponentHovered(state, id);
  });
  const targetSlot = useAppSelector(selectTargetSlot);
  const isTarget = targetSlot === id;
  const { regionId: focusedRegion = DEFAULT_REGION } = useParams();

  // Show the name of the region when either the region is hovered or when it's the target of drag and drop.
  // Desired result is that only one NameTag is shown at a time:
  // either the selected or the hovered component or, when dragging, the target slot or region.
  const showName =
    isTarget ||
    (!targetSlot &&
      isHovered &&
      !isDragging &&
      focusedRegion === DEFAULT_REGION);

  if (!showName) {
    return null;
  }

  return <NameTag name={name} id={id} nodeType={props.nodeType} />;
};

export const ComponentNameTag: React.FC<NameTagProps> = (props) => {
  const { name, id } = props;
  const selectedComponent = useAppSelector(selectSelectedComponentUuid);
  const { isDragging } = useAppSelector(selectDragging);
  const isSelected = id === selectedComponent;
  const isHovered = useAppSelector((state) => {
    return selectIsComponentHovered(state, id);
  });
  const noComponentIsHovered = useAppSelector(selectNoComponentIsHovered);

  // Show the name of the hovered component or selected component when nothing else is hovered. Hide when dragging
  // Desired result is that only one NameTag is shown at a time:
  // either the selected or the hovered component or, when dragging, the target slot or region.
  const showName =
    !isDragging && ((isSelected && noComponentIsHovered) || isHovered);

  if (!showName) {
    return null;
  }
  return <NameTag name={name} id={id} nodeType="component" />;
};
