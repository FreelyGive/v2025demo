import { createContext, useContext } from 'react';

/** Context to manage the indentation level for nested list items (SidebarNode components).
 * Each level corresponds to a multiple of `var(--space-2)`.
 * This allows SidebarNode to adjust padding based on nesting depth (e.g. top level vs in a folder)
 * without prop drilling through multiple layers of components.
 */
export const ListIndentContext = createContext<number>(0);

export const useIndentContext = () => useContext(ListIndentContext);
