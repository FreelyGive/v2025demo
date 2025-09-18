import { useEffect, useRef } from 'react';
import { createRoot } from 'react-dom/client';

import type { ReactNode } from 'react';
import type React from 'react';
import type { Root } from 'react-dom/client';

interface ShadowWrapperProps {
  children: ReactNode;
}

const ShadowWrapper: React.FC<ShadowWrapperProps> = ({ children }) => {
  const containerRef = useRef<HTMLDivElement | null>(null);
  const rootRef = useRef<Root | null>(null);

  useEffect(() => {
    if (containerRef.current && !rootRef.current) {
      const shadowRoot = containerRef.current.attachShadow({ mode: 'open' });

      // Create the root directly on the shadowRoot
      rootRef.current = createRoot(shadowRoot);
    }

    if (rootRef.current) {
      rootRef.current.render(<>{children}</>);
    }
  }, [children]);

  return <div className="shadowDomWrapper" ref={containerRef}></div>;
};

export default ShadowWrapper;
