/**
 * ⚠️ This is highly experimental and *will* be refactored.
 */
import {
  useCallback,
  useEffect,
  useRef,
  useState,
  useSyncExternalStore,
} from 'react';
import { DeepChat } from 'deep-chat-react';
import { useParams } from 'react-router';
import { useNavigate } from 'react-router-dom';
import AiWelcome from '@assets/icons/ai-welcome.svg?react';
import { Box, Flex, Text } from '@radix-ui/themes';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import {
  selectCodeComponentProperty,
  setCodeComponentProperty,
} from '@/features/code-editor/codeEditorSlice';
import {
  selectModel,
  setUpdatePreview,
} from '@/features/layout/layoutModelSlice';
import {
  selectPageData,
  updatePageDataExternally,
} from '@/features/pageData/pageDataSlice';
import {
  useCreateCodeComponentMutation,
  useGetComponentsQuery,
} from '@/services/componentAndLayout';
import { getDrupalSettings } from '@/utils/drupal-globals';

import fixtureProps from '../../../../modules/canvas_ai/src/PropsSchema.json';

import type {
  ComponentNode,
  LayoutModelSliceState,
} from '@/features/layout/layoutModelSlice';
import type { CodeComponent } from '@/types/CodeComponent';
import type { CanvasComponent } from '@/types/Component';

import styles from './AiWizard.module.css';

const DB_NAME = 'aiWizardDB';
const STORE_NAME = 'chatHistory';
const KEY = 'history';

const withStore = (
  type: IDBTransactionMode,
  callback: (store: IDBObjectStore) => void,
): Promise<void> => {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, 1);
    request.onupgradeneeded = () =>
      request.result.createObjectStore(STORE_NAME);
    request.onerror = () => reject(request.error);
    request.onsuccess = () => {
      const db = request.result;
      const tx = db.transaction(STORE_NAME, type);
      callback(tx.objectStore(STORE_NAME));
      tx.oncomplete = () => resolve();
      tx.onerror = () => reject(tx.error);
    };
  });
};

const db = {
  get: (): Promise<any[]> =>
    new Promise((resolve) => {
      let req: IDBRequest;
      withStore('readonly', (store) => {
        req = store.get(KEY);
      }).then(() => resolve(req?.result || []));
    }),
  set: (data: any[]) => withStore('readwrite', (store) => store.put(data, KEY)),
  clear: () => withStore('readwrite', (store) => store.clear()),
};

const createHistoryStore = () => {
  let history: any[] = [];
  const subscribers = new Set<() => void>();

  db.get().then((initialHistory) => {
    history = initialHistory;
    subscribers.forEach((callback) => callback());
  });

  return {
    addMessage(message: any) {
      history = [...history, message];
      db.set(history);
    },
    clearHistory() {
      history = [];
      db.clear();
      subscribers.forEach((callback) => callback());
    },
    subscribe(callback: () => void) {
      subscribers.add(callback);
      return () => subscribers.delete(callback);
    },
    getSnapshot() {
      return history;
    },
  };
};
const historyStore = createHistoryStore();

const simplePropertyHandler = (
  property: string,
  propKey: keyof CodeComponent,
) => ({
  canHandle: (msg: any) => property in msg && msg[property],
  handle: async ({ message, dispatch }: { message: any; dispatch: any }) => {
    dispatch(setCodeComponentProperty([propKey, message[property]]));
  },
});

const cssStructureHandler = simplePropertyHandler(
  'css_structure',
  'sourceCodeCss',
);
const jsStructureHandler = simplePropertyHandler(
  'js_structure',
  'sourceCodeJs',
);

const componentStructureHandler = {
  canHandle: (msg: any) =>
    'component_structure' in msg && msg.component_structure,
  handle: async ({
    message,
    createCodeComponent,
    navigate,
  }: {
    message: any;
    createCodeComponent: any;
    navigate: any;
  }) => {
    const component = message.component_structure;
    await createCodeComponent(component).unwrap();
    navigate(`/code-editor/component/${component.machineName}`);
  },
};

const propsMetadataHandler = {
  canHandle: (msg: any) => 'props_metadata' in msg && msg.props_metadata,
  handle: async ({ message, dispatch }: { message: any; dispatch: any }) => {
    const parsedProps = JSON.parse(message.props_metadata);
    dispatch(setCodeComponentProperty(['props', parsedProps]));
  },
};

