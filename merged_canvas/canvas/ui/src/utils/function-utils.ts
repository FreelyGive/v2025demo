import type { PendingChanges } from '@/services/pendingChangesApi';
import type {
  BoundingRect,
  ComponentsMap,
  RegionsMap,
  SlotsMap,
  StackDirection,
} from '@/types/Annotations';
import type { PropsValues } from '@/types/Form';

export function handleNonWorkingBtn(): void {
  alert('Not yet supported.');
}

export const preventHover = (event: any) => {
  const e = event as Event;
  e.preventDefault();
};

/**
 * Checks if an array of numbers contains consecutive values (each value is exactly one more than the previous).
 * For example, [1,2,3,4] are consecutive but [1,2,4,5] are not.
 *
 * @param sortedIndexes - Array of numbers in ascending order to check for consecutiveness
 * @returns True if all values are consecutive, or if the array has 0-1 elements. False otherwise.
 */
export function isConsecutive(sortedIndexes: number[]): boolean {
  for (let i = 1; i < sortedIndexes.length; i++) {
    if (sortedIndexes[i] !== sortedIndexes[i - 1] + 1) {
      return false;
    }
  }
  return true;
}

// Some prop shapes do not have a means of representing an empty value, so they
// can't simply have their value replaced when removed. This special string
// flags items for removal that can't be replaced with an empty value.
export const VALUE_THAT_MEANS_REMOVE = '$%^&*JUST_REMOVE';

/**
 * Checks if a value is flagged for removal.
 *
 *  @param {any} value
 *    The value to check.
 */
export function flaggedForRemoval(value: any): boolean {
  // This will return true if any value within the structure equals
  // VALUE_THAT_MEANS_REMOVE.

  if (value === null || value === undefined) {
    return false;
  }

  if (typeof value === 'string') {
    return value === VALUE_THAT_MEANS_REMOVE;
  }

  if (Array.isArray(value)) {
    return value.some((item) => flaggedForRemoval(item));
  }

  if (typeof value === 'object') {
    return Object.values(value).some((item) => flaggedForRemoval(item));
  }

  return false;
}

export function parseValue(
  value: any,
  element: HTMLInputElement | HTMLSelectElement,
  schema: PropsValues | null,
) {
  if (schema?.type === 'string') {
    return `${value}`;
  }
  if (schema?.type === 'number') {
    const parsed = Number(value);
    return isNaN(parsed) ? value : parsed;
  }
  if (element && Object.prototype.hasOwnProperty.call(element, 'checked')) {
    return (element as HTMLInputElement).checked;
  }
  if (value === '') {
    return value;
  }
  const parsed = Number(value);
  return isNaN(parsed) ? value : parsed;
}

/**
 * Returns the scroll position to center the scroll exactly half way horizontally.
 * @param parent
 */
export function getHalfwayScrollPosition(parent: HTMLElement | null) {
  if (parent) {
    // Calculate the maximum possible scrollLeft value (total scrollable width).
    const maxScrollLeft = parent.scrollWidth - parent.clientWidth;
    // Return the halfway scroll position.
    return maxScrollLeft / 2;
  }
  return 0;
}

/**
 * Calculates the horizontal and vertical distance between the first and second passed element||group of elements.
 *
 * @param el1 - The first element or array of elements.
 * @param el2 - The second element or array of elements.
 * @returns An object containing the horizontal and vertical distances between the elements.
 */
export function getDistanceBetweenElements(
  el1: HTMLElement | HTMLElement[],
  el2: HTMLElement | HTMLElement[],
): { horizontalDistance: number; verticalDistance: number } {
  const rect1 = calculateBoundingRect(el1);
  const rect2 = calculateBoundingRect(el2);

  if (rect1 === null || rect2 === null) {
    return {
      horizontalDistance: 0,
      verticalDistance: 0,
    };
  }

  // Calculate the horizontal and vertical distances
  const dx = rect2.left - rect1.left;
  const dy = rect2.top - rect1.top;

  return {
    horizontalDistance: dx,
    verticalDistance: dy,
  };
}

/**
 * Calculates the bounding rectangle that encompasses all the provided elements.
 *
 * @param elements - A single DOM element or an array of DOM elements.
 * @returns The bounding rectangle with properties: top, left, width, and height.
 *          Returns null if elements are not provided or invalid.
 */
