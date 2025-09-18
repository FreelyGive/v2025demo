// Utility to remove /region/:regionId from a pathname
export function removeRegionFromPathname(pathname: string): string {
  // Remove all /region/:regionId segments
  const cleaned = pathname.replace(/\/region\/[^/]+/g, '');
  // Remove any double slashes that may result
  return cleaned.replace(/\/\//g, '/').replace(/\/$/, '');
}

// Utility to remove /component/:componentId from a pathname
export function removeComponentFromPathname(pathname: string): string {
  // Remove all /component/:componentId segments
  const cleaned = pathname.replace(/\/component\/[^/]+/g, '');
  // Remove any double slashes that may result
  return cleaned.replace(/\/\//g, '/').replace(/\/$/, '');
}

// Utility to robustly set /region/:regionId in a pathname
export function setRegionInPathname(
  pathname: string,
  regionId?: string,
  defaultRegion?: string,
): string {
  const regionRegex = /\/region\/[^/]+/;
  let newPath = pathname;
  if (regionId === defaultRegion || !regionId) {
    // Remove /region/:regionId if present
    newPath = newPath.replace(regionRegex, '');
  } else {
    if (regionRegex.test(newPath)) {
      // Replace existing /region/:regionId
      newPath = newPath.replace(regionRegex, `/region/${regionId}`);
    } else {
      // Append /region/:regionId
      newPath += `/region/${regionId}`;
    }
  }
  // Clean up double slashes and trailing slash
  return newPath.replace(/\/\//g, '/').replace(/\/$/, '');
}

// Utility to robustly set /component/:componentId in a pathname
export function setComponentInPathname(
  pathname: string,
  componentId?: string,
): string {
  const componentRegex = /\/component\/[^/]+$/;
  let newPath = pathname;
  if (!componentId) {
    // Remove /component/:componentId if present
    newPath = newPath.replace(componentRegex, '');
  } else {
    if (componentRegex.test(newPath)) {
      // Replace existing /component/:componentId
      newPath = newPath.replace(componentRegex, `/component/${componentId}`);
    } else {
      // Ensure no trailing slash before appending
      newPath = newPath.replace(/\/$/, '') + `/component/${componentId}`;
    }
  }
  // Clean up double slashes and trailing slash
  return newPath.replace(/\/\//g, '/').replace(/\/$/, '');
}
