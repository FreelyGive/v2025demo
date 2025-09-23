/**
 * Unified component for `ContextMenu` and `DropdownMenu` from Radix Themes.
 *
 * @see https://www.radix-ui.com/themes/docs/components/context-menu
 * @see https://www.radix-ui.com/themes/docs/components/dropdown-menu
 *
 * Use `<ContextMenu.Root>` and `<ContextMenu.Trigger>` or `<DropdownMenu.Root>`
 * and `<DropdownMenu.Trigger>` like you would with the original components.
 * Replace `<ContextMenu.Content>` and `<DropdownMenu.Content>` with
 * `<UnifiedMenu.Content>` provided by this component to implement a component
 * tree that can be used as both a context menu (right-click) and a dropdown
 * menu (left-click).
 */

import { createContext, useContext } from 'react';
import { ContextMenu, DropdownMenu } from '@radix-ui/themes';

type UnifiedProps<
  T extends keyof typeof ContextMenu & keyof typeof DropdownMenu,
> = React.ComponentProps<(typeof ContextMenu)[T]> &
  React.ComponentProps<(typeof DropdownMenu)[T]>;

export type UnifiedMenuType = 'context' | 'dropdown';

const UnifiedMenuTypeContext = createContext<UnifiedMenuType | null>(null);

const useMenuType = () => {
  const menuType = useContext(UnifiedMenuTypeContext);
  if (menuType === null) {
    throw new Error(
      'Unified menu components must be used within a UnifiedMenuContent context',
    );
  }
  return menuType;
};

const UnifiedMenuContent = (
  props: UnifiedProps<'Content'> & { menuType: UnifiedMenuType },
) => {
  const { menuType, ...contentProps } = props;
  return (
    <UnifiedMenuTypeContext.Provider value={menuType}>
      {menuType === 'context' ? (
        <ContextMenu.Content {...contentProps} />
      ) : (
        <DropdownMenu.Content {...contentProps} />
      )}
    </UnifiedMenuTypeContext.Provider>
  );
};

const UnifiedMenuLabel = (props: UnifiedProps<'Label'>) => {
  const menuType = useMenuType();
  return menuType === 'context' ? (
    <ContextMenu.Label {...props} />
  ) : (
    <DropdownMenu.Label {...props} />
  );
};

const UnifiedMenuItem = (props: UnifiedProps<'Item'>) => {
  const menuType = useMenuType();
  return menuType === 'context' ? (
    <ContextMenu.Item {...props} />
  ) : (
    <DropdownMenu.Item {...props} />
  );
};

const UnifiedMenuGroup = (props: UnifiedProps<'Group'>) => {
  const menuType = useMenuType();
  return menuType === 'context' ? (
    <ContextMenu.Group {...props} />
  ) : (
    <DropdownMenu.Group {...props} />
  );
};

const UnifiedMenuRadioGroup = (props: UnifiedProps<'RadioGroup'>) => {
  const menuType = useMenuType();
  return menuType === 'context' ? (
    <ContextMenu.RadioGroup {...props} />
  ) : (
    <DropdownMenu.RadioGroup {...props} />
  );
};

const UnifiedMenuRadioItem = (props: UnifiedProps<'RadioItem'>) => {
  const menuType = useMenuType();
  return menuType === 'context' ? (
    <ContextMenu.RadioItem {...props} />
  ) : (
    <DropdownMenu.RadioItem {...props} />
  );
};

const UnifiedMenuCheckboxItem = (props: UnifiedProps<'CheckboxItem'>) => {
  const menuType = useMenuType();
  return menuType === 'context' ? (
    <ContextMenu.CheckboxItem {...props} />
  ) : (
    <DropdownMenu.CheckboxItem {...props} />
  );
};

const UnifiedMenuSub = (props: UnifiedProps<'Sub'>) => {
  const menuType = useMenuType();
  return menuType === 'context' ? (
    <ContextMenu.Sub {...props} />
  ) : (
    <DropdownMenu.Sub {...props} />
  );
};

const UnifiedMenuSubTrigger = (props: UnifiedProps<'SubTrigger'>) => {
  const menuType = useMenuType();
  return menuType === 'context' ? (
    <ContextMenu.SubTrigger {...props} />
  ) : (
    <DropdownMenu.SubTrigger {...props} />
  );
};

const UnifiedMenuSubContent = (props: UnifiedProps<'SubContent'>) => {
  const menuType = useMenuType();
  return menuType === 'context' ? (
    <ContextMenu.SubContent {...props} />
  ) : (
    <DropdownMenu.SubContent {...props} />
  );
};

const UnifiedMenuSeparator = (props: UnifiedProps<'Separator'>) => {
  const menuType = useMenuType();
  return menuType === 'context' ? (
    <ContextMenu.Separator {...props} />
  ) : (
    <DropdownMenu.Separator {...props} />
  );
};

export const UnifiedMenu = {
  Content: UnifiedMenuContent,
  Label: UnifiedMenuLabel,
  Item: UnifiedMenuItem,
  Group: UnifiedMenuGroup,
  RadioGroup: UnifiedMenuRadioGroup,
  RadioItem: UnifiedMenuRadioItem,
  CheckboxItem: UnifiedMenuCheckboxItem,
  Sub: UnifiedMenuSub,
  SubTrigger: UnifiedMenuSubTrigger,
  SubContent: UnifiedMenuSubContent,
  Separator: UnifiedMenuSeparator,
} as const;

export default UnifiedMenu;
