/**
 * @file
 *
 * Compiles source code of a code component and global asset library.
 *
 * @see docs/react-codebase/code-editor.md
 *
 * Responsibilities:
 * - Extract class name candidates from the component's JS code.
 * - Add them to the global asset library's JS comment to serve as an index
 *   for the Tailwind CSS class name candidates.
 * - Build the global CSS with Tailwind CSS using the class name candidates.
 * - Compile the component's JS code.
 * - Compile the component's JS code for previewing slot examples in the
 *   code editor's preview.
 * - Compile the component's own CSS.
 * - Save everything to the Redux store.
 */

import { useEffect, useRef, useState } from 'react';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import {
  selectCodeComponentProperty,
  selectGlobalAssetLibraryProperty,
  selectStatus,
  setCodeComponentProperty,
  setGlobalAssetLibraryProperty,
  setPreviewCompiledJsForSlots,
  setStatus,
} from '@/features/code-editor/codeEditorSlice';
import useCompileCss from '@/features/code-editor/hooks/useCompileCss';
import useCompileJavaScript from '@/features/code-editor/hooks/useCompileJavaScript';
import {
  detectValidPropOrSlotChange,
  getJsForSlotsPreview,
} from '@/features/code-editor/utils';
import { upsertClassNameCandidatesInComment } from '@/features/code-editor/utils/classNameCandidates';

import type {
  CodeComponentProp,
  CodeComponentSlot,
} from '@/types/CodeComponent';

