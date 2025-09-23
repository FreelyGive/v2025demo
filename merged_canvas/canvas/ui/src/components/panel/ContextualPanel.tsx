import { useEffect, useState } from 'react';
import clsx from 'clsx';
import { Outlet } from 'react-router-dom';
import { InfoCircledIcon } from '@radix-ui/react-icons';
import {
  Box,
  Button,
  Callout,
  Flex,
  ScrollArea,
  Tabs,
  Text,
} from '@radix-ui/themes';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import ErrorBoundary from '@/components/error/ErrorBoundary';
import PageDataForm from '@/components/PageDataForm';
import { setCurrentComponent } from '@/features/form/formStateSlice';
import {
  EditorFrameContext,
  selectIsMultiSelect,
  selectSelectedComponentUuid,
  selectSelection,
} from '@/features/ui/uiSlice';
import useHidePanelClasses from '@/hooks/useHidePanelClasses';

import type React from 'react';

import styles from './ContextualPanel.module.css';

interface ContextualPanelProps {
  context: EditorFrameContext;
}

const ContextualPanel: React.FC<ContextualPanelProps> = ({ context }) => {
  const selectedComponent = useAppSelector(selectSelectedComponentUuid);
  const isMultiSelect = useAppSelector(selectIsMultiSelect);
  const selection = useAppSelector(selectSelection);
  const dispatch = useAppDispatch();
  const isTemplateContext = context === EditorFrameContext.TEMPLATE;

  const [activePanel, setActivePanel] = useState('pageData');
  const offRightClasses = useHidePanelClasses('right');
  const [hidePanel, setHidePanel] = useState(false);

  useEffect(() => {
    if (selectedComponent) {
      // One component is selected
      dispatch(setCurrentComponent(selectedComponent));
      setActivePanel('settings');
    } else if (isMultiSelect) {
      // Multiple components are selected
      dispatch(setCurrentComponent(''));
      setActivePanel('settings');
    } else {
      // No component is selected
      dispatch(setCurrentComponent(''));
      setActivePanel('pageData');
    }
  }, [dispatch, selectedComponent, isMultiSelect]);

  useEffect(() => {
    if (isTemplateContext && !isMultiSelect && !selectedComponent) {
      setHidePanel(true);
    } else {
      setHidePanel(false);
    }
  }, [selectedComponent, isMultiSelect, isTemplateContext]);

  const mainTabText = isTemplateContext ? 'Template data' : 'Page data';

  return (
    <Box
      data-testid="canvas-contextual-panel"
      pt="2"
      className={clsx(
        styles.contextualPanel,
        { [styles.hidePanel]: hidePanel },
        ...offRightClasses,
      )}
    >
      <Flex
        flexGrow="1"
        direction="column"
        height="100%"
        data-testid={`canvas-contextual-panel-${selectedComponent}`}
      >
        <ErrorBoundary>
          <Tabs.Root
            defaultValue={'pageData'}
            onValueChange={setActivePanel}
            value={activePanel}
            className={clsx(styles.tabRoot)}
          >
            <Tabs.List justify="start" mx="4" size="1">
              {!isTemplateContext && (
                <Tabs.Trigger
                  value="pageData"
                  data-testid="canvas-contextual-panel--page-data"
                >
                  {mainTabText}
                </Tabs.Trigger>
              )}
              {(selectedComponent || isMultiSelect) && (
                <Tabs.Trigger
                  value="settings"
                  data-testid="canvas-contextual-panel--settings"
                >
                  Settings
                </Tabs.Trigger>
              )}
            </Tabs.List>
            <ScrollArea scrollbars="vertical" className={styles.scrollArea}>
              <Box px="4" width="100%">
                <Tabs.Content value={'settings'}>
                  {isMultiSelect && (
                    <Box my="2">
                      <Flex direction="column" gap="2">
                        <Text align="center" color="gray" my="3" size="1">
                          {selection.items.length} items selected
                        </Text>

                        <Flex gap="1" justify="center" align="center">
                          <Button
                            size="1"
                            disabled={!selection.consecutive}
                            onClick={() =>
                              alert(
                                'Copy functionality will be implemented later',
                              )
                            }
                            className="canvas-button"
                          >
                            Copy
                          </Button>
                          <Text size="1" color="gray">
                            or
                          </Text>
                          <Button
                            size="1"
                            disabled={!selection.consecutive}
                            onClick={() =>
                              alert(
                                'Save as Pattern functionality will be implemented later',
                              )
                            }
                            className="canvas-button"
                          >
                            Save as Pattern
                          </Button>
                        </Flex>
                        {!selection.consecutive && (
                          <Callout.Root
                            size="1"
                            color="blue"
                            variant="surface"
                            mt="4"
                          >
                            <Callout.Icon>
                              <InfoCircledIcon />
                            </Callout.Icon>
                            <Callout.Text>
                              Actions are only available when selecting adjacent
                              items in the layout
                            </Callout.Text>
                          </Callout.Root>
                        )}
                      </Flex>
                    </Box>
                  )}
                  <ErrorBoundary title="An unexpected error has occurred while rendering the component's form.">
                    <Outlet />
                  </ErrorBoundary>
                </Tabs.Content>
                {!isTemplateContext && (
                  <Tabs.Content
                    value={'pageData'}
                    forceMount={true}
                    hidden={activePanel !== 'pageData'}
                  >
                    {context === 'entity' && <PageDataForm />}
                  </Tabs.Content>
                )}
              </Box>
            </ScrollArea>
          </Tabs.Root>
        </ErrorBoundary>
      </Flex>
    </Box>
  );
};
export default ContextualPanel;
