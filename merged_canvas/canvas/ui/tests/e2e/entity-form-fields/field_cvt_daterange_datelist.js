import {
  defaultValue,
  localDefaultEndValue,
  localDefaultValue,
} from './default-date.js';

export const edit = (cy) => {
  // Confirm we have the correct timezone in the test.
  expect(defaultValue.toISOString()).to.equal('2025-04-01T04:15:00.000Z');
  cy.findByRole('group', { name: 'Canvas Date Range (Datelist)' }).as(
    'dateRangeDateList',
  );
  cy.get('@dateRangeDateList')
    .findAllByLabelText('Year')
    .eq(0)
    .as('startDateYear');
  cy.get('@dateRangeDateList')
    .findAllByLabelText('Year')
    .eq(1)
    .as('endDateYear');
  cy.get('@dateRangeDateList')
    .findAllByLabelText('Month')
    .eq(0)
    .as('startDateMonth');
  cy.get('@dateRangeDateList')
    .findAllByLabelText('Month')
    .eq(1)
    .as('endDateMonth');
  cy.get('@dateRangeDateList')
    .findAllByLabelText('Day')
    .eq(0)
    .as('startDateDay');
  cy.get('@dateRangeDateList').findAllByLabelText('Day').eq(1).as('endDateDay');
  cy.get('@dateRangeDateList')
    .findAllByLabelText('Hour')
    .eq(0)
    .as('startDateHour');
  cy.get('@dateRangeDateList')
    .findAllByLabelText('Hour')
    .eq(1)
    .as('endDateHour');
  cy.get('@dateRangeDateList')
    .findAllByLabelText('Minute')
    .eq(0)
    .as('startDateMinute');
  cy.get('@dateRangeDateList')
    .findAllByLabelText('Minute')
    .eq(1)
    .as('endDateMinute');
  const defaultValues = {
    startDateYear: 2025,
    startDateMonth: localDefaultValue.format('M'),
    startDateDay: localDefaultValue.format('D'),
    startDateHour: localDefaultValue.format('H'),
    startDateMinute: localDefaultValue.format('m'),
    endDateYear: 2025,
    endDateMonth: localDefaultEndValue.format('M'),
    endDateDay: localDefaultEndValue.format('D'),
    endDateHour: localDefaultEndValue.format('H'),
    endDateMinute: localDefaultEndValue.format('m'),
  };
  Object.entries(defaultValues).forEach(([key, value]) => {
    cy.get(`@${key}`).should('have.value', value);
  });
  // Check we can select the empty value without raising a 500 error.
  cy.get('@startDateMonth').select('Month', { force: true });
  // This date is after daylight savings time has finished in the
  // timezone core uses for tests (Australia/Sydney). This is by design
  // as we want to assert that the saved value reflects the new offset
  // of UTC+10.
  // @see bootstrap.php
  const newValues = {
    startDateYear: 2026,
    startDateMonth: 5,
    startDateDay: 2,
    startDateHour: 5,
    startDateMinute: 15,
    endDateYear: 2026,
    endDateMonth: 6,
    endDateDay: 2,
    endDateHour: 7,
    endDateMinute: 30,
  };
  Object.entries(newValues).forEach(([key, value]) => {
    cy.get(`@${key}`).select(String(value), { force: true });
    cy.get(`@${key}`).should('have.value', String(value));
  });
};
export const assertData = (response) => {
  expect(response.attributes.field_cvt_daterange_datelist.value).to.equal(
    '2026-05-02T05:15:00+10:00',
  );
  expect(response.attributes.field_cvt_daterange_datelist.end_value).to.equal(
    '2026-06-02T07:30:00+10:00',
  );
};
