import { Toaster } from 'sonner';
import { Spinner } from '@radix-ui/themes';

import type { CSSProperties } from 'react';

const toastStyles: CSSProperties = {
  alignItems: 'center',
  borderRadius: 'var(--radius-4)',
  border: 'none',
  background: 'var(--blue-9)',
  boxShadow: 'var(--shadow-4)',
  color: '#fff',
  width: '144px',
  height: '32px',
  whiteSpace: 'nowrap',
  padding: '5px',
  fontSize: '12px',
  justifyContent: 'center',
  position: 'relative',
  left: '50%',
  transform: 'translateX(-50%)',
};

const Toast = () => {
  return (
    <Toaster
      position="bottom-center"
      visibleToasts={1}
      toastOptions={{
        style: toastStyles,
      }}
      icons={{
        loading: <Spinner style={{ color: 'white' }} />,
      }}
    />
  );
};

export default Toast;
