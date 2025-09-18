import { getCanvasPermissions } from '@/utils/drupal-globals';

const permissions = getCanvasPermissions();
const userPermissions: string[] = Object.keys(permissions).filter((key) => {
  return permissions[key] ? key : false;
});

export const hasPermission = (permission: string): boolean => {
  return userPermissions.includes(permission);
};

export const hasPermissions = (requiredPermissions: string[]): boolean => {
  return requiredPermissions.every((permission) =>
    userPermissions.includes(permission),
  );
};

export const hasAnyPermission = (requiredPermissions: string[]): boolean => {
  return requiredPermissions.some((permission) =>
    userPermissions.includes(permission),
  );
};