const useSourceCode = (requestedComponentId: string): void => {
  const dispatch = useAppDispatch();
  const lastInvocationTimeoutRef = useRef<NodeJS.Timeout | null>(null);
  const globalSourceCodeJSRef = useRef<string>('');
  const hasCompiledOnceRef = useRef(false);
  const needsAutoSaveOnFirstCompilationRef = useRef(false);

  const { needsAutoSaveOnFirstCompilation } = useAppSelector(selectStatus);

  const {
    extractClassNameCandidates,
    buildTailwindCssFromClassNameCandidates,
    transformCss,
  } = useCompileCss();
  const { isJavaScriptCompilerReady, compileJavaScript } =
    useCompileJavaScript();

  const componentId = useAppSelector(
    selectCodeComponentProperty('machineName'),
  );

  const sourceCodeJS = useAppSelector(
    selectCodeComponentProperty('sourceCodeJs'),
  );
  const sourceCodeCSS = useAppSelector(
    selectCodeComponentProperty('sourceCodeCss'),
  );
  const props = useAppSelector(selectCodeComponentProperty('props'));
  const slots = useAppSelector(selectCodeComponentProperty('slots'));
  const globalSourceCodeCSS = useAppSelector(
    selectGlobalAssetLibraryProperty(['css', 'original']),
  );
  const globalSourceCodeJS = useAppSelector(
    selectGlobalAssetLibraryProperty(['js', 'original']),
  );

  // Keep track of values in refs which we need in the effect that compiles the
  // source code, and which we also update in the same effect. Adding them as
  // dependencies would re-trigger the effect once they're updated.
  useEffect(() => {
    needsAutoSaveOnFirstCompilationRef.current =
      needsAutoSaveOnFirstCompilation;
  }, [needsAutoSaveOnFirstCompilation]);
  useEffect(() => {
    globalSourceCodeJSRef.current = globalSourceCodeJS;
  }, [globalSourceCodeJS]);

  const [hasCompiledOnce, setHasCompiledOnce] = useState(false);
  useEffect(() => {
    hasCompiledOnceRef.current = hasCompiledOnce;
  }, [hasCompiledOnce]);

  // Track the last source code values to detect changes
  const lastSourceCodeJSRef = useRef<string>('');
  const lastSourceCodeCSSRef = useRef<string>('');
  const lastGlobalSourceCodeCSSRef = useRef<string>('');
  const lastGlobalSourceCodeJSRef = useRef<string>('');
  const lastPropsRef = useRef<CodeComponentProp[]>([]);
  const lastSlotsRef = useRef<CodeComponentSlot[]>([]);

  // Set hasUnsavedChanges flag whenever source code changes
  useEffect(() => {
    const propsChanged = detectValidPropOrSlotChange(
      props,
      lastPropsRef.current,
    );
    const slotsChanged = detectValidPropOrSlotChange(
      slots,
      lastSlotsRef.current,
    );

    const sourceCodeChanged =
      sourceCodeJS !== lastSourceCodeJSRef.current ||
      sourceCodeCSS !== lastSourceCodeCSSRef.current ||
      globalSourceCodeCSS !== lastGlobalSourceCodeCSSRef.current ||
      globalSourceCodeJS !== lastGlobalSourceCodeJSRef.current ||
      propsChanged ||
      slotsChanged;
    if (
      requestedComponentId === componentId &&
      componentId &&
      sourceCodeChanged
    ) {
      dispatch(setStatus({ hasUnsavedChanges: true }));
      // Update the reference values
      lastSourceCodeJSRef.current = sourceCodeJS;
      lastSourceCodeCSSRef.current = sourceCodeCSS;
      lastGlobalSourceCodeCSSRef.current = globalSourceCodeCSS;
      lastGlobalSourceCodeJSRef.current = globalSourceCodeJS;
      lastPropsRef.current = props;
      lastSlotsRef.current = slots;
    }
  }, [
    sourceCodeJS,
    sourceCodeCSS,
    componentId,
    requestedComponentId,
    dispatch,
    globalSourceCodeCSS,
    globalSourceCodeJS,
    props,
    slots,
  ]);

  useEffect(() => {
    if (
      requestedComponentId !== componentId ||
      !componentId ||
      !isJavaScriptCompilerReady
    ) {
      setHasCompiledOnce(false);
      return;
    }

    const compile = async () => {
      // Extract class name candidates from the component's JS code.
      const classNameCandidates = extractClassNameCandidates(sourceCodeJS);
      // Add it to our globally tracked index of class name candidates, which
      // are extracted from all code components. They're stored as a JS comment
      // in the global asset library.
      // @see ui/src/features/code-editor/utils/classNameCandidates.ts
      const { nextSource: globalJSClassNameIndex, nextClassNameCandidates } =
        upsertClassNameCandidatesInComment(
          globalSourceCodeJSRef.current,
          componentId,
          classNameCandidates,
        );
      // Build Tailwind CSS from the class name candidates. This will be our
      // global CSS. The global CSS source is the Tailwind CSS configuration, but
      // it can also contain arbitrary CSS.
      const { css: globalCompiledCss, error: compiledTailwindError } =
        await buildTailwindCssFromClassNameCandidates(
          nextClassNameCandidates,
          globalSourceCodeCSS,
        );
      // Compile the component's JS code.
      const { code: compiledJs, error: compiledJsError } = compileJavaScript(
        sourceCodeJS,
        `The component ${componentId} failed to compile.`,
      );
      // Compile the component's JS code for previewing slot examples in the
      // code editor's preview.
      const { code: compiledJsForSlots, error: compiledJsForSlotsError } =
        compileJavaScript(getJsForSlotsPreview(slots));
      // Compile the component's own CSS.
      const compiledCss = await transformCss(sourceCodeCSS);

      // Save everything to the Redux store.
      // (These updates are automatically batched since React 18+.)

      // Set the code editor needing to auto-save if it's already set to
      // auto-save or if it's not the first compilation.
      const needsAutoSave =
        needsAutoSaveOnFirstCompilationRef.current ||
        hasCompiledOnceRef.current;
      setHasCompiledOnce(true);

      dispatch(
        setGlobalAssetLibraryProperty([
          'css',
          'compiled',
          globalCompiledCss,
          { needsAutoSave },
        ]),
      );
      dispatch(
        setGlobalAssetLibraryProperty([
          'js',
          'original',
          globalJSClassNameIndex,
          { needsAutoSave },
        ]),
      );
      dispatch(
        setCodeComponentProperty([
          'compiledCss',
          compiledCss,
          { needsAutoSave },
        ]),
      );
      dispatch(
        setCodeComponentProperty(['compiledJs', compiledJs, { needsAutoSave }]),
      );
      dispatch(setPreviewCompiledJsForSlots(compiledJsForSlots));
      dispatch(
        setStatus({
          compilationError:
            !!compiledJsError ||
            !!compiledJsForSlotsError ||
            !!compiledTailwindError,
          isCompiling: false,
        }),
      );
    };

    if (lastInvocationTimeoutRef.current) {
      clearTimeout(lastInvocationTimeoutRef.current);
    }
    lastInvocationTimeoutRef.current = setTimeout(
      () => {
        dispatch(setStatus({ isCompiling: true }));
        void compile();
      },
      hasCompiledOnceRef.current ? 1000 : 0,
    );

    return () => {
      if (lastInvocationTimeoutRef.current) {
        clearTimeout(lastInvocationTimeoutRef.current);
      }
    };
  }, [
    buildTailwindCssFromClassNameCandidates,
    compileJavaScript,
    componentId,
    dispatch,
    extractClassNameCandidates,
    globalSourceCodeCSS,
    isJavaScriptCompilerReady,
    requestedComponentId,
    slots,
    sourceCodeCSS,
    sourceCodeJS,
    transformCss,
  ]);
};

export default useSourceCode;
