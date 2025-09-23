import PermissionCheck from '@/components/PermissionCheck';

import AddCodeComponentDialog from './AddCodeComponentDialog';
import AddToComponentsDialog from './AddToComponentsDialog';
import ComponentInLayoutDialog from './ComponentInLayoutDialog';
import DeleteCodeComponentDialog from './DeleteCodeComponentDialog';
import RemoveFromComponentsDialog from './RemoveFromComponentsDialog';
import RenameCodeComponentDialog from './RenameCodeComponentDialog';

const CodeComponentDialogs = () => {
  return (
    <>
      <PermissionCheck hasPermission="codeComponents">
        <AddCodeComponentDialog />
      </PermissionCheck>
      <RenameCodeComponentDialog />
      <DeleteCodeComponentDialog />
      <AddToComponentsDialog />
      <RemoveFromComponentsDialog />
      <ComponentInLayoutDialog />
    </>
  );
};

export default CodeComponentDialogs;