const createdContentHandler = {
  canHandle: (msg: any) => 'created_content' in msg && msg.created_content,
  handle: async ({ message, dispatch }: { message: any; dispatch: any }) => {
    const value = message.created_content;
    dispatch(setUpdatePreview(true));
    dispatch(updatePageDataExternally({ 'title[0][value]': value }));
  },
};

const editContentHandler = {
  canHandle: (msg: any) => 'refined_text' in msg && msg.refined_text,
  handle: async ({ message, dispatch }: { message: any; dispatch: any }) => {
    const value = message.refined_text;
    dispatch(setUpdatePreview(true));
    dispatch(updatePageDataExternally({ 'title[0][value]': value }));
  },
};
const metadataHandler = {
  canHandle: (msg: any) => 'metadata' in msg && msg.metadata,
  handle: async ({ message, dispatch }: { message: any; dispatch: any }) => {
    const value = message.metadata;
    dispatch(setUpdatePreview(true));
    dispatch(
      updatePageDataExternally({
        'description[0][value]': value.metatag_description,
      }),
    );
  },
};

// Helper to delay the placement of components.
const delay = (ms: number) => new Promise((resolve) => setTimeout(resolve, ms));

const operationsHandler = {
  canHandle: (msg: any) => 'operations' in msg && msg.operations,
  handle: async ({
    message,
    dispatch,
    availableComponents,
    layoutUtils,
    componentSelectionUtils,
    navigate,
    params,
  }: {
    message: any;
    dispatch: any;
    availableComponents: any;
    layoutUtils: any;
    componentSelectionUtils: any;
    navigate: any;
    params: any;
  }) => {
    // Logic for placing components (SDCs/Blocks/Code components) to the editor frame.
    for (const op of message.operations) {
      // Only 'Add' operation is supported for now.
      if (
        op.operation === 'ADD' &&
        op.components &&
        Array.isArray(op.components) &&
        availableComponents
      ) {
        for (const component of op.components) {
          if (component.id && availableComponents[component.id]) {
            const componentToUse: CanvasComponent =
              availableComponents[component.id];
            dispatch(
              layoutUtils.addNewComponentToLayout(
                {
                  component: componentToUse,
                  withValues: component.fieldValues,
                  to: component.nodePath,
                },
                componentSelectionUtils.setSelectedComponent,
              ),
            );
            // Wait for a second before placing the next component, for the UI to render the component.
            await delay(1000);
          }
        }
      }
    }
    const { entityId, entityType } = params;
    // Redirect to /editor.
    navigate(`/editor/${entityType}/${entityId}`);
  },
};

const messageHandlers = [
  createdContentHandler,
  editContentHandler,
  cssStructureHandler,
  jsStructureHandler,
  componentStructureHandler,
  propsMetadataHandler,
  metadataHandler,
  operationsHandler,
];

