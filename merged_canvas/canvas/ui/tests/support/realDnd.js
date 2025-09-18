// Drag and drop functionality for e2e Cypress tests using cypress-real-events.
// @see https://github.com/dmtrKovalenko/cypress-real-events/pull/17
import { fireCdpCommand } from 'cypress-real-events/fireCdpCommand.js';
import { getCypressElementCoordinates } from 'cypress-real-events/getCypressElementCoordinates.js';

function isJQuery(obj) {
  return Boolean(obj.jquery);
}

export async function realDnd(subject, destination, options = {}) {
  if (!destination) {
    throw new Error(
      'destination is required when using cy.realDnd(destination)',
    );
  }

  const startCoords = getCypressElementCoordinates(subject, options.position);
  const endCoords = isJQuery(destination)
    ? getCypressElementCoordinates(
        destination,
        options.position,
        options.scrollBehavior,
      )
    : destination;

  const log = Cypress.log({
    $el: subject,
    name: 'realClick',
    consoleProps: () => ({
      Dragged: subject.get(0),
      From: startCoords,
      End: endCoords,
    }),
  });
  await new Promise((resolve) =>
    setTimeout(resolve, options?.preClickWait || 200),
  );

  log.snapshot('before');
  await fireCdpCommand('Input.dispatchMouseEvent', {
    type: 'mousePressed',
    ...startCoords,
    clickCount: 1,
    buttons: 1,
    pointerType: options.pointer ?? 'mouse',
    button: 'left',
  });
  await new Promise((resolve) =>
    setTimeout(resolve, options?.preMoveWait || 200),
  );

  console.log(endCoords);
  await fireCdpCommand('Input.dispatchMouseEvent', {
    ...endCoords,
    type: 'mouseMoved',
    button: 'left',
    pointerType: options.pointer ?? 'mouse',
  });

  await new Promise((resolve) =>
    setTimeout(resolve, options?.preReleaseWait || 200),
  );

  await fireCdpCommand('Input.dispatchMouseEvent', {
    type: 'mouseReleased',
    ...endCoords,
    clickCount: 1,
    buttons: 1,
    pointerType: options.pointer ?? 'mouse',
    button: 'left',
  });
  await new Promise((resolve) => setTimeout(resolve, 200));

  log.snapshot('after').end();

  return subject;
}
