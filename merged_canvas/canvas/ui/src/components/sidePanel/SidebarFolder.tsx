import { useState } from 'react';
import clsx from 'clsx';
import FolderIcon from '@assets/icons/folder.svg?react';
import * as Collapsible from '@radix-ui/react-collapsible';
import { ChevronRightIcon, DotsHorizontalIcon } from '@radix-ui/react-icons';
import { ContextMenu, DropdownMenu, Flex, Text } from '@radix-ui/themes';

import UnifiedMenu from '@/components/UnifiedMenu';

import type React from 'react';

import detailsStyles from '@/components/form/components/AccordionAndDetails.module.css';
import listStyles from '@/components/list/List.module.css';
import nodeStyles from '@/components/sidePanel/SidebarNode.module.css';

interface SidebarFolderProps {
  name: string;
  count?: number;
  menuItems?: React.ReactNode; // If menuItems are provided, wrap in ContextMenu and DropdownMenu
  className?: string;
  isOpen?: boolean;
  onOpenChange?: (open: boolean) => void;
  children?: React.ReactNode;
  contextualMenuType?: 'dropdown' | 'context' | 'both';
}

const SidebarFolder: React.FC<SidebarFolderProps> = ({
  name,
  count,
  menuItems,
  className,
  isOpen: isOpenProp,
  onOpenChange,
  children,
  contextualMenuType = 'both',
}) => {
  const [isOpen, setIsOpen] = useState(isOpenProp ?? true);
  const handleOpenChange = (open: boolean) => {
    setIsOpen(open);
    onOpenChange?.(open);
  };

  const folderRow = (
    <Flex
      data-canvas-folder-name={name}
      className={clsx(listStyles.folderTrigger, {
        [nodeStyles.contextualAccordionVariant]: menuItems,
      })}
      flexGrow="1"
      align="center"
      overflow="hidden"
      pb="2"
      pt="2"
    >
      <Flex pl="2" align="center" flexShrink="0">
        <FolderIcon className={listStyles.folderIcon} />
      </Flex>

      <Flex px="2" align="center" flexGrow="1" overflow="hidden" role="button">
        <Text size="1" weight="medium">
          {name}
        </Text>
      </Flex>

      {menuItems && contextualMenuType !== 'context' && (
        <DropdownMenu.Root>
          <DropdownMenu.Trigger>
            <button
              aria-label="Open contextual menu"
              className={nodeStyles.contextualTrigger}
            >
              <span className={nodeStyles.dots}>
                <DotsHorizontalIcon />
              </span>
            </button>
          </DropdownMenu.Trigger>
          <UnifiedMenu.Content menuType="dropdown">
            {menuItems}
          </UnifiedMenu.Content>
        </DropdownMenu.Root>
      )}
      {typeof count === 'number' && (
        <Flex
          align="end"
          flexShrink="0"
          px="1"
          justify="center"
          className={listStyles.folderCount}
        >
          <Text size="1" weight="medium">
            {String(count)}
          </Text>
        </Flex>
      )}
      <Collapsible.Trigger asChild>
        <Flex
          pl="2"
          align="end"
          flexShrink="0"
          role="button"
          aria-label={`${isOpen ? 'Collapse' : 'Expand'} ${name} folder`}
        >
          <ChevronRightIcon
            className={clsx(listStyles.chevron, {
              [listStyles.isOpen]: isOpen,
            })}
          />
        </Flex>
      </Collapsible.Trigger>
    </Flex>
  );

  let rowWithContextMenu = folderRow;
  if (menuItems && contextualMenuType !== 'dropdown') {
    rowWithContextMenu = (
      <ContextMenu.Root>
        <ContextMenu.Trigger>{folderRow}</ContextMenu.Trigger>
        <UnifiedMenu.Content menuType="context" align="start" side="right">
          {menuItems}
        </UnifiedMenu.Content>
      </ContextMenu.Root>
    );
  }

  return (
    <Collapsible.Root open={isOpen} onOpenChange={handleOpenChange}>
      {rowWithContextMenu}
      <Collapsible.Content
        className={clsx(detailsStyles.content, detailsStyles.detailsContent)}
      >
        <Flex pl="5" direction="column">
          {children}
        </Flex>
      </Collapsible.Content>
    </Collapsible.Root>
  );
};

export default SidebarFolder;