const startPolling = (
  requestId: string,
  csrfToken: string,
  chatEl: any,
  onPollingComplete?: () => void,
  stopSignal?: { stopped: boolean },
) => {
  let pollCount = 0;
  const maxPolls = 500;
  const itemStatuses = new Map();
  let hasAddedInitialMessage = false;
  let pollingMessageIndex = -1;

  const getStatusIcon = (status: string) =>
    status === 'completed'
      ? '<span class="aiCompletedIcon"></span>'
      : '<span class="aiLoader"></span>';

  const buildHtmlContent = () => {
    if (itemStatuses.size === 0) return '';

    let htmlContent = '<div style="margin-top: 10px;">';

    itemStatuses.forEach((item) => {
      const icon = getStatusIcon(item.status);

      htmlContent += `<div style="display: flex; align-items: center; padding: 8px; background-color: white;">
        <span style="margin-right: 8px;">${icon}</span>
        <span style="font-weight: 400;">${item.description}</span>
      </div>`;

      if (item.type === 'agent' && item.generated_text?.trim()) {
        htmlContent += `<div style="padding: 8px; background-color: white; font-size: 14px; line-height: 1.26;">
          ${item.generated_text.replace(/\n/g, '<br>')}
        </div>`;
      }
    });

    return htmlContent + '</div>';
  };

  const updateChatDisplay = () => {
    const htmlContent = buildHtmlContent();
    if (!chatEl) return;

    const scrollContainer = chatEl.shadowRoot?.querySelector('#messages');
    const shouldAutoScroll = scrollContainer
      ? scrollContainer.scrollHeight -
          scrollContainer.scrollTop -
          scrollContainer.clientHeight <
        5
      : false;

    if (!hasAddedInitialMessage) {
      chatEl.addMessage({ html: htmlContent, role: 'ai' });
      hasAddedInitialMessage = true;
      pollingMessageIndex = chatEl.getMessages().length - 1;
    } else {
      try {
        if (pollingMessageIndex >= 0) {
          chatEl.updateMessage({ html: htmlContent }, pollingMessageIndex);
        }
      } catch (error) {
        console.warn('UpdateMessage failed:', error);
        chatEl.addMessage({ html: htmlContent, role: 'ai' });
        pollingMessageIndex = chatEl.getMessages().length - 1;
      }
    }

    if (scrollContainer && shouldAutoScroll) {
      setTimeout(
        () => (scrollContainer.scrollTop = scrollContainer.scrollHeight),
        0,
      );
    }
  };

  const handlePollingComplete = () => {
    const finalContent = buildHtmlContent();
    if (finalContent) {
      historyStore.addMessage({ html: finalContent, role: 'ai' });
    }
    onPollingComplete?.();
  };

  const poll = async () => {
    if (stopSignal?.stopped) {
      return;
    }
    try {
      pollCount++;
      const response = await fetch(
        `/admin/api/canvas/ai-progress?request_id=${requestId}`,
        {
          method: 'GET',
          headers: { 'X-CSRF-Token': csrfToken },
        },
      );

      if (!response.ok) {
        throw new Error(`Polling HTTP error. Status: ${response.status}`);
      }

      const pollingData = await response.json();

      if (pollingData.items?.length) {
        pollingData.items.forEach((item: any) => {
          itemStatuses.set(item.id, {
            type: item.type,
            description: item.description || item.name || '',
            status: item.status,
            generated_text: item.generated_text || '',
          });
        });
      }

      updateChatDisplay();

      if (pollingData.is_finished) {
        handlePollingComplete();
        return;
      }

      if (pollCount < maxPolls) {
        setTimeout(poll, 2000);
      } else {
        console.error('Polling timeout reached');
        handlePollingComplete();
      }
    } catch (error) {
      console.error('Polling request failed:', error);
      handlePollingComplete();
    }
  };
  setTimeout(poll, 1000);
};

function getHandlersForMessage(message: any) {
  return messageHandlers.filter((handler) => handler.canHandle(message));
}

