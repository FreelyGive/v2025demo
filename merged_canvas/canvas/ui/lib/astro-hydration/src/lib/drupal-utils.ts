interface Linkset {
  linkset: LinksetItem[];
}

interface LinksetItem {
  anchor: string;
  item: LinksetMenuItem[];
}

interface LinksetMenuItem {
  href: string;
  hierarchy: string[];
  _children?: LinksetMenuItem[];
  _hasSubmenu: boolean;
}

/**
 * Sort menu items from core's menu API into a tree with additional
 * _children and _hasSubmenu properties.
 *
 * @param linkset
 *
 * @return menuItems
 */
export function sortMenu(linkset: Linkset) {
  const menuItemsMap = new Map();
  const menu: LinksetMenuItem[] = [];

  if (!linkset.linkset?.length) {
    return [];
  }

  linkset.linkset[0].item.forEach((item) => {
    const hierarchyKey = item.hierarchy.join('|');
    menuItemsMap.set(hierarchyKey, {
      ...item,
      id: hierarchyKey,
      _children: [],
      _hasSubmenu: false,
    });
  });

  linkset.linkset[0].item.forEach((item) => {
    const hierarchyKey = item.hierarchy.join('|');
    const currentItem = menuItemsMap.get(hierarchyKey);

    if (item.hierarchy.length === 1) {
      // Root level item.
      menu.push(currentItem);
    } else {
      // Child item.
      const parentHierarchy = item.hierarchy.slice(0, -1);
      const parentKey = parentHierarchy.join('|');
      const parent = menuItemsMap.get(parentKey);
      if (parent) {
        parent._children.push(currentItem);
        parent._hasSubmenu = true;
      }
    }
  });

  return menu;
}

interface BreadcrumbLink {
  key: string;
  text: string;
  url: string;
}

interface PageData {
  pageTitle: string;
  breadcrumbs: Array<BreadcrumbLink>;
}

interface SiteData {
  branding: {
    homeUrl: string;
    siteName: string;
    siteSlogan: string;
  };
  baseUrl: string;
}

export const getPageData = (): PageData => {
  const pageData = {
    pageTitle: window.drupalSettings?.canvasData?.v0?.pageTitle || '',
    breadcrumbs: window.drupalSettings?.canvasData?.v0?.breadcrumbs || [],
  };
  window.parent.postMessage({
    type: '_canvas_useswr_data_fetch',
    id: 'getPageData()',
    data: pageData,
  });
  return pageData;
};

export const getSiteData = (): SiteData => {
  const siteData = {
    branding: window.drupalSettings?.canvasData?.v0?.branding || {
      homeUrl: '',
      siteName: '',
      siteSlogan: '',
    },
    baseUrl: window.drupalSettings?.canvasData?.v0?.baseUrl || '/',
  };
  window.parent.postMessage({
    type: '_canvas_useswr_data_fetch',
    id: 'getSiteData()',
    data: siteData,
  });
  return siteData;
};
