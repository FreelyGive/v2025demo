import { useCallback, useEffect, useRef } from 'react';
import { useLocation } from 'react-router-dom';
import ExtensionIcon from '@assets/icons/extension_sm.svg?react';
import TemplateIcon from '@assets/icons/template.svg?react';
import {
  Component1Icon,
  FileTextIcon,
  LayersIcon,
  PlusIcon,
} from '@radix-ui/react-icons';
import { Button, Flex, Tooltip } from '@radix-ui/themes';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import {
  selectActivePanel,
  setActivePanel,
  setManageLibraryTab,
  unsetActivePanel,
} from '@/features/ui/primaryPanelSlice';
import { getCanvasSettings } from '@/utils/drupal-globals';

import styles from './SideMenu.module.css';

interface SideMenuButton {
  type: 'button';
  id: string;
  icon: React.ReactNode;
  label: string;
  enabled?: boolean;
  hidden?: boolean;
}
interface SideMenuSeparator {
  type: 'separator';
  hidden?: boolean;
}
type SideMenuItem = SideMenuButton | SideMenuSeparator;
const { drupalSettings } = window;

interface SideMenuProps {}

export const SideMenu: React.FC<SideMenuProps> = () => {
  const canvasSettings = getCanvasSettings();
  const activePanel = useAppSelector(selectActivePanel);
  const activePanelRef = useRef(activePanel);
  let hasExtensions = false;
  if (drupalSettings && drupalSettings.canvasExtension) {
    hasExtensions = Object.values(drupalSettings.canvasExtension).length > 0;
  }
  const { pathname } = useLocation();
  const segments = pathname.split('/').filter(Boolean); // removes empty strings
  const isCodeEditor = segments.includes('code-editor');
  const isEditor = segments.includes('editor');

  const dispatch = useAppDispatch();

  const handleMenuClick = useCallback(
    (panelId: string) => {
      if (activePanel === panelId) {
        dispatch(unsetActivePanel());
        return;
      }
      dispatch(setActivePanel(panelId));
    },
    [dispatch, activePanel],
  );

  useEffect(() => {
    activePanelRef.current = activePanel;
  }, [activePanel]);

  /**
   * When coming into the Code Editor switch to the Manage Library panel.
   * When coming into the Editor from the Code Editor switch to the "Add" (library) panel.
   * When coming into the Editor fresh, default to the Layers panel.
   */
  useEffect(() => {
    if (isCodeEditor) {
      dispatch(setActivePanel('manageLibrary'));
      dispatch(setManageLibraryTab('code'));
    } else if (isEditor && activePanelRef.current === 'manageLibrary') {
      // we came from the library to the editor, so switch to "library"
      dispatch(setActivePanel('library'));
    }
  }, [dispatch, isCodeEditor, isEditor]);

  const menuItems: SideMenuItem[] = [
    {
      type: 'button',
      id: 'library',
      icon: <PlusIcon />,
      label: 'Add',
      enabled: true,
      hidden: isCodeEditor,
    },
    {
      type: 'button',
      id: 'layers',
      icon: <LayersIcon />,
      label: 'Layers',
      enabled: true,
      hidden: isCodeEditor,
    },
    { type: 'separator', hidden: isCodeEditor },
    {
      type: 'button',
      id: 'manageLibrary',
      icon: <Component1Icon />,
      label: 'Manage library',
      enabled: true,
      hidden: false,
    },
    { type: 'separator', hidden: !canvasSettings.devMode },
    {
      type: 'button',
      id: 'pages',
      icon: <FileTextIcon />,
      label: 'Pages',
      enabled: true,
      hidden: false,
    },
    {
      type: 'button',
      id: 'templates',
      icon: <TemplateIcon />,
      label: 'Templates',
      enabled: true,
      hidden: !canvasSettings.devMode,
    },
    { type: 'separator', hidden: !hasExtensions },
    {
      type: 'button',
      id: 'extensions',
      icon: <ExtensionIcon />,
      label: 'Extensions',
      enabled: true,
      hidden: !hasExtensions,
    },
  ];

  return (
    <Flex gap="2" className={styles.sideMenu} data-testid="canvas-side-menu">
      {menuItems
        .filter((item) => !item.hidden)
        .map((item, index) =>
          item.type === 'separator' ? (
            <hr key={index} className={styles.separator} />
          ) : (
            <Tooltip key={item.id} content={item.label} side="right">
              <Button
                variant="ghost"
                color="gray"
                disabled={!item.enabled}
                className={`${styles.menuItem} ${item.enabled ? '' : styles.disabled} ${activePanel === item.id ? styles.active : ''}`}
                onClick={
                  item.enabled ? () => handleMenuClick(item.id) : undefined
                }
                aria-label={item.label}
              >
                {item.icon}
              </Button>
            </Tooltip>
          ),
        )}
    </Flex>
  );
};

export default SideMenu;
