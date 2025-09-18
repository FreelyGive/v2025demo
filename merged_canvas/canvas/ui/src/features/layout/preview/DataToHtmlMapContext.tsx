import { createContext, useCallback, useContext, useState } from 'react';

import type { ReactNode } from 'react';
import type { ComponentsMap, RegionsMap, SlotsMap } from '@/types/Annotations';

interface ComponentHtmlMapProviderProps {
  children: ReactNode;
}

// Create separate contexts for consumer and updater
const ValueContext = createContext<{
  regionsMap: RegionsMap;
  componentsMap: ComponentsMap;
  slotsMap: SlotsMap;
}>({
  regionsMap: {},
  componentsMap: {},
  slotsMap: {},
});

const UpdateContext = createContext<{
  updateRegionsMap: (newRegions: RegionsMap) => void;
  updateComponentsMap: (newComponents: ComponentsMap) => void;
  updateSlotsMap: (newSlots: SlotsMap) => void;
} | null>(null);

// Custom hooks to use the contexts
export const useDataToHtmlMapValue = () => {
  const context = useContext(ValueContext);
  if (context === undefined) {
    throw new Error('useDataToHtmlMapValue must be used within a Provider');
  }
  return context;
};

export const useDataToHtmlMapUpdater = () => {
  const context = useContext(UpdateContext);
  if (context === null) {
    throw new Error('useDataToHtmlMapUpdater must be used within a Provider');
  }
  return context;
};

// Provider component
export const ComponentHtmlMapProvider: React.FC<
  ComponentHtmlMapProviderProps
> = ({ children }) => {
  const [regionsMap, setRegionsMap] = useState<RegionsMap>({});
  const [componentsMap, setComponentsMap] = useState<ComponentsMap>({});
  const [slotsMap, setSlotsMap] = useState<SlotsMap>({});

  // Memoized updater functions
  const updateRegionsMap = useCallback((newRegions: RegionsMap) => {
    setRegionsMap(newRegions);
  }, []);

  const updateComponentsMap = useCallback((newComponents: ComponentsMap) => {
    setComponentsMap(newComponents);
  }, []);

  const updateSlotsMap = useCallback((newSlots: SlotsMap) => {
    setSlotsMap(newSlots);
  }, []);

  return (
    <ValueContext.Provider value={{ regionsMap, componentsMap, slotsMap }}>
      <UpdateContext.Provider
        value={{ updateRegionsMap, updateComponentsMap, updateSlotsMap }}
      >
        {children}
      </UpdateContext.Provider>
    </ValueContext.Provider>
  );
};

export default ComponentHtmlMapProvider;
