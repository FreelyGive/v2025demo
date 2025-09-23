import { useEffect, useRef } from 'react';
import { useParams } from 'react-router-dom';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import { selectIsMultiSelect, setSelection } from '@/features/ui/uiSlice';

// A one way sync to make sure that if the URL is updated, the state is updated too.
function useSyncParamsToState() {
  const dispatch = useAppDispatch();
  const params = useParams();
  const isMultiSelect = useAppSelector(selectIsMultiSelect);
  const isFirstRun = useRef(true);

  useEffect(() => {
    // If we're in multi-select mode and not on first run, don't update the component selection from the URL
    // This prevents losing our multi-selection when we update the URL
    if (!isMultiSelect || isFirstRun.current) {
      if (params?.componentId) {
        dispatch(
          setSelection({
            items: [params.componentId],
          }),
        );
      }
      isFirstRun.current = false;
    }
  }, [dispatch, params, isMultiSelect]);
}

export default useSyncParamsToState;