export function calculateBoundingRect(
  elements: HTMLElement | HTMLElement[] | null,
): BoundingRect | null {
  if (!elements) {
    return null;
  }

  const elementsArray = Array.isArray(elements) ? elements : [elements];
  const expandedElements: HTMLElement[] = [];

  elementsArray.forEach((el) => {
    if (!el) {
      return;
    }

    function collectElementsWithCalculableSize(
      el: HTMLElement,
      expandedElements: HTMLElement[],
    ) {
      const style = window.getComputedStyle(el);
      if (style.display !== 'contents') {
        expandedElements.push(el);
        return;
      }
      // If the element is display: contents; we need to look at its children to find the first child that has a calculable size.
      // Recursively traverse down children looking for the first child that is not display: contents or until finding
      // multiple children (which suggests we are not dealing with <astro-*> elements and have traversed into the component markup itself).
      if (el.children.length === 1) {
        const onlyChild = el.children[0];
        if (onlyChild.nodeType === Node.ELEMENT_NODE) {
          collectElementsWithCalculableSize(
            onlyChild as HTMLElement,
            expandedElements,
          );
          return;
        }
      } else if (el.children.length > 1) {
        expandedElements.push(
          ...Array.from(el.children).filter(
            (child): child is HTMLElement =>
              child.nodeType === Node.ELEMENT_NODE,
          ),
        );
        return;
      }
    }

    // Elements that are display: contents; (e.g <astro-*> elements) take on the size of their children, so in that case,
    // we add the first children we find that are not display: contents to the array instead.
    collectElementsWithCalculableSize(el, expandedElements);
  });

  const tops: number[] = [];
  const lefts: number[] = [];
  const rights: number[] = [];
  const bottoms: number[] = [];

  expandedElements.forEach((el) => {
    const rect = el.getBoundingClientRect();
    if (elemIsVisible(el)) {
      tops.push(rect.top);
      lefts.push(rect.left);
      rights.push(rect.left + rect.width);
      bottoms.push(rect.top + rect.height);
    }
  });

  const minTop = getMinOfArray(tops);
  const minLeft = getMinOfArray(lefts);
  return {
    top: minTop,
    left: minLeft,
    width: getMaxOfArray(rights) - minLeft,
    height: getMaxOfArray(bottoms) - minTop,
  };
}

export function elemIsVisible(elem: HTMLElement) {
  return !!(
    elem.offsetWidth ||
    elem.offsetHeight ||
    elem.getClientRects().length
  );
}

export function getMaxOfArray(numArray: number[]) {
  if (numArray.length === 0) {
    return 0;
  }
  return Math.max.apply(null, numArray);
}

export function getMinOfArray(numArray: number[]) {
  if (numArray.length === 0) {
    return 0;
  }
  return Math.min.apply(null, numArray);
}

/**
 * Returns a SlotsMap containing all the slots in the passed `document` keyed by the ID of the slot.
 * @param document
 */
export function mapSlots(document: Document): SlotsMap {
  const walker = document.createTreeWalker(
    document.body,
    NodeFilter.SHOW_COMMENT,
  );
  let currentNode = walker.nextNode();
  const slotsMap: SlotsMap = {};

  while (currentNode) {
    // Adjust regex to capture UUID and slot name, ignoring whitespace
    const slotMatch = /^\s*canvas-slot-start-([\w-]+)\/([\w-]+)\s*$/.exec(
      currentNode.nodeValue || '',
    );

    if (slotMatch) {
      const uuid = slotMatch[1];
      const slotName = slotMatch[2];
      const slotId = `${uuid}/${slotName}`;

      // Ensure the parent element exists and is an HTMLElement
      const parentElement = currentNode.parentElement;
      if (parentElement) {
        parentElement.dataset.canvasSlotId = slotId;
        slotsMap[slotId] = {
          element: parentElement,
          componentUuid: uuid,
          slotName: slotName,
          stackDirection: getStackingDirection(parentElement),
        };
      }
    }

    currentNode = walker.nextNode();
  }

  return slotsMap;
}

/**
 * Returns a ComponentsMap containing all the components in the passed `document` keyed by the ID of the component.
 * @param document
 */
