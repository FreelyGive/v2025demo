import { localDefaultEndValue, localDefaultValue } from './default-date.js';

export const edit = (cy) => {
  cy.findByRole('group', { name: 'Canvas Date Range (Default)' }).as(
    'dateRangeDefault',
  );
  cy.get('@dateRangeDefault').findAllByLabelText('Date').eq(0).as('startDate');
  cy.get('@dateRangeDefault').findAllByLabelText('Date').eq(1).as('endDate');
  cy.get('@dateRangeDefault').findAllByLabelText('Time').eq(0).as('startTime');
  cy.get('@dateRangeDefault').findAllByLabelText('Time').eq(1).as('endTime');
  const defaultValues = {
    startDate: localDefaultValue.format('YYYY-MM-DD'),
    startTime: localDefaultValue.format('HH:mm:ss'),
    endDate: localDefaultEndValue.format('YYYY-MM-DD'),
    endTime: localDefaultEndValue.format('HH:mm:ss'),
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
    startDate: '2026-05-02',
    startTime: '05:15:00',
    endDate: '2026-06-02',
    endTime: '07:30:00',
  };
  Object.entries(newValues).forEach(([key, value]) => {
    cy.get(`@${key}`).clear();
    cy.get(`@${key}`).type(value);
  });
};
export const assertData = (response) => {
  expect(response.attributes.field_cvt_daterange_default.value).to.equal(
    '2026-05-02T05:15:00+10:00',
  );
  expect(response.attributes.field_cvt_daterange_default.end_value).to.equal(
    '2026-06-02T07:30:00+10:00',
  );
};
