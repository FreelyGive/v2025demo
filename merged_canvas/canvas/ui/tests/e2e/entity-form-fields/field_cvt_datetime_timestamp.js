import { localDefaultValue } from './default-date.js';

export const edit = (cy) => {
  cy.findByRole('group', { name: 'Canvas DateTime (Timestamp)' }).as(
    'dateTimestamp',
  );
  cy.get('@dateTimestamp').findByLabelText('Date').as('timestampDate');
  cy.get('@dateTimestamp').findByLabelText('Time').as('timestampTime');
  const defaultValues = {
    timestampDate: localDefaultValue.format('YYYY-MM-DD'),
    timestampTime: localDefaultValue.format('HH:mm:ss'),
  };
  Object.entries(defaultValues).forEach(([key, value]) => {
    cy.get(`@${key}`).should('have.value', value);
  });
  // This date is after daylight savings time has finished in the
  // timezone core uses for tests (Australia/Sydney). This is by design
  // as we want to assert that the saved value reflects the new offset
  // of UTC+10.
  // @see bootstrap.php
  const newValues = {
    timestampDate: '2026-05-02',
    timestampTime: '05:15:00',
  };
  Object.entries(newValues).forEach(([key, value]) => {
    cy.get(`@${key}`).clear();
    cy.get(`@${key}`).type(value);
  });
};
export const assertData = (response) => {
  expect(response.attributes.field_cvt_datetime_timestamp).to.equal(
    '2026-05-02T05:15:00+10:00',
  );
};