const AiWizard = () => {
  const pageData = useAppSelector(selectPageData);
  const dispatch = useAppDispatch();
  const drupalSettings = getDrupalSettings();
  const chatElementRef = useRef<any>(null);
  const [csrfToken, setCsrfToken] = useState<string | null>(null);
  const [createCodeComponent] = useCreateCodeComponentMutation();
  const navigate = useNavigate();
  const params = useParams();
  const codeComponentName = useAppSelector(
    selectCodeComponentProperty('machineName'),
  );
  const model = useAppSelector(selectModel);
  const textPropsMap = Object.fromEntries(
    Object.entries(model).map(([uuid, comp]) => [uuid, comp.resolved]),
  );
  const textPropsMapString = JSON.stringify(textPropsMap);
  const chatHistory = useSyncExternalStore(
    historyStore.subscribe,
    historyStore.getSnapshot,
  );
  let isComponentRendered = false;
  const welcomeTextRef = useRef<HTMLSpanElement>(null);
  // Get the current layout, selected component, and available components from Redux state
  const theLayoutModel = useAppSelector(
    (state) => state?.layoutModel?.present as LayoutModelSliceState,
  );

  // Create a ref to store current values for Deep Chat's connect prop.
  // Accessing these ensures we're working with fresh values even after the Deep
  // Chat component has been mounted.
  const currentValuesRef = useRef({
    codeComponentName,
    textPropsMapString,
    pageData,
    params,
    theLayoutModel,
  });

  // Update the ref whenever tracked values change.
  useEffect(() => {
    currentValuesRef.current = {
      codeComponentName,
      textPropsMapString,
      pageData,
      params,
      theLayoutModel,
    };
  }, [codeComponentName, textPropsMapString, pageData, params, theLayoutModel]);
  // Access layoutUtils and componentSelectionUtils from drupalSettings.canvas
  const layoutUtils = drupalSettings.canvas?.layoutUtils as any;
  const componentSelectionUtils = drupalSettings.canvas
    ?.componentSelectionUtils as any;

  const selectedComponent = useAppSelector(
    (state) => state.ui.selection.items[0],
  );
  const { data: availableComponents } = useGetComponentsQuery();
  const componentsRef = useRef<any>(null);

  useEffect(() => {
    if (availableComponents && !componentsRef.current) {
      componentsRef.current = availableComponents;
    }
  }, [availableComponents]);

  // Helper to transform the current layout into a JSON representation.
  const transformLayout = () => {
    const theLayout = currentValuesRef.current.theLayoutModel;
    if (!theLayout?.layout) return null;
    const result: any = { layout: {} };
    theLayout.layout.forEach((region: any, regionIndex: number) => {
      result.layout[region.id] = {
        nodePathPrefix: [regionIndex],
        components: [],
      };
      result.layout[region.id].components = processComponents(
        region.components,
      );
    });
    return result;
  };

  // Helper to recursively process components
  const processComponents = (
    components: ComponentNode[] | undefined,
    parentPath: string[] = [],
  ): any[] => {
    if (!components) return [];
    return components.map((component) => {
      let nodePath: number[] | null = null;
      try {
        nodePath = layoutUtils.findNodePathByUuid(
          currentValuesRef.current.theLayoutModel.layout,
          component.uuid,
        );
      } catch (e) {
        console.warn(`Could not find nodePath for ${component.uuid}`);
      }
      const transformedComponent: any = {
        name: component.type?.split('@')[0],
        uuid: component.uuid,
        nodePath: nodePath,
      };
      // Handle slots if they exist
      if (component.slots && component.slots.length > 0) {
        transformedComponent.slots = {};
        component.slots.forEach((slot) => {
          transformedComponent.slots[slot.id] = {
            components: processComponents(slot.components, [
              ...parentPath,
              component.uuid,
            ]),
          };
        });
      }
      return transformedComponent;
    });
  };

  // Fetch CSRF token on mount.
  useEffect(() => {
    const fetchToken = async () => {
      try {
        const response = await fetch('/admin/api/canvas/token', {
          credentials: 'same-origin',
        });
        if (!response.ok) {
          throw new Error(
            `HTTP error: ${response.status} ${response.statusText}`,
          );
        }
        const token = await response.text();
        setCsrfToken(token);
      } catch (error) {
        console.error('Failed to fetch CSRF token:', error);
        const event = new CustomEvent('canvas-csrf-token-error', {
          detail: {
            error,
            time: new Date(),
          },
        });
        window.dispatchEvent(event);
      }
    };

    fetchToken();
  }, []);

  // Function to handle message response from AI.
  const receiveMessage = useCallback(
    async (message: any) => {
      try {
        const handlers = getHandlersForMessage(message);
        for (const handler of handlers) {
          // If the handler is operationsHandler, do not await it here.
          if (handler === operationsHandler) {
            setTimeout(() => {
              // Do the async work in the background.
              operationsHandler.handle({
                message,
                dispatch,
                availableComponents: componentsRef.current,
                layoutUtils,
                componentSelectionUtils,
                navigate,
                params,
              });
            }, 0);
          } else {
            await handler.handle({
              message,
              dispatch,
              createCodeComponent,
              navigate,
              availableComponents: componentsRef.current,
              layoutUtils,
              componentSelectionUtils,
              params,
            });
          }
        }
        return { text: message.message };
      } catch (error) {
        console.error('AI response processing failed:', error);
        return {
          text: 'An error occurred while processing your request. Please try again.',
          role: 'error',
        };
      }
    },
    [
      dispatch,
      layoutUtils,
      componentSelectionUtils,
      navigate,
      createCodeComponent,
      params,
    ],
  );

  useEffect(() => {
    const chatEl = chatElementRef.current;
    if (!chatEl) return;
    const handler = (event: { detail: { message: any; isHistory: any } }) => {
      const { message, isHistory } = event.detail;
      if (!isHistory) {
        if (welcomeTextRef.current) {
          welcomeTextRef.current.style.display = 'none';
        }
        historyStore.addMessage(message);
      }
    };
    chatEl.addEventListener('message', handler);
    return () => {
      chatEl.removeEventListener('message', handler);
    };
  }, [csrfToken]);

  // Handle text input changes to enable/disable submit button.
  const handleTextInput = () => {
    const chatEl = chatElementRef.current;
    const deepChatEl = document.querySelector('deep-chat') as any;
    const inputText =
      deepChatEl?.shadowRoot?.querySelector('#text-input')?.textContent || '';
    if (inputText.trim().length > 0) {
      chatEl.disableSubmitButton(false);
    } else {
      chatEl.disableSubmitButton();
    }
  };

  return (
    csrfToken && (
      <Flex
        direction="column"
        align="stretch"
        gap="4"
        className={styles.aiWizard}
        onKeyDown={(e) => {
          e.stopPropagation();
        }}
      >
        <Flex direction="column" align="center">
          <Flex align="center">
            <AiWelcome />
          </Flex>
          <Flex direction="row" align="center" gap="0">
            <Box className={styles.aiWizardTitleContainer}>
              <Text className={styles.aiWizardTitle}>Drupal Canvas AI</Text>
              <Text className={styles.aiWizardBeta}>Beta</Text>
            </Box>
          </Flex>
          <Text ref={welcomeTextRef} className={styles.aiWizardSubtitle}>
            Hello, how can I help you today?
          </Text>
        </Flex>
        <DeepChat
          ref={chatElementRef}
          history={chatHistory}
          images={{
            files: {
              acceptedFormats: '.jpg, .png, .jpeg',
              // For now we just support uploading 1 image at a time
              // if the user tries to upload another image the already
              // added image is replaced.
              maxNumberOfFiles: 1,
            },
            button: {
              position: 'inside-left',
              styles: {
                container: {
                  default: {
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    marginLeft: '8px',
                    marginBottom: '12px',
                    backgroundColor: '#F0F0F3',
                  },
                },
                svg: {
                  content: `
                  <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <rect width="16" height="16" fill="white" fill-opacity="0.01"/>
                  <path fill-rule="evenodd" clip-rule="evenodd" d="M8.53324 2.93324C8.53324 2.63869 8.29445 2.3999 7.9999 2.3999C7.70535 2.3999 7.46657 2.63869 7.46657 2.93324V7.46657H2.93324C2.63869 7.46657 2.3999 7.70535 2.3999 7.9999C2.3999 8.29445 2.63869 8.53324 2.93324 8.53324H7.46657V13.0666C7.46657 13.3611 7.70535 13.5999 7.9999 13.5999C8.29445 13.5999 8.53324 13.3611 8.53324 13.0666V8.53324H13.0666C13.3611 8.53324 13.5999 8.29445 13.5999 7.9999C13.5999 7.70535 13.3611 7.46657 13.0666 7.46657H8.53324V2.93324Z" fill="#60646C"/>
                  </svg>
                `,
                },
              },
            },
          }}
          // @todo Revisit once https://www.drupal.org/node/3528730 is in.
          requestBodyLimits={{
            maxMessages: 3,
          }}
          connect={{
            // Defining a handler instead of an object to ensure we can work with
            // up-to-date data. Otherwise `connect.additionalBodyProps` captures
            // the values at the time the component was mounted.
            // @see https://deepchat.dev/docs/connect/#Handler
            handler: async (body, signals) => {
              let pendingResponse: any = null;
              const stopPolling = { stopped: false };

              try {
                const hasFiles = body instanceof FormData;
                let requestBody: FormData | string;
                const headers: Record<string, string> = {
                  'X-CSRF-Token': csrfToken,
                };

                if (hasFiles) {
                  const files = body.getAll('files');
                  const MAX_FILE_SIZE =
                    drupalSettings?.canvas?.canvasAiMaxFileSize;

                  for (const file of files) {
                    if (file instanceof File && file.size > MAX_FILE_SIZE) {
                      signals.onResponse({
                        text: `File is too large. Maximum allowed size is ${MAX_FILE_SIZE / (1024 * 1024)}MB.`,
                        role: 'error',
                      });
                      return;
                    }
                  }
                  requestBody = body as FormData;
                  requestBody.append(
                    'entity_type',
                    currentValuesRef.current.params.entityType || '',
                  );
                  requestBody.append(
                    'entity_id',
                    currentValuesRef.current.params.entityId || '',
                  );
                  requestBody.append(
                    'selected_component',
                    currentValuesRef.current.codeComponentName,
                  );
                  requestBody.append(
                    'layout',
                    currentValuesRef.current.textPropsMapString,
                  );
                  requestBody.append(
                    'derived_proptypes',
                    JSON.stringify(fixtureProps),
                  );
                } else {
                  requestBody = JSON.stringify({
                    ...body,
                    entity_type: currentValuesRef.current.params.entityType,
                    entity_id: currentValuesRef.current.params.entityId,
                    selected_component:
                      currentValuesRef.current.codeComponentName,
                    layout: currentValuesRef.current.textPropsMapString,
                    active_component_uuid: selectedComponent ?? '',
                    current_layout: transformLayout(),
                    derived_proptypes: fixtureProps,
                    page_title:
                      currentValuesRef.current.pageData['title[0][value]'],
                    page_description:
                      currentValuesRef.current.pageData[
                        'description[0][value]'
                      ],
                  });
                  headers['Content-Type'] = 'application/json';
                }
                // Generate a unique request ID
                const requestId = `req_${Date.now()}_${Math.random().toString(36).substring(2, 11)}`;
                if (hasFiles) {
                  (requestBody as FormData).append('request_id', requestId);
                } else {
                  const parsedBody = JSON.parse(requestBody as string);
                  parsedBody.request_id = requestId;
                  requestBody = JSON.stringify(parsedBody);
                }

                // Start polling first
                const chatEl = chatElementRef.current;
                if (chatEl) {
                  startPolling(
                    requestId,
                    csrfToken,
                    chatEl,
                    async () => {
                      // Process the main response after polling completes
                      if (pendingResponse) {
                        const processedMessage =
                          await receiveMessage(pendingResponse);
                        await signals.onResponse(processedMessage);
                        chatEl.disableSubmitButton();
                      }
                    },
                    stopPolling,
                  );
                }

                // Make the main API call but don't process the response immediately
                fetch('/admin/api/canvas/ai', {
                  method: 'POST',
                  headers,
                  body: requestBody,
                })
                  .then(async (response) => {
                    if (!response.ok) {
                      throw new Error(`HTTP error. Status: ${response.status}`);
                    }
                    const data = await response.json();
                    // Store the response instead of processing it
                    pendingResponse = data;
                  })
                  .catch((error) => {
                    console.error('AI request failed:', error);
                    stopPolling.stopped = true;
                    signals.onResponse({
                      text: 'An error occurred while processing your request. Please try again.',
                      role: 'error',
                    });
                    setTimeout(() => {
                      chatElementRef.current?.disableSubmitButton();
                    }, 0);
                  });
              } catch (error) {
                console.error('AI request failed:', error);
                stopPolling.stopped = true;
                await signals.onResponse({
                  text: 'An error occurred while processing your request. Please try again.',
                  role: 'error',
                });
                setTimeout(() => {
                  chatElementRef.current?.disableSubmitButton();
                }, 0);
              }
              setTimeout(() => {
                chatElementRef.current?.disableSubmitButton();
              }, 0);
            },
          }}
          onInput={handleTextInput}
          onComponentRender={() => {
            if (!isComponentRendered) {
              chatElementRef.current.clearMessages();
              historyStore.clearHistory();
              chatElementRef.current.disableSubmitButton();
              isComponentRendered = true;
            }
          }}
          textInput={{
            placeholder: { text: 'Build me a ...' },
            styles: {
              text: {
                padding: '16px',
              },
              container: {
                height: '167px',
                width: '100%',
                padding: '0 0 40px 0',
              },
            },
          }}
          style={{
            width: '283px',
            height: '100%',
            border: 'none',
          }}
          messageStyles={{
            default: {
              shared: {
                bubble: {
                  width: '100%',
                  maxWidth: '100%',
                  color: 'var(--black-12)',
                  fontSize: '14px',
                  fontWeight: '400',
                  lineHeight: '1.26',
                  padding: '8px',
                  textAlign: 'left',
                },
              },
              user: {
                bubble: {
                  backgroundColor: '#F0F0F3',
                },
              },
              ai: {
                bubble: {
                  backgroundColor: 'white',
                },
              },
              error: {
                bubble: {
                  color: '#FF3333',
                },
              },
            },
          }}
          submitButtonStyles={{
            disabled: {
              container: {
                default: {
                  display: 'none',
                },
              },
            },
            submit: {
              container: {
                default: {
                  display: 'inherit',
                  marginRight: '8px',
                  marginBottom: '12px',
                },
              },
              svg: {
                content: `
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M0 3C0 1.34315 1.34315 0 3 0H21C22.6569 0 24 1.34315 24 3V21C24 22.6569 22.6569 24 21 24H3C1.34315 24 0 22.6569 0 21V3Z" fill="#0090FF"/>
                  <rect width="16" height="16" transform="translate(4 4)" fill="white" fill-opacity="0.01"/>
                  <path fill-rule="evenodd" clip-rule="evenodd" d="M11.6228 6.28952C11.8311 6.08123 12.1688 6.08123 12.3771 6.28952L16.6438 10.5562C16.852 10.7645 16.852 11.1021 16.6438 11.3104C16.4355 11.5187 16.0978 11.5187 15.8894 11.3104L12.5333 7.95422V17.3333C12.5333 17.6278 12.2945 17.8666 12 17.8666C11.7054 17.8666 11.4666 17.6278 11.4666 17.3333V7.95422L8.11041 11.3104C7.90213 11.5187 7.56444 11.5187 7.35617 11.3104C7.14788 11.1021 7.14788 10.7645 7.35617 10.5562L11.6228 6.28952Z" fill="white"/>
                </svg>
              `,
              },
            },
            stop: {
              svg: {
                content: `
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M0 3C0 1.34315 1.34315 0 3 0H21C22.6569 0 24 1.34315 24 3V21C24 22.6569 22.6569 24 21 24H3C1.34315 24 0 22.6569 0 21V3Z" fill="#0090FF"/>
                  <rect width="16" height="16" transform="translate(4 4)" fill="white" fill-opacity="0.01"/>
                  <path fill-rule="evenodd" clip-rule="evenodd" d="M6.1333 7.19997C6.1333 6.61087 6.61087 6.1333 7.19997 6.1333H16.8C17.3891 6.1333 17.8666 6.61087 17.8666 7.19997V16.8C17.8666 17.3891 17.3891 17.8666 16.8 17.8666H7.19997C6.61087 17.8666 6.1333 17.3891 6.1333 16.8V7.19997ZM16.8 7.19997H7.19997V16.8H16.8V7.19997Z" fill="white"/>
                </svg>
              `,
              },
            },
          }}
          auxiliaryStyle="
          .aiLoader, .aiCompletedIcon {
            display: inline-block;
            box-sizing: border-box;
            vertical-align: middle;
            margin-right: 8px;
          }
          .aiLoader {
            width: 12px;
            height: 12px;
            border: 2px solid #8B8D98;
            border-bottom-color: transparent;
            border-radius: 50%;
            animation: ai-wizard-rotation 0.8s linear infinite;
          }
          @keyframes ai-wizard-rotation {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
          }
          .aiCompletedIcon {
            position: relative;
            width: 12px;
            height: 12px;
            border: 1.5px solid #30A46C;
            border-radius: 50%;
          }
          .aiCompletedIcon::after {
            content: '';
            position: absolute;
            top: 0px;
            left: 3px;
            width: 3px;
            height: 6px;
            border: solid #30A46C;
            border-width: 0 1.5px 1.5px 0;
            transform: rotate(45deg);
          }
          #chat-view:has(#messages:empty) {
            display: block;
          }
          #input:has(#file-attachment-container[style*='display: block']) {
            margin-top: 40px;
          }
          .text-message h1 {
            font-size: var(--font-size-5);
          }
          .text-message h2 {
            font-size: var(--font-size-4);
          }
          .text-message h3 {
            font-size: var(--font-size-3);
          }
          .text-message h4 {
            font-size: var(--font-size-2);
          }
          .text-message h5 {
            font-size: var(--font-size-1);
          }
        "
        />
        <Box className={styles.aiWizardLegalContainer}>
          <Text>
            These responses are generated by AI, which can make mistakes.
          </Text>
        </Box>
      </Flex>
    )
  );
};

export default AiWizard;
