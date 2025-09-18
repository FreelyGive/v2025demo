import dayjs from 'dayjs';
import customParseFormat from 'dayjs/plugin/customParseFormat.js';
import timezone from 'dayjs/plugin/timezone.js';
import utc from 'dayjs/plugin/utc.js';

dayjs.extend(customParseFormat);
dayjs.extend(utc);
dayjs.extend(timezone);

// The time chosen here is during daylight savings for the timezone
// that core uses for testing (Australia/Sydney). This is by design so
// that we can test oddities like daylight savings.
// @see \canvas_test_article_fields_install().
// @see bootstrap.php
const tz = 'Australia/Sydney';
export const defaultValue = dayjs()
  .utc()
  .year(2025)
  .date(1)
  .month(3)
  .hour(4)
  .minute(15)
  .millisecond(0)
  .second(0);
export const defaultEndValue = defaultValue.add(2, 'hours');

// When Cypress sets up the test environment, it makes use of core's test-site
// application, which loads the same bootstrap.php as PHPUnit. In this bootstrap
// file, the default timezone is set to Australia/Sydney. As this file is loaded
// before the test site is installed, it results in the default timezone for
// users in the test site being Australia/Sydney. As a result all date fields
// display the date in this timezone.
// @see core/scripts/test-site.php
// @see core/tests/bootstrap.php
export const localDefaultValue = defaultValue.tz(tz);
export const localDefaultEndValue = defaultEndValue.tz(tz);
