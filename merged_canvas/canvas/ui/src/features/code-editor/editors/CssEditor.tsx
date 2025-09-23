import { css } from '@codemirror/lang-css';
import { githubLight } from '@uiw/codemirror-theme-github';
import CodeMirror from '@uiw/react-codemirror';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import {
  selectCodeComponentProperty,
  setCodeComponentProperty,
} from '@/features/code-editor/codeEditorSlice';

const CssEditor = ({ isLoading }: { isLoading: boolean }) => {
  const dispatch = useAppDispatch();
  const value = useAppSelector(selectCodeComponentProperty('sourceCodeCss'));

  function onChangeHandler(value: string) {
    dispatch(setCodeComponentProperty(['sourceCodeCss', value]));
  }
  if (isLoading) {
    return null;
  }
  return (
    <CodeMirror
      className="canvas-code-mirror-editor"
      value={value}
      onChange={onChangeHandler}
      theme={githubLight}
      extensions={[css()]}
    />
  );
};

export default CssEditor;