export function mapComponents(document: Document): ComponentsMap {
  const walker = document.createTreeWalker(
    document.body,
    NodeFilter.SHOW_COMMENT,
  );
  let currentNode = walker.nextNode();
  const componentMap: ComponentsMap = {};

  while (currentNode) {
    const startMatch = /^\s*canvas-start-([\w-]+)\s*$/.exec(
      currentNode.nodeValue || '',
    );

    if (startMatch) {
      const uuid = startMatch[1];
      let sibling = currentNode.nextSibling;
      componentMap[uuid] = {
        componentUuid: uuid,
        elements: [],
      };

      // Traverse siblings until the end comment is found
      while (
        sibling &&
        !(
          sibling.nodeType === Node.COMMENT_NODE &&
          sibling.nodeValue?.trim() === `canvas-end-${uuid}`
        )
      ) {
        if (sibling.nodeType === Node.ELEMENT_NODE) {
          (sibling as HTMLElement).dataset.canvasUuid = uuid;
          componentMap[uuid].elements.push(sibling as HTMLElement);
        }
        sibling = sibling.nextSibling;
      }
    }

    currentNode = walker.nextNode();
  }

  return componentMap;
}

/**
 * Returns a RegionsMap containing all the regions in the passed `document` keyed by the ID of the region.
 * @param document
 */
export function mapRegions(document: Document): RegionsMap {
  const regionMap: RegionsMap = {};
  const walker = document.createTreeWalker(
    document.body,
    NodeFilter.SHOW_COMMENT,
  );
  let currentNode = walker.nextNode();
  while (currentNode) {
    const startMatch = /^\s*canvas-region-start-([\w-]+)\s*$/.exec(
      currentNode.nodeValue || '',
    );

    if (startMatch) {
      const regionId = startMatch[1];
      if (regionId === 'content') {
        // Content region is a special case where the container div.region is the parent of the comment.
        regionMap[regionId] = {
          elements: [currentNode.parentElement as HTMLElement],
          regionId,
        };
      } else {
        regionMap[regionId] = {
          elements: [],
          regionId,
        };
        let sibling = currentNode.nextSibling;
        while (
          sibling &&
          !(
            sibling.nodeType === Node.COMMENT_NODE &&
            sibling.nodeValue?.trim() === `canvas-region-end-${regionId}`
          )
        ) {
          if (sibling.nodeType === Node.ELEMENT_NODE) {
            regionMap[regionId].elements.push(sibling as HTMLElement);
          }
          sibling = sibling.nextSibling;
        }
      }
    }
    currentNode = walker.nextNode();
  }

  return regionMap;
}

// <!-- canvas-start-4737d23d-fa9a-4670-9807-ebf61e049076 -->
/**
 * Returns an array of all HTMLElements that are in between the canvas-start-{uuid} and canvas-end-{uuid} HTML comments.
 * @param id
 * @param document
 */
export function getElementsByIdInHTMLComment(
  id: string,
  document: Document,
): HTMLElement[] {
  const startMarker = `canvas-start-${id}`;
  const endMarker = `canvas-end-${id}`;
  const iterator = document.createNodeIterator(
    document,
    NodeFilter.SHOW_COMMENT,
    null,
  );
  const comments = [];
  let currentNode;

  // Collect all comment nodes
  while ((currentNode = iterator.nextNode())) {
    comments.push(currentNode);
  }

  let startIndex = -1;
  let endIndex = -1;

  // Find the start and end comment indices
  comments.forEach((comment, index) => {
    if (comment.nodeValue?.trim() === startMarker) {
      startIndex = index;
    }
    if (comment.nodeValue?.trim() === endMarker) {
      endIndex = index;
    }
  });

  // If both start and end comments are found and in the correct order
  if (startIndex !== -1 && endIndex !== -1 && startIndex < endIndex) {
    const startComment = comments[startIndex];
    const endComment = comments[endIndex];
    const elements = [];

    // Collect elements between the start and end comments
    let currentNode = startComment.nextSibling;
    while (currentNode && currentNode !== endComment) {
      if (currentNode.nodeType === Node.ELEMENT_NODE) {
        elements.push(currentNode as HTMLElement);
      }
      currentNode = currentNode.nextSibling;
    }

    return elements;
  } else {
    // If the comments are not found or not in the correct order, return an empty array
    return [];
  }
}

