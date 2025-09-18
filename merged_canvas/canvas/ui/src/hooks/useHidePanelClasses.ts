import { useMemo } from 'react';

import { useAppSelector } from '@/app/hooks';
import { selectActivePanel } from '@/features/ui/primaryPanelSlice';
import { selectEditorFrameMode } from '@/features/ui/uiSlice';

import styles from '@/components/Panel.module.css';

function useHidePanelClasses(side: 'left' | 'right'): string[] {
  const editorFrameMode = useAppSelector(selectEditorFrameMode);
  const activePanel = useAppSelector(selectActivePanel);

  return useMemo(() => {
    if (
      side === 'left' &&
      (editorFrameMode === 'interactive' || !activePanel)
    ) {
      return [styles.offLeft, styles.animateOff];
    }
    if (side === 'right' && editorFrameMode === 'interactive') {
      return [styles.offRight, styles.animateOff];
    }
    return [styles.animateOff];
  }, [activePanel, editorFrameMode, side]);
}

export default useHidePanelClasses;
