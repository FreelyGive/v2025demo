import { useEffect } from 'react';
import { useLocation } from 'react-router-dom';

import { useAppDispatch } from '@/app/hooks';
import { setPreviouslyEdited } from '@/features/ui/uiSlice';
import { useEntityTitle } from '@/hooks/useEntityTitle';

const useReturnableLocation = () => {
  const { pathname } = useLocation();
  const dispatch = useAppDispatch();
  const entityTitle = useEntityTitle();

  useEffect(() => {
    const segments = pathname.split('/').filter(Boolean);
    const isEditor = segments.includes('editor');
    const isTemplateEditor = segments.includes('template');
    if (isEditor && entityTitle) {
      dispatch(setPreviouslyEdited({ path: pathname, name: entityTitle }));
    }
    if (isTemplateEditor) {
      dispatch(
        setPreviouslyEdited({ path: pathname, name: 'Template Editor' }),
      );
    }
  }, [dispatch, pathname, entityTitle]);
};

export default useReturnableLocation;
