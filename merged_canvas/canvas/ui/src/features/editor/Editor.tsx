import { useEffect } from 'react';
import { useParams } from 'react-router';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import ErrorBoundary from '@/components/error/ErrorBoundary';
import ExtensionDialog from '@/components/extensions/ExtensionDialog';
import ContextualPanel from '@/components/panel/ContextualPanel';
import PrimaryPanel from '@/components/sidePanel/PrimaryPanel';
import CodeComponentDialogs from '@/features/code-editor/dialogs/CodeComponentDialogs';
import ConflictWarning from '@/features/editor/ConflictWarning';
import EditorFrame from '@/features/editorFrame/EditorFrame';
import { selectLatestError } from '@/features/error-handling/queryErrorSlice';
import Layout from '@/features/layout/Layout';
import { setUpdatePreview } from '@/features/layout/layoutModelSlice';
import TemplateLayout from '@/features/layout/TemplateLayout';
import PatternDialogs from '@/features/pattern/PatternDialogs';
import {
  setEditorFrameContext,
  setFirstLoadComplete,
  unsetEditorFrameContext,
} from '@/features/ui/uiSlice';
import useLayoutWatcher from '@/hooks/useLayoutWatcher';
import useReturnableLocation from '@/hooks/useReturnableLocation';
import useSyncParamsToState from '@/hooks/useSyncParamsToState';
import { useUndoRedo } from '@/hooks/useUndoRedo';

import type { EditorFrameContext } from '@/features/ui/uiSlice';

import styles from './Editor.module.css';

interface EditorProps {
  context: EditorFrameContext;
}

const Editor: React.FC<EditorProps> = ({ context }) => {
  const dispatch = useAppDispatch();
  useLayoutWatcher();
  useSyncParamsToState();
  useReturnableLocation();
  const { isUndoable, dispatchUndo } = useUndoRedo();
  const latestError = useAppSelector(selectLatestError);
  const params = useParams();

  useEffect(() => {
    dispatch(setEditorFrameContext(context));
    return () => {
      dispatch(setFirstLoadComplete(false));
      dispatch(unsetEditorFrameContext());
    };
  }, [context, dispatch]);

  useEffect(() => {
    dispatch(setUpdatePreview(false));
    // When the entityId or entityType changes, we want to reset the first load complete state
    dispatch(setFirstLoadComplete(false));
  }, [dispatch, params.entityId, params.entityType]);

  if (latestError) {
    if (latestError.status === '409') {
      // There has been an editing conflict and the user should be blocked from continuing!
      return <ConflictWarning />;
    }
  }

  return (
    <>
      <PrimaryPanel />
      <ErrorBoundary
        title="An unexpected error has occurred while fetching the layout."
        variant="alert"
        onReset={isUndoable ? dispatchUndo : undefined}
        resetButtonText={isUndoable ? 'Undo last action' : undefined}
      >
        {context === 'entity' && <Layout />}
        {context === 'template' && <TemplateLayout />}
      </ErrorBoundary>
      <EditorFrame />
      <ContextualPanel context={context} />
      <div className={styles.absoluteContainer}>
        <PatternDialogs />
        <CodeComponentDialogs />
        <ExtensionDialog />
      </div>
    </>
  );
};

export default Editor;
