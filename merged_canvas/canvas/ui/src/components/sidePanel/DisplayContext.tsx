import { createContext, useContext } from 'react';

type DisplayContextType =
  | 'manage-library'
  | 'component-library'
  | 'layout'
  | null;

const DisplayContext = createContext<DisplayContextType>(null);

export const useDisplayContext = () => {
  return useContext(DisplayContext);
};

export { DisplayContext };
