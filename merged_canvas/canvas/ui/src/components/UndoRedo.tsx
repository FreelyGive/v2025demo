import { useEffect } from 'react';
import { useHotkeys } from 'react-hotkeys-hook';
import { ResetIcon } from '@radix-ui/react-icons';
import { Button } from '@radix-ui/themes';

import { useUndoRedo } from '@/hooks/useUndoRedo';

import styles from '@/components/topbar/Topbar.module.css';

const UndoRedo = () => {
  const { isUndoable, isRedoable, dispatchUndo, dispatchRedo } = useUndoRedo();

  // The useHotKeys hook listens to the parent document.
  useHotkeys('mod+z', () => dispatchUndo()); // 'mod' listens for cmd on Mac and ctrl on Windows.
  useHotkeys(['meta+shift+z', 'ctrl+y'], () => dispatchRedo()); // Mac redo is cmd+shift+z, Windows redo is ctrl+y.

  // Add an event listener for a message from the iFrame that a user used hot keys for undo/redo
  // while inside the iFrame.
  useEffect(() => {
    function dispatchUndoRedo(event: MessageEvent) {
      if (event.data === 'dispatchUndo') {
        dispatchUndo();
      }
      if (event.data === 'dispatchRedo') {
        dispatchRedo();
      }
    }
    window.addEventListener('message', dispatchUndoRedo);
    return () => {
      window.removeEventListener('message', dispatchUndoRedo);
    };
  });

  return (
    <>
      <Button
        variant="ghost"
        color="gray"
        size="1"
        className={styles.topBarButton}
        onClick={() => dispatchUndo()}
        disabled={!isUndoable}
        aria-label="Undo"
      >
        <ResetIcon height="16" width="auto" />
      </Button>
      <Button
        variant="ghost"
        color="gray"
        size="1"
        className={styles.topBarButton}
        onClick={() => dispatchRedo()}
        disabled={!isRedoable}
        aria-label="Redo"
      >
        <ResetIcon
          height="16"
          width="auto"
          style={{ transform: 'scaleX(-1)' }}
        />
      </Button>
    </>
  );
};

export default UndoRedo;
