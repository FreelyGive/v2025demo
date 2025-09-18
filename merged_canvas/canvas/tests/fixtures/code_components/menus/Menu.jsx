import { useState } from 'react';
import useSWR from 'swr';
import { JsonApiClient } from '@drupal-api-client/json-api-client';

import { sortMenu as drupalSortMenu } from '@/lib/drupal-utils';
import { sortMenu as jsonapiSortMenu } from '@/lib/jsonapi-utils';
import { cn } from '@/lib/utils';

const client = new JsonApiClient();

export default function Menu() {
  const {
    data: drupalData,
    error: drupalError,
    isLoading: drupalIsLoading,
  } = useSWR('/system/menu/main/linkset', async (url) => {
    const response = await fetch(url);
    return response.json();
  });

  const {
    data: jsonapiData,
    error: jsonapiError,
    isLoading: jsonapiLoading,
  } = useSWR(['menu_items', 'main'], ([type, resourceId]) =>
    client.getResource(type, resourceId),
  );

  if (jsonapiError || drupalError) return 'An error has occurred.';
  if (jsonapiLoading || drupalIsLoading) return 'Loading...';

  return (
    <>
      <h2>JSON:API Menu</h2>
      <MenuItems menu={jsonapiSortMenu(jsonapiData)} />
      <h2>Core Linkset Menu</h2>
      <MenuItems menu={drupalSortMenu(drupalData)} />
    </>
  );
}

const MenuItems = ({ menu }) => {
  const [open, setOpen] = useState(false);
  const toggleMenu = (open) => {
    setOpen(!open);
  };

  const [openSubmenus, setOpenSubmenu] = useState({});
  const toggleSubmenu = (menuKey) => {
    setOpenSubmenu((prev) => ({
      ...prev,
      [menuKey]: !prev[menuKey],
    }));
  };

  return (
    <div data-testid="menu">
      <div
        className={`flex justify-end md:hidden ${open ? 'absolute right-0' : ''}`}
      >
        <button
          type="button"
          className="relative flex size-9 items-center justify-center rounded-lg border border-gray-200 text-sm font-semibold text-gray-800 hover:bg-gray-100 focus:bg-gray-100 focus:outline-none disabled:pointer-events-none cursor-pointer`"
          aria-expanded={open}
          aria-label="Toggle navigation"
          onClick={() => {
            toggleMenu(open);
          }}
          data-testid="open-menu"
        >
          <svg
            className={cn('size-4 cursor-pointer', open ? 'hidden' : '')}
            xmlns="http://www.w3.org/2000/svg"
            width="24"
            height="24"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
          >
            <line x1="3" x2="21" y1="6" y2="6" />
            <line x1="3" x2="21" y1="12" y2="12" />
            <line x1="3" x2="21" y1="18" y2="18" />
          </svg>
          <svg
            className={cn(
              'size-4 shrink-0 cursor-pointer',
              open ? '' : 'hidden',
            )}
            xmlns="http://www.w3.org/2000/svg"
            width="24"
            height="24"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
          >
            <path d="M18 6 6 18" />
            <path d="m6 6 12 12" />
          </svg>
          <span className="sr-only">Toggle navigation</span>
        </button>
      </div>
      <nav
        className={cn(
          'w-screen border-b border-solid border-slate-200 bg-white px-10 py-6 md:static md:block md:w-full md:border-none md:px-8 md:py-0 pt-0',
          open ? '' : 'hidden',
        )}
        role="navigation"
        aria-label="Main navigation"
      >
        <ul
          className="flex flex-col gap-0.5 py-2 md:flex-row md:items-center md:justify-center md:gap-1 md:py-0"
          data-testid="menu-links"
        >
          {menu.map((menuItem) => {
            const menuKey = `menu-${menuItem.id}`;
            const isOpen = openSubmenus[menuKey];
            return (
              <li
                key={menuItem.id}
                className={`${
                  menuItem._hasSubmenu
                    ? isOpen
                      ? 'has-submenu open'
                      : 'has-submenu'
                    : 'flex items-center'
                } relative`}
              >
                <a
                  href={menuItem.url ?? menuItem.href ?? '#'}
                  className={`p-2 text-sm text-blue-600 focus:text-blue-600 focus:outline-none dark:text-blue-500 dark:focus:text-blue-500 ${window.parent.location.pathname === menuItem.url || window.parent.location.pathname === menuItem.href ? 'bg-blue-50 rounded' : ''}`}
                  aria-expanded={menuItem._hasSubmenu ? isOpen : undefined}
                >
                  {menuItem.title}
                </a>
                {menuItem._hasSubmenu && (
                  <>
                    <button
                      aria-expanded={isOpen}
                      onClick={(e) => {
                        e.preventDefault();
                        toggleSubmenu(menuKey);
                      }}
                      className="bg-transparent border border-gray-300 p-2 ml-1 cursor-pointer align-center hover:bg-gray-100 focus:bg-gray-100"
                      data-testid="open-submenu"
                    >
                      <span
                        className={`inline-block w-0 h-0 border-l-4 border-r-4 border-l-transparent border-r-transparent ${isOpen ? 'border-b-4 border-b-gray-800' : 'border-t-4 border-t-gray-800'}`}
                      >
                        <span className="hidden">
                          show submenu for “{menuItem.title}”
                        </span>
                      </span>
                    </button>
                    <ul
                      className={`absolute top-full left-0 bg-white border border-gray-300 shadow-lg min-w-[200px] list-none p-0 m-0 z-[1000] ${isOpen ? 'block' : 'hidden'}`}
                      data-testid="submenu"
                    >
                      {menuItem._children.map((submenuItem) => (
                        <li key={submenuItem.id} className="block w-full">
                          <a
                            href={submenuItem.url ?? submenuItem.href ?? '#'}
                            className="block w-full py-3 px-4 border-0 border-b border-gray-200 hover:bg-gray-50 focus:bg-gray-50"
                          >
                            {submenuItem.title}
                          </a>
                        </li>
                      ))}
                    </ul>
                  </>
                )}
              </li>
            );
          })}
        </ul>
      </nav>
    </div>
  );
};
