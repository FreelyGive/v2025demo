import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { parse } from '@babel/parser';
import { Flex, ScrollArea, Spinner } from '@radix-ui/themes';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import ErrorCard from '@/components/error/ErrorCard';
import {
  clearDataFetches,
  selectCodeComponentProperty,
  selectGlobalAssetLibraryProperty,
  selectPreviewCompiledJsForSlots,
  selectStatus,
  setCodeComponentProperty,
} from '@/features/code-editor/codeEditorSlice';
import {
  getDataDependenciesFromAst,
  getImportsFromAst,
  getPropValuesForPreview,
  getSlotNamesForPreview,
} from '@/features/code-editor/utils';
import { useGetCodeComponentsQuery } from '@/services/componentAndLayout';
import {
  getBaseUrl,
  getCanvasSettings,
  getDrupal,
  getDrupalSettings,
} from '@/utils/drupal-globals';

import MissingDefaultExportMessage, {
  CodeBlock,
  TextBlock,
} from './errors/MissingDefaultExportMessage';

import type { File } from '@babel/types';

import styles from './Preview.module.css';

const Drupal = getDrupal();
const CANVAS_MODULE_UI_PATH =
  `${getBaseUrl()}${getCanvasSettings().canvasModulePath}/ui` as const;
const PREVIEW_LIB_PATH = 'dist/assets/code-editor-preview.js' as const;

