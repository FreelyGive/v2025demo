import clsx from 'clsx';
import { ZoomInIcon } from '@radix-ui/react-icons';
import { Flex, Select } from '@radix-ui/themes';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import {
  scaleValues,
  selectEditorViewPortScale,
  setEditorFrameViewPort,
} from '@/features/ui/uiSlice';

import styles from '@/components/zoom/ZoomControl.module.css';

const ZoomControl = (props: { buttonClass: string }) => {
  const { buttonClass } = props;
  const dispatch = useAppDispatch();
  const editorViewPortScale = useAppSelector(selectEditorViewPortScale);

  const handleValueChange = (value: string) => {
    dispatch(
      setEditorFrameViewPort({
        scale: scaleValues.find((sv) => value === sv.percent)?.scale,
      }),
    );
  };

  return (
    <Select.Root
      defaultValue="100%"
      // @ts-ignore - setting value to null when scale doesn't match unsets the selected value.
      value={
        scaleValues.find((sv) => sv.scale === editorViewPortScale)?.percent ||
        null
      }
      size="1"
      onValueChange={handleValueChange}
    >
      <Select.Trigger
        variant="surface"
        color="gray"
        aria-label="Select zoom level"
        className={buttonClass}
      >
        <Flex
          as="span"
          align="center"
          gap="2"
          className={styles.zoomControlSelect}
        >
          <ZoomInIcon />
          {Math.round(editorViewPortScale * 100)}%
        </Flex>
      </Select.Trigger>
      <Select.Content position="popper" data-testid="zoom-select-menu">
        {scaleValues.map((sv) => (
          <Select.Item
            key={sv.scale}
            value={sv.percent}
            className={clsx({
              [styles.oneHundred]: sv.scale === 1,
            })}
          >
            {sv.percent}
          </Select.Item>
        ))}
      </Select.Content>
    </Select.Root>
  );
};

export default ZoomControl;
