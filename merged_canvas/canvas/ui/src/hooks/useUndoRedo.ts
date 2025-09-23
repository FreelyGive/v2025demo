import { useAppDispatch, useAppSelector } from '@/app/hooks';
import {
  selectRedoType,
  selectUndoType,
  UndoRedoActionCreators,
} from '@/features/ui/uiSlice';

interface UndoRedoState {
  isUndoable: boolean;
  isRedoable: boolean;
  dispatchUndo: () => void;
  dispatchRedo: () => void;
}

export function useUndoRedo(): UndoRedoState {
  const dispatch = useAppDispatch();
  const undoType = useAppSelector(selectUndoType);
  const redoType = useAppSelector(selectRedoType);

  const dispatchUndo = () =>
    undoType ? dispatch(UndoRedoActionCreators.undo(undoType)) : null;

  const dispatchRedo = () =>
    redoType ? dispatch(UndoRedoActionCreators.redo(redoType)) : null;

  return {
    isUndoable: undoType !== undefined,
    isRedoable: redoType !== undefined,
    dispatchUndo,
    dispatchRedo,
  };
}
