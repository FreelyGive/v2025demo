import { localDefaultValue } from './default-date.js';

export const edit = (cy) => {
  cy.findByRole('group', { name: 'Canvas DateTime (Datelist)' }).as(
    'dateDateList',
  );
  cy.get('@dateDateList').findByLabelText('Year').as('dateYear');
  cy.get('@dateDateList').findByLabelText('Month').as('dateMonth');
  cy.get('@dateDateList').findByLabelText('Day').as('dateDay');
  cy.get('@dateDateList').findByLabelText('Hour').as('dateHour');
  cy.get('@dateDateList').findByLabelText('Minute').as('dateMinute');
  const defaultValues = {
    dateYear: 2025,
    dateMonth: localDefaultValue.format('M'),
    dateDay: localDefaultValue.format('D'),
    dateHour: localDefaultValue.format('H'),
    dateMinute: localDefaultValue.format('m'),
  };
  Object.entries(defaultValues).forEach(([key, value]) => {
    cy.get(`@${key}`).should('have.value', value);
  });
  // Check we can select the empty value without raising a 500 error.
  cy.get('@dateMonth').select('Month', { force: true });
  // This date is after daylight savings time has finished in the
  // timezone core uses for tests (Australia/Sydney). This is by design
  // as we want to assert that the saved value reflects the new offset
  // of UTC+10.
  // @see bootstrap.php
  const newValues = {
    dateYear: 2026,
    dateMonth: 5,
    dateDay: 2,
    dateHour: 5,
    dateMinute: 15,
  };
  Object.entries(newValues).forEach(([key, value]) => {
    // Radix renders these as a hidden element with a button to trigger, so
    // we have to use force.
    cy.get(`@${key}`).select(String(value), { force: true });
    cy.get(`@${key}`).should('have.value', String(value));
  });
};
export const assertData = (response) => {
  expect(response.attributes.field_cvt_datetime_datelist).to.equal(
    '2026-05-02T05:15:00+10:00',
  );
};