const Preview = ({ isLoading = false }: { isLoading?: boolean }) => {
  const dispatch = useAppDispatch();
  const componentId = useAppSelector(
    selectCodeComponentProperty('machineName'),
  );
  const sourceCodeJs = useAppSelector(
    selectCodeComponentProperty('sourceCodeJs'),
  );
  const compiledJs = useAppSelector(selectCodeComponentProperty('compiledJs'));
  const compiledCss = useAppSelector(
    selectCodeComponentProperty('compiledCss'),
  );
  const compiledGlobalCss = useAppSelector(
    selectGlobalAssetLibraryProperty(['css', 'compiled']),
  );
  const previewCompiledJsForSlots = useAppSelector(
    selectPreviewCompiledJsForSlots,
  );
  const { compilationError } = useAppSelector(selectStatus);
  const props = useAppSelector(selectCodeComponentProperty('props'));
  const slots = useAppSelector(selectCodeComponentProperty('slots'));
  const [isDefaultExportMissingError, setIsDefaultExportMissingError] =
    useState(false);
  const iframeRef = useRef<HTMLIFrameElement>(null);
  const parentRef = useRef<HTMLDivElement>(null);
  const [isJsImportError, setIsJsImportError] = useState(false);
  const { data: codeComponents } = useGetCodeComponentsQuery();
  const [jsImportNameWithError, setJsImportNameWithError] = useState('');

  const [iframeSrcDoc, setIframeSrcDoc] = useState('');

  // @see JsComponent.php
  const importMap = useMemo(
    () => ({
      imports: {
        // Map to Canvas generated libraries.
        preact: `${CANVAS_MODULE_UI_PATH}/lib/astro-hydration/dist/preact.module.js`,
        'preact/hooks': `${CANVAS_MODULE_UI_PATH}/lib/astro-hydration/dist/hooks.module.js`,
        'react/jsx-runtime': `${CANVAS_MODULE_UI_PATH}/lib/astro-hydration/dist/jsx-runtime-default.js`,
        react: `${CANVAS_MODULE_UI_PATH}/lib/astro-hydration/dist/compat.module.js`,
        'react-dom': `${CANVAS_MODULE_UI_PATH}/lib/astro-hydration/dist/compat.module.js`,
        'react-dom/client': `${CANVAS_MODULE_UI_PATH}/lib/astro-hydration/dist/compat.module.js`,
        // @todo Remove hardcoding and allow components to nominate their own?
        clsx: `${CANVAS_MODULE_UI_PATH}/lib/astro-hydration/dist/clsx.js`,
        'class-variance-authority': `${CANVAS_MODULE_UI_PATH}/lib/astro-hydration/dist/class-variance-authority.js`,
        'tailwind-merge': `${CANVAS_MODULE_UI_PATH}/lib/astro-hydration/dist/tailwind-merge.js`,
        '@/lib/FormattedText': `${CANVAS_MODULE_UI_PATH}/lib/astro-hydration/dist/FormattedText.js`,
        'next-image-standalone': `${CANVAS_MODULE_UI_PATH}/lib/astro-hydration/dist/next-image-standalone.js`,
        '@/lib/utils': `${CANVAS_MODULE_UI_PATH}/lib/astro-hydration/dist/utils.js`,
        '@/components/': Drupal.url(
          'canvas/api/v0/auto-saves/js/js_component/',
        ),
        '@drupal-api-client/json-api-client': `${CANVAS_MODULE_UI_PATH}/lib/astro-hydration/dist/jsonapi-client.js`,
        'drupal-jsonapi-params': `${CANVAS_MODULE_UI_PATH}/lib/astro-hydration/dist/jsonapi-params.js`,
        '@/lib/jsonapi-utils': `${CANVAS_MODULE_UI_PATH}/lib/astro-hydration/dist/jsonapi-utils.js`,
        '@/lib/drupal-utils': `${CANVAS_MODULE_UI_PATH}/lib/astro-hydration/dist/drupal-utils.js`,
        swr: `${CANVAS_MODULE_UI_PATH}/lib/astro-hydration/dist/swr.js`,
      },
    }),
    [],
  );

  const getIframeSrc = useCallback(
    ({
      previewGlobalCss,
      previewCss,
      previewJsData,
    }: {
      previewCss: string;
      previewGlobalCss: string;
      previewJsData: string;
    }) => `
    <html>
      <head>
        <script type="importmap">
          ${JSON.stringify(importMap)}
        </script>
        <style>${previewGlobalCss}</style>
        ${
          // Add CSS for all code components except the current one.
          // @todo Make this more efficient by introducing better backend support.
          // @see https://drupal.org/i/3520867
          codeComponents
            ? Object.keys(codeComponents)
                .filter(
                  (componentName) =>
                    codeComponents[componentName].machineName !== componentId,
                )
                .map(
                  (componentName) =>
                    `<link rel="stylesheet" href="${Drupal.url(`canvas/api/v0/auto-saves/css/js_component/${componentName}`)}" />`,
                )
                .join('\n')
            : ''
        }
        <style>${previewCss}</style>
        <script id="canvas-code-editor-preview-data" type="application/json">
          ${previewJsData}
        </script>
        <script type="module" src="${CANVAS_MODULE_UI_PATH}/${PREVIEW_LIB_PATH}"></script>
      </head>
      <body>
        <div id="canvas-code-editor-preview-root"></div>
        <script>
          document.addEventListener('click', function (e) {
            const anchor = e.target.closest('a');
            if (anchor) {
              e.preventDefault();
              e.stopPropagation();
            }
          });
        </script>
      </body>
    </html>`,
    [codeComponents, componentId, importMap],
  );

  // Verifies that the component's JS code has a default export.
  const hasDefaultExport = (ast: File) => {
    for (const node of ast.program.body) {
      if (node.type === 'ExportDefaultDeclaration') {
        // Case when JS is a function default export.
        if (node.declaration.type === 'FunctionDeclaration') {
          return true;
        } else if ('name' in node.declaration) {
          // Case when JS is an arrow function default export.
          return true;
        }
      }
    }
    return false;
  };

  // Collects all the JS components imported in the component's JS code.
  const collectImportedJsComponents = useCallback(
    (ast: File) => {
      // Returns an array of all the imports that start with '@/components/'.
      // ex. [ 'my_button', 'my_heading']
      const scope = '@/components/';
      const imports = getImportsFromAst(ast, scope);
      const dataDependencies = getDataDependenciesFromAst(ast);
      dispatch(clearDataFetches());
      dispatch(setCodeComponentProperty(['importedJsComponents', imports]));
      dispatch(
        setCodeComponentProperty(['dataDependencies', dataDependencies]),
      );
      setIsJsImportError(false);
      if (imports.length > 0) {
        imports.map((importName) => {
          if (!codeComponents?.[importName]) {
            setIsJsImportError(true);
            setJsImportNameWithError(importName);
          }
        });
      }
    },
    [codeComponents, dispatch],
  );

  useEffect(() => {
    if (!sourceCodeJs) {
      return;
    }
    try {
      const ast = parse(sourceCodeJs, {
        sourceType: 'module',
        plugins: ['jsx'],
      });
      collectImportedJsComponents(ast);
      if (hasDefaultExport(ast)) {
        setIsDefaultExportMissingError(false);
      } else {
        setIsDefaultExportMissingError(true);
      }
    } catch (error) {
      // This error will also be caught by the JavaScript compiler, and
      // `previewCompilationError` will be set to true in the code editor
      // Redux slice.
      console.error('Error parsing source code:', error);
    }
  }, [sourceCodeJs, collectImportedJsComponents]);

  useEffect(() => {
    if (!compiledJs) {
      return;
    }
    // The following data is going to be embedded in the iframe as a JSON
    // object. It is used by a script that we load inside the iframe to render
    // the component. The script is loaded via an `src` attribute instead of
    // being added to the iframe inline because of Content Security Policy (CSP)
    // restrictions.
    // @see ui/lib/code-editor-preview.js
    const propValues = getPropValuesForPreview(props);
    const slotNames = getSlotNamesForPreview(slots);
    // Remove the `canvas` and `canvasExtension` properties from `drupalSettings`.
    // They are only added for the Canvas UI, and are not available normally.
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const { canvas, canvasExtension, ...drupalSettings } = getDrupalSettings();
    const previewJsData = JSON.stringify({
      compiledJsUrl: URL.createObjectURL(
        new Blob([compiledJs], { type: 'text/javascript' }),
      ),
      compiledJsForSlotsUrl: URL.createObjectURL(
        new Blob([previewCompiledJsForSlots], { type: 'text/javascript' }),
      ),
      propValues,
      slotNames,
      drupalSettings,
    });
    setIframeSrcDoc(
      getIframeSrc({
        previewCss: compiledCss,
        previewGlobalCss: compiledGlobalCss,
        previewJsData,
      }),
    );
  }, [
    compiledCss,
    compiledGlobalCss,
    compiledJs,
    getIframeSrc,
    previewCompiledJsForSlots,
    props,
    slots,
  ]);

  // Add an invisible overlay to the iframe when the Mosaic window is being resized.
  // This prevents the iframe from intercepting mouse events from the parent Mosaic window.
  // This is necessary because when a user is resizing their preview window, and their mouse enters the iframe,
  // the parent window stops receiving mouse events so the resizing stops.
  useEffect(() => {
    const handleOnChange = () => {
      if (parentRef.current) {
        parentRef.current.classList.add('iframe-overlay');
      }
    };
    const handleOnRelease = () => {
      if (parentRef.current) {
        parentRef.current.classList.remove('iframe-overlay');
      }
    };

    window.addEventListener('mosaicOnChange', handleOnChange);
    window.addEventListener('mosaicOnRelease', handleOnRelease);

    return () => {
      window.removeEventListener('mosaicOnChange', handleOnChange);
      window.removeEventListener('mosaicOnRelease', handleOnRelease);
    };
  }, []);

  const renderCompileError = () => (
    <ErrorCard
      title="Error: There was an error compiling your code."
      error="Check your browser's developer console for more details."
    />
  );

  const renderExportMissingError = () => (
    <ErrorCard
      title="Error: Component is missing a default export."
      asChild={true}
    >
      <MissingDefaultExportMessage />
    </ErrorCard>
  );

  const renderImportError = () => {
    const title = `Error: Could not import JS component of id: ${jsImportNameWithError}`;
    return (
      <ErrorCard title={title} asChild={true}>
        <Flex direction="column" gap="3">
          <TextBlock>
            An auto-saved version of this component doesn't exist yet or your
            import statement is using the wrong component id.
          </TextBlock>
          <CodeBlock>import Heading from '@/components/component_id'</CodeBlock>
          <TextBlock>
            To find the correct id for the component you are trying to import,
            open the code editor for that component, and it will be in your
            browser's URL.
          </TextBlock>
        </Flex>
      </ErrorCard>
    );
  };

  const errorComponents = {
    isCompileError: renderCompileError(),
    isDefaultExportMissingError: renderExportMissingError(),
    isJsImportError: renderImportError(),
  };

  const activeErrors = Object.entries({
    isCompileError: compilationError,
    isDefaultExportMissingError,
    isJsImportError,
  })
    .filter(([_, hasError]) => hasError)
    .map(([key]) => key);

  return (
    <Spinner loading={isLoading}>
      <div className={styles.iframeContainer} ref={parentRef}>
        {activeErrors.length > 0 && (
          <ScrollArea>
            <div className={styles.errorContainer}>
              {activeErrors.map(
                (key) => errorComponents[key as keyof typeof errorComponents],
              )}
            </div>
          </ScrollArea>
        )}
        {activeErrors.length === 0 && (
          <iframe
            className={styles.iframe}
            title="Canvas Code Editor Preview"
            ref={iframeRef}
            height="100%"
            width="100%"
            srcDoc={iframeSrcDoc}
            data-canvas-iframe="canvas-code-editor-preview"
            // @todo: Remove 'allow-same-origin' in https://www.drupal.org/i/3527515.
            sandbox="allow-scripts allow-same-origin"
          />
        )}
      </div>
    </Spinner>
  );
};

export default Preview;
