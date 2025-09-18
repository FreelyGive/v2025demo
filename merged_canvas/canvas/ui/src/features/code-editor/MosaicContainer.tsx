import {
  MosaicWindow,
  MosaicWithoutDragDropContext,
} from 'react-mosaic-component';

import type { MosaicNode } from 'react-mosaic-component';

import './canvas-react-mosaic-component.css';

import { useState } from 'react';
import { LayoutIcon } from '@radix-ui/react-icons';
import { Box, Button, ScrollArea, Tabs } from '@radix-ui/themes';

import CssEditor from '@/features/code-editor/editors/CssEditor';
import GlobalCssEditor from '@/features/code-editor/editors/GlobalCssEditor';
import JavaScriptEditor from '@/features/code-editor/editors/JavaScriptEditor';

import styles from './MosaicContainer.module.css';

import './canvas-code-mirror.css';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import {
  selectCodeComponentProperty,
  selectCodeComponentSerialized,
} from '@/features/code-editor/codeEditorSlice';
import ComponentData from '@/features/code-editor/component-data/ComponentData';
import useCodeEditor from '@/features/code-editor/hooks/useCodeEditor';
import Preview from '@/features/code-editor/Preview';
import ConflictWarning from '@/features/editor/ConflictWarning';
import { selectLatestError } from '@/features/error-handling/queryErrorSlice';
import { openAddToComponentsDialog } from '@/features/ui/codeComponentDialogSlice';

const defaultLayout: MosaicNode<string> = {
  direction: 'row',
  first: 'Editor',
  second: {
    direction: 'column',
    first: 'Preview',
    second: 'Component data',
    splitPercentage: 50,
  },
  splitPercentage: 60,
};

const fullEditorLayout: MosaicNode<string> = {
  direction: 'row',
  first: 'Editor',
  second: {
    direction: 'column',
    first: 'Preview',
    second: 'Component data',
    splitPercentage: 50,
  },
  splitPercentage: 100,
};

const MosaicContainer = () => {
  const [layout, setLayout] = useState<MosaicNode<string>>(defaultLayout);
  const [activeTab, setActiveTab] = useState('js');
  const dispatch = useAppDispatch();
  const selectedComponent = useAppSelector(selectCodeComponentSerialized);
  const componentStatus = useAppSelector(selectCodeComponentProperty('status'));
  const latestError = useAppSelector(selectLatestError);

  const { isLoading } = useCodeEditor();

  // Check for conflict errors (same as Editor.tsx)
  if (latestError && latestError.status === '409') {
    return <ConflictWarning />;
  }

  const TabGroup = () => {
    function tabChangeHandler(selectedTab: string) {
      setActiveTab(selectedTab);
    }
    return (
      <Tabs.Root
        className={styles.tabRoot}
        onValueChange={tabChangeHandler}
        value={activeTab}
      >
        <Tabs.List size="1" className={styles.tabList}>
          <Tabs.Trigger value="js" className={styles.tabTrigger}>
            JavaScript
          </Tabs.Trigger>
          <Tabs.Trigger value="css" className={styles.tabTrigger}>
            CSS
          </Tabs.Trigger>
          <Tabs.Trigger value="global-css" className={styles.tabTrigger}>
            Global CSS
          </Tabs.Trigger>
        </Tabs.List>
      </Tabs.Root>
    );
  };

  const ToggleLayoutButton = () => {
    function toggleLayout() {
      setLayout(layout === defaultLayout ? fullEditorLayout : defaultLayout);
    }

    return (
      <div className="mosaic-toggle-layout">
        <Button
          onClick={toggleLayout}
          aria-label="Toggle button for code editor view"
          variant="ghost"
          color="gray"
        >
          <LayoutIcon />
        </Button>
      </div>
    );
  };

  return (
    <div id="canvas-mosaic-container" data-testid="canvas-mosaic-container">
      {/* `DndProvider` is added in `ui/src/app/App.tsx` */}
      <MosaicWithoutDragDropContext
        value={layout}
        mosaicId={''}
        onChange={(newNode) => {
          window.dispatchEvent(new Event('mosaicOnChange'));
          setLayout(newNode as MosaicNode<string>);
        }}
        onRelease={() => {
          window.dispatchEvent(new Event('mosaicOnRelease'));
        }}
        renderTile={(id: string, path: any[]) => {
          switch (id) {
            case 'Editor':
              return (
                <MosaicWindow<string>
                  className="canvas-mosaic-window-editor"
                  path={path}
                  draggable={false}
                  toolbarControls={
                    <>
                      <TabGroup />
                      <ToggleLayoutButton />
                    </>
                  }
                  title="Editor"
                >
                  <ScrollArea className={styles.scrollArea}>
                    {activeTab === 'js' && (
                      <JavaScriptEditor isLoading={isLoading} />
                    )}
                    {activeTab === 'css' && <CssEditor isLoading={isLoading} />}
                    {activeTab === 'global-css' && (
                      <GlobalCssEditor isLoading={isLoading} />
                    )}
                  </ScrollArea>
                </MosaicWindow>
              );
            case 'Preview':
              return (
                <MosaicWindow<string>
                  className="canvas-mosaic-window-preview"
                  path={path}
                  title="Preview"
                  draggable={false}
                  renderToolbar={({ title }) => {
                    return (
                      <Box width="100%">
                        {componentStatus === false && (
                          <Box px="4">
                            <Box className={styles.addToComponentsButton}>
                              <Button
                                onClick={() => {
                                  dispatch(
                                    openAddToComponentsDialog(
                                      selectedComponent,
                                    ),
                                  );
                                }}
                              >
                                Add to components
                              </Button>
                            </Box>
                          </Box>
                        )}
                        <div className="mosaic-window-title">
                          <span>{title}</span>
                        </div>
                      </Box>
                    );
                  }}
                >
                  <Preview isLoading={isLoading} />
                </MosaicWindow>
              );
            case 'Component data':
              return (
                <MosaicWindow<string>
                  className="canvas-mosaic-window-component-data"
                  path={path}
                  title="Component data"
                  draggable={false}
                >
                  <ComponentData isLoading={isLoading} />
                </MosaicWindow>
              );
            default:
              return <div></div>;
          }
        }}
      />
    </div>
  );
};

export default MosaicContainer;