/**
 * Finds all the canvas-slot-start-{any} HTML comments in the whole document and returns an array containing their immediate parent HTMLElements.
 * @param document
 */
export function getSlotParentsByHTMLComments(
  document: Document,
): HTMLElement[] {
  const slotParents: HTMLElement[] = [];
  const walker = document.createTreeWalker(document, NodeFilter.SHOW_COMMENT, {
    acceptNode(node) {
      const commentPattern = /^\s*canvas-slot-start-/;
      return commentPattern.test(node.nodeValue || '')
        ? NodeFilter.FILTER_ACCEPT
        : NodeFilter.FILTER_REJECT;
    },
  });
  let currentNode = walker.nextNode();

  // Each time an canvas-slot-start comment is found, add the parent HTMLElement to the array
  while (currentNode) {
    if (currentNode.parentElement) {
      slotParents.push(currentNode.parentElement);
    }
    currentNode = walker.nextNode();
  }

  return slotParents;
}

/**
 * Find a given slot by ID using the HTML comment annotations - it will return the immediate parent HTMLElement that
 * contains the <!-- canvas-slot-start-{slotId} --> comment.
 * @param slotId
 * @param document
 */
export function getSlotParentElementByIdInHTMLComment(
  slotId: string,
  document: Document,
): HTMLElement | null {
  // regular expression pattern to match comments with the given slot ID
  const commentPattern = new RegExp(`^\\s*canvas-slot-start-${slotId}\\b`);
  const walker = document.createTreeWalker(document, NodeFilter.SHOW_COMMENT, {
    acceptNode(node) {
      return commentPattern.test(node.nodeValue || '')
        ? NodeFilter.FILTER_ACCEPT
        : NodeFilter.FILTER_REJECT;
    },
  });
  let currentNode = walker.nextNode();

  // Traverse the nodes to find the first matching comment
  while (currentNode) {
    if (currentNode.parentElement) {
      return currentNode.parentElement;
    }
    currentNode = walker.nextNode();
  }

  // Return null if no matching comment is found
  return null;
}

export function findInChanges(
  changeList: PendingChanges,
  entityId: string | undefined,
  entityType: string | undefined,
) {
  if (!entityId || !entityType || !changeList) {
    return false;
  }
  for (const key in changeList) {
    if (Object.prototype.hasOwnProperty.call(changeList, key)) {
      const obj = changeList[key];
      if (obj.entity_id === entityId && obj.entity_type === entityType) {
        return true;
      }
    }
  }
  return false;
}

function getStackingDirection(container: HTMLElement): StackDirection {
  let style = getComputedStyle(container);
  if (style.display === 'contents' && container.parentElement) {
    style = getComputedStyle(container.parentElement);
  }
  const display = style.display;

  if (display.includes('flex')) {
    const flexDirection = style.flexDirection;
    if (flexDirection === 'row' || flexDirection === 'row-reverse') {
      return 'horizontal-flex';
    } else if (
      flexDirection === 'column' ||
      flexDirection === 'column-reverse'
    ) {
      return 'vertical-flex';
    }
  } else if (display.includes('grid')) {
    const gridTemplateColumns = style.gridTemplateColumns
      .split(' ')
      .filter((val) => val !== '0px' && val !== 'auto');
    const gridTemplateRows = style.gridTemplateRows
      .split(' ')
      .filter((val) => val !== '0px' && val !== 'auto');

    // If there is only one column defined, treat it as vertical stacking.
    if (gridTemplateColumns.length === 1) {
      return 'vertical-grid';
    } else if (gridTemplateColumns.length > 1) {
      return 'horizontal-grid';
    }

    // If there are multiple rows, consider it vertical stacking.
    if (gridTemplateRows.length > 1) {
      return 'vertical-grid';
    }

    const gridAutoFlow = style.gridAutoFlow;
    if (gridAutoFlow.includes('row')) {
      return 'horizontal-grid';
    } else if (gridAutoFlow.includes('column')) {
      return 'vertical-grid';
    }
  }

  // Default assumption based on common practices
  return 'vertical';
}
