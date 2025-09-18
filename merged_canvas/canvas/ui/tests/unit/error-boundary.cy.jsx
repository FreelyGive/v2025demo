import { Suspense } from 'react';
import {
  Await,
  createBrowserRouter,
  json,
  RouterProvider,
} from 'react-router-dom';

import ErrorBoundary, {
  RouteAsyncErrorBoundary,
  RouteErrorBoundary,
} from '@/components/error/ErrorBoundary';

beforeEach(() => {
  cy.on('uncaught:exception', (err, runnable) => {
    // Uncaught exceptions cause Cypress to fail the test. Prevent this behavior.
    return false;
  });
});

const TroubleMaker = ({ shouldThrow }) => {
  if (shouldThrow) {
    throw new Error('Too many tabs, too little coffee');
  } else {
    return null;
  }
};

describe('ErrorBoundary handles errors', () => {
  it('displays an error only when an error is thrown', () => {
    // Mount the <TroubleMaker /> test component inside our <ErrorBoundary />.
    // No error is thrown yet, so we shouldn't see any error message.
    cy.mount(
      <ErrorBoundary>
        <TroubleMaker />
      </ErrorBoundary>,
    );
    cy.contains('too little coffee').should('not.exist');

    // Now mount again with an error thrown, and we should see the error message.
    cy.mount(
      <ErrorBoundary>
        <TroubleMaker shouldThrow={true} />
      </ErrorBoundary>,
    );
    cy.findByRole('alert')
      .invoke('text')
      .should('include', 'An unexpected error has occurred.')
      .should('include', 'Too many tabs, too little coffee')
      .should('include', 'Try again');
  });

  it('displays custom props', () => {
    cy.mount(
      <ErrorBoundary
        title="Unexpected decaf detected."
        resetButtonText="Brew again"
      >
        <TroubleMaker shouldThrow={true} />
      </ErrorBoundary>,
    );
    cy.findByRole('alert')
      .invoke('text')
      .should('include', 'Unexpected decaf detected.')
      .should('include', 'Too many tabs, too little coffee')
      .should('include', 'Brew again');
  });

  it('invokes callback to reset', () => {
    const reset = cy.stub({ reset: () => {} }, 'reset').as('reset');
    cy.mount(
      <ErrorBoundary onReset={reset}>
        <TroubleMaker shouldThrow={true} />
      </ErrorBoundary>,
    );
    cy.contains('Try again').click();
    cy.get('@reset').should('be.calledOnce');
  });

  it('displays the right variant', () => {
    cy.mount(
      <ErrorBoundary variant="page">
        <TroubleMaker shouldThrow={true} />
      </ErrorBoundary>,
    );
    cy.findByTestId('canvas-error-page')
      .should('exist')
      .invoke('text')
      .should('include', 'An unexpected error has occurred.')
      .should('include', 'Too many tabs, too little coffee')
      .should('include', 'Try again');

    cy.mount(
      <ErrorBoundary variant="card">
        <TroubleMaker shouldThrow={true} />
      </ErrorBoundary>,
    );
    cy.findByTestId('canvas-error-card')
      .should('exist')
      .invoke('text')
      .should('include', 'An unexpected error has occurred.')
      .should('include', 'Too many tabs, too little coffee')
      .should('include', 'Try again');

    cy.mount(
      <ErrorBoundary variant="alert">
        <TroubleMaker shouldThrow={true} />
      </ErrorBoundary>,
    );
    cy.findByTestId('canvas-error-alert')
      .should('exist')
      .invoke('text')
      .should('include', 'An unexpected error has occurred.')
      .should('include', 'Too many tabs, too little coffee')
      .should('include', 'Try again');
  });
});

describe('RouteErrorBoundary handles errors', () => {
  it('displays an error only when an error is thrown', () => {
    // Let React Router know that the path is '/'.
    window.history.pushState({}, null, '/');

    // Mount a simple browser router with the <TroubleMaker /> test component
    // in it, and with our <RouteErrorBoundary /> component as error element.
    cy.mount(
      <RouterProvider
        router={createBrowserRouter([
          {
            path: '',
            element: <TroubleMaker />,
            errorElement: <RouteErrorBoundary />,
          },
        ])}
      />,
    );
    // No error is thrown yet, so we shouldn't see any error message.
    cy.contains('too little coffee').should('not.exist');

    // Now mount again with an error thrown, and we should see the error message.
    cy.mount(
      <RouterProvider
        router={createBrowserRouter([
          {
            path: '',
            element: <TroubleMaker shouldThrow={true} />,
            errorElement: <RouteErrorBoundary />,
          },
        ])}
      />,
    );
    cy.findByRole('alert')
      .invoke('text')
      .should('include', 'An unexpected error has occurred in a route.')
      .should('include', 'Too many tabs, too little coffee')
      // Make sure there is no reset button. That is not supported in React
      // Router's error element.
      .should('not.include', 'Try again');
  });

  it('displays an error from a route error response', () => {
    // Let React Router know that the path is '/'.
    window.history.pushState({}, null, '/');

    cy.mount(
      <RouterProvider
        router={createBrowserRouter([
          {
            path: '',
            element: <></>,
            errorElement: <RouteErrorBoundary />,
            loader: () => {
              throw json(
                {},
                { status: 418, statusText: 'Unable to brew coffee' },
              );
            },
          },
        ])}
      />,
    );
    cy.findByRole('alert')
      .invoke('text')
      .should('include', 'An unexpected error has occurred in a route.')
      .should('include', '418 Unable to brew coffee');
  });
});

describe('RouteAsyncErrorBoundary handles errors', () => {
  it('displays an error when deferred data loading fails', () => {
    // Let React Router know that the path is '/'.
    window.history.pushState({}, null, '/');

    // Mount a simple browser router with React Router's <Await> inside where
    // we reject the promise it awaits.
    cy.mount(
      <RouterProvider
        router={createBrowserRouter([
          {
            path: '',
            element: (
              <Suspense>
                <Await
                  resolve={Promise.reject('Too many tabs, too little coffee')}
                  errorElement={<RouteAsyncErrorBoundary />}
                />
              </Suspense>
            ),
          },
        ])}
      />,
    );
    cy.findByRole('alert')
      .invoke('text')
      .should('include', 'An unexpected async error has occurred in a route.')
      .should('include', 'Too many tabs, too little coffee')
      // Make sure there is no reset button. That is not supported in React
      // Router's error element.
      .should('not.include', 'Try again');
  });
});
