import { addOrUpdateAutoSavesHash } from '@/components/review/PublishReview.slice';

import type { Dispatch } from 'redux';
import type { AutoSavesHash } from '@/types/AutoSaves';

/**
 * Centralized handler for updating the autosaves hash keyed by request URL.
 * @param dispatch Redux dispatch function
 * @param autoSaves The AutoSavesHash object
 * @param meta Optional meta object from queryFulfilled
 */
export function handleAutoSavesHashUpdate(
  dispatch: Dispatch,
  autoSaves: AutoSavesHash | undefined,
  meta?: any,
) {
  if (!autoSaves) return;
  // key by API endpoint URL
  const url = meta?.request?.url;
  if (typeof url === 'string') {
    // trim to only part after but including canvas/api
    const startIndex = url.indexOf('canvas/api/');
    if (startIndex !== -1) {
      const requestUrl = url.substring(startIndex);
      dispatch(addOrUpdateAutoSavesHash({ [requestUrl]: autoSaves }));
      return;
    }
  }
  console.error(
    'Failed to update autoSavesHash: request URL is invalid or missing canvas/api/',
  );
}
