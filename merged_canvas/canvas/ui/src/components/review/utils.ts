/**
 * Provides utility functions for the PublishReview components
 */

import { differenceInMonths, format, formatDistanceToNow } from 'date-fns';
import { kebabCase } from 'lodash';

import { FallbackColor } from '@/types/Review';

// @todo https://www.drupal.org/i/3501449 - this color randomizer should be replaced with a proper solution
const colors = Object.values(FallbackColor);
const usernameColorMap: Map<number, FallbackColor> = new Map();
// Initialize colorIndex with a random starting point
let colorIndex = Math.floor(Math.random() * colors.length);

/**
 * Function to get a consistent color for a given username
 * @param userId
 */
export function getAvatarInitialColor(userId: number): FallbackColor {
  // Return the cached color if it exists
  if (usernameColorMap.has(userId)) {
    return usernameColorMap.get(userId)!;
  }

  const color = colors[colorIndex];
  // Store the color in the map for future reference
  usernameColorMap.set(userId, color);
  // Increment the color index, wrapping around if necessary
  colorIndex = (colorIndex + 1) % colors.length;

  return color;
}

/**
 * Returns a human-readable label for a given entity type.
 *
 * Maps known entity type strings to their corresponding group labels.
 * If the entity type is not recognized, returns a kebab-case version of the input.
 *
 * @param entityType - The type of the entity to get the label for.
 * @returns The group label corresponding to the entity type.
 */
export function getGroupLabel(entityType: string): string {
  switch (entityType) {
    case 'node':
      return 'Content';
    case 'canvas_page':
      return 'Pages';
    case 'js_component':
      return 'Components';
    case 'asset_library':
      return 'Assets';
    case 'page_region':
      return 'Regions';
    case 'staged_config_update':
      return 'Configuration updates';
    case 'content_template':
      return 'Content templates';
    default:
      return kebabCase(entityType);
  }
}

/**
 * Returns a human-readable string representing the time elapsed since the given timestamp.
 *
 * - If the timestamp is older than one month, the date is formatted as "d MMM" (e.g., "5 Jan").
 * - For more recent dates, a relative time string is returned (e.g., "2h ago", "5m ago"),
 *   with units abbreviated according to a custom mapping.
 *
 * @param timestamp - The UNIX timestamp (in seconds) to calculate the elapsed time from.
 * @returns A formatted string indicating how long ago the timestamp occurred.
 */
export const getTimeAgo = (timestamp: number) => {
  const dateInMilliseconds = timestamp * 1000;
  const inputDate = new Date(dateInMilliseconds);

  // Calculate the difference in months
  const monthsDifference = differenceInMonths(new Date(), inputDate);

  // If the date is older than 1 month, use "dd MMM" format
  if (monthsDifference >= 1) {
    // @todo Implement Drupal-Specific Date Formatting(https://www.drupal.org/project/canvas/issues/3493779)
    return format(inputDate, 'd MMM');
  }

  const timeAgo = formatDistanceToNow(inputDate, { addSuffix: true });

  // Define a mapping for units
  const unitMappings: Record<string, string> = {
    'less than a minute': 'a moment',
    ' seconds': 's',
    ' second': 's',
    ' minutes': 'm',
    ' minute': 'm',
    ' hours': 'h',
    ' hour': 'h',
    ' days': 'd',
    ' day': 'd',
    ' month': 'mo',
    'about ': '',
  };

  return timeAgo.replace(
    new RegExp(Object.keys(unitMappings).join('|'), 'g'),
    (matched) => unitMappings[matched],
  );
};
