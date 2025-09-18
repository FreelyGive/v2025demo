import { useEffect, useMemo, useRef } from 'react';
import { useBlocker } from 'react-router-dom';
import { toast } from 'sonner';

import { useAppSelector } from '@/app/hooks';
import { selectStatus } from '@/features/code-editor/codeEditorSlice';

import styles from '@/components/SavingOverlay.module.css';

const SavingOverlay = () => {
  const { isCompiling, isSaving, hasUnsavedChanges } =
    useAppSelector(selectStatus);
  const toastIdRef = useRef<string | number>('');
  const isProcessing = useMemo(() => {
    return isCompiling || isSaving || hasUnsavedChanges;
  }, [isCompiling, isSaving, hasUnsavedChanges]);

  const blocker = useBlocker(isProcessing);

  useEffect(() => {
    if (blocker.state === 'blocked' && isProcessing) {
      // Show toast when blocked and processing.
      toastIdRef.current = toast.loading('Saving changes');
    } else {
      // Dismiss toast and proceed with navigation.
      toast.dismiss(toastIdRef.current);
      toastIdRef.current = '';
      blocker.proceed?.();
    }
  }, [blocker, isProcessing]);

  // Only show overlay if blocked and processing.
  return blocker.state === 'blocked' && isProcessing ? (
    <div className={styles.overlay} />
  ) : null;
};

export default SavingOverlay;
