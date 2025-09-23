import {
  hasAnyPermission as _hasAnyPermission,
  hasPermission as _hasPermission,
  hasPermissions as _hasPermissions,
} from '@/utils/permissions';

type RequireOnlyOnePermission =
  | { hasPermission: string; hasAnyPermission?: never; hasPermissions?: never }
  | {
      hasPermission?: never;
      hasAnyPermission: string[];
      hasPermissions?: never;
    }
  | {
      hasPermission?: never;
      hasAnyPermission?: never;
      hasPermissions: string[];
    };

export function usePermissionCheck(props: RequireOnlyOnePermission): boolean {
  if ('hasPermission' in props && props.hasPermission) {
    return _hasPermission(props.hasPermission);
  }
  if ('hasAnyPermission' in props && props.hasAnyPermission) {
    return _hasAnyPermission(props.hasAnyPermission);
  }
  if ('hasPermissions' in props && props.hasPermissions) {
    return _hasPermissions(props.hasPermissions);
  }
  return false;
}
