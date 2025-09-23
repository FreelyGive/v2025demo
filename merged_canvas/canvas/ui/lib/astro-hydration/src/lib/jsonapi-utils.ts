interface Node {
  path?: {
    alias?: string;
  };
  drupal_internal__nid?: number;
}

interface MenuItem {
  id: string | number;
  parent?: string | number;
  _children?: MenuItem[];
  _hasSubmenu: boolean;
}

/**
 * Given a node returned from JSON:API, return either the path alias or fall back
 * to /node/x path.
 *
 * @param node
 *
 * @return string
 */
export function getNodePath(node: Node) {
  return (
    node.path?.alias ||
    (node.drupal_internal__nid ? `/node/${node.drupal_internal__nid}` : '#')
  );
}

/**
 * Sort menu items from jsonapi_menu_items module into a tree with additional
 * _children and _hasSubmenu properties.
 *
 * @param menuItems
 *
 * @return menuItems
 */
export function sortMenu(menuItems: MenuItem[]) {
  const menuItemsMap = new Map();
  const menu: MenuItem[] = [];

  menuItems.forEach((menuItem) => {
    menuItemsMap.set(menuItem.id, {
      ...menuItem,
      _children: [],
      _hasSubmenu: false,
    });
  });

  menuItems.forEach((menuItem) => {
    const node = menuItemsMap.get(menuItem.id);
    if (menuItem.parent && menuItemsMap.has(menuItem.parent)) {
      const parent = menuItemsMap.get(menuItem.parent);
      parent._children.push(node);
      parent._hasSubmenu = true;
    } else {
      menu.push(node);
    }
  });

  return menu;
}
