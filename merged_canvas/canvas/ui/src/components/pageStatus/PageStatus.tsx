import { useEffect, useState } from 'react';
import { useParams } from 'react-router';
import { Badge } from '@radix-ui/themes';

import { useAppDispatch } from '@/app/hooks';
import { HOMEPAGE_CONFIG_ID } from '@/components/pageInfo/PageInfo';
import { setHomepageStagedConfigExists } from '@/features/configuration/configurationSlice';
import { useGetPageLayoutQuery } from '@/services/componentAndLayout';
import { useGetAllPendingChangesQuery } from '@/services/pendingChangesApi';
import { findInChanges } from '@/utils/function-utils';

export interface PageStatusBadgeProps {
  isNew: boolean;
  hasAutoSave: boolean;
  isPublished: boolean;
}

export const PageStatusBadge: React.FC<PageStatusBadgeProps> = ({
  isNew,
  hasAutoSave,
  isPublished,
}) => {
  if (isNew) {
    return (
      <Badge size="1" variant="solid" color="blue">
        Draft
      </Badge>
    );
  }

  if (hasAutoSave) {
    return (
      <Badge size="1" variant="solid" color="amber">
        Changed
      </Badge>
    );
  }

  if (isPublished) {
    return (
      <Badge size="1" variant="solid" color="green">
        Published
      </Badge>
    );
  }

  return (
    <Badge size="1" variant="solid" color="gray">
      Archived
    </Badge>
  );
};

const PageStatus = () => {
  const { data: changes, isSuccess: isGetPendingChangesSuccess } =
    useGetAllPendingChangesQuery();
  const { entityType, entityId } = useParams();
  const [hasAutoSave, setHasAutoSave] = useState(false);
  const { data: fetchedLayout, isError } = useGetPageLayoutQuery();
  const dispatch = useAppDispatch();

  useEffect(() => {
    if (changes) {
      const isChanged = findInChanges(changes, entityId, entityType);
      setHasAutoSave(isChanged);
    }
  }, [changes, fetchedLayout, entityId, entityType]);

  // Check if the homepage staged update exists in the current auto-save.
  useEffect(() => {
    if (isGetPendingChangesSuccess) {
      const containsHomepageConfig = Object.prototype.hasOwnProperty.call(
        changes,
        `staged_config_update:${HOMEPAGE_CONFIG_ID}`,
      );
      dispatch(setHomepageStagedConfigExists(containsHomepageConfig));
    }
  }, [changes, dispatch, isGetPendingChangesSuccess]);

  if (fetchedLayout && !isError) {
    const { isNew, isPublished } = fetchedLayout;

    return (
      <PageStatusBadge
        isPublished={isPublished}
        isNew={isNew}
        hasAutoSave={hasAutoSave}
      />
    );
  }
};

export default PageStatus;
