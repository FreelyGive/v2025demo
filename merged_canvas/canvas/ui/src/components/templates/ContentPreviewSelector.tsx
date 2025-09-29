import { ChevronDownIcon, EyeOpenIcon } from '@radix-ui/react-icons';
import { Button, DropdownMenu, Flex, Tooltip } from '@radix-ui/themes';

import type React from 'react';

interface ContentItem {
  id: string;
  label: string;
}

interface ContentPreviewSelectorProps {
  items?: { [key: string]: ContentItem };
  selectedItemId?: string;
  onSelectionChange?: (entityId: string) => void;
}

const ContentPreviewSelector: React.FC<ContentPreviewSelectorProps> = ({
  items = {},
  selectedItemId,
  onSelectionChange,
}) => {
  // Convert items object to array for easier handling
  const itemsArray = Object.values(items);
  const itemsCount = itemsArray.length;

  // Default to first item if no selection and items are available
  const effectiveSelectedId =
    selectedItemId || (itemsCount > 0 ? itemsArray[0].id : undefined);
  const selectedItem = itemsArray.find(
    (item) => item.id === effectiveSelectedId,
  );

  const handleItemSelect = (itemId: string) => {
    if (onSelectionChange) {
      onSelectionChange(itemId);
    }
  };

  // Custom trigger content
  const triggerContent = (
    <Flex gap="2" align="center">
      <EyeOpenIcon />
      <span>
        {itemsCount === 0
          ? 'No content available'
          : (selectedItem?.label ?? 'Select content to preview')}
      </span>
      {itemsCount > 0 && <ChevronDownIcon />}
    </Flex>
  );

  return (
    <Flex>
      {itemsCount === 0 ? (
        <Tooltip content="Preview content" side="bottom">
          <Button variant="soft" size="1" disabled color="blue">
            {triggerContent}
          </Button>
        </Tooltip>
      ) : (
        <DropdownMenu.Root>
          <Tooltip content="Preview content" side="bottom">
            <DropdownMenu.Trigger>
              <Button variant="soft" size="1" color="blue">
                {triggerContent}
              </Button>
            </DropdownMenu.Trigger>
          </Tooltip>
          <DropdownMenu.Content>
            {itemsArray.map((item) => (
              <DropdownMenu.Item
                key={item.id}
                onSelect={() => handleItemSelect(item.id)}
              >
                {item.label}
              </DropdownMenu.Item>
            ))}
          </DropdownMenu.Content>
        </DropdownMenu.Root>
      )}
    </Flex>
  );
};

export default ContentPreviewSelector;
export type { ContentItem, ContentPreviewSelectorProps };
