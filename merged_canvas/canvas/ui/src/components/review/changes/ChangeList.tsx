import { Box } from '@radix-ui/themes';

import ChangeGroup from './ChangeGroup';

import type {
  UnpublishedChange,
  UnpublishedChangeGroups,
} from '@/types/Review';

interface ChangeListProps {
  groups: UnpublishedChangeGroups;
  isBusy: boolean;
  selectedChanges: UnpublishedChange[];
  setSelectedChanges: (changes: UnpublishedChange[]) => void;
  onDiscardClick: (change: UnpublishedChange) => void;
  onViewClick?: (change: UnpublishedChange) => void;
}

const ChangeList = ({
  groups,
  isBusy,
  selectedChanges,
  setSelectedChanges,
  onDiscardClick,
  onViewClick,
}: ChangeListProps) => {
  return (
    groups && (
      <Box data-testid="pending-changes-list">
        {Object.entries(groups).map(([entityType, changes]) => {
          return (
            <ChangeGroup
              key={entityType}
              entityType={entityType}
              changes={changes}
              isBusy={isBusy}
              selectedChanges={selectedChanges}
              setSelectedChanges={setSelectedChanges}
              onDiscardClick={onDiscardClick}
              onViewClick={onViewClick}
            />
          );
        })}
      </Box>
    )
  );
};

export default ChangeList;
