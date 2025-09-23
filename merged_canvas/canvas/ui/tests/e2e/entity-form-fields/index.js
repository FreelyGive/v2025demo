import * as field_cvt_boolean_checkbox from './field_cvt_boolean_checkbox.js';
import * as field_cvt_comment from './field_cvt_comment.js';
import * as field_cvt_daterange_datelist from './field_cvt_daterange_datelist.js';
import * as field_cvt_daterange_default from './field_cvt_daterange_default.js';
import * as field_cvt_datetime_datelist from './field_cvt_datetime_datelist.js';
import * as field_cvt_datetime_timestamp from './field_cvt_datetime_timestamp.js';
import * as field_cvt_entity_autocomplete from './field_cvt_entity_autocomplete.js';
import * as field_cvt_entity_ref_tags from './field_cvt_entity_ref_tags.js';
import * as field_cvt_int from './field_cvt_integer.js';
import * as field_cvt_language from './field_cvt_language.js';
import * as field_cvt_moderation_state from './field_cvt_moderation_state.js';
import * as field_cvt_options_buttons from './field_cvt_options_buttons.js';
import * as field_cvt_telephone from './field_cvt_telephone.js';
import * as field_cvt_textarea_summary from './field_cvt_textarea_summary.js';
import * as field_cvt_textarea from './field_cvt_textarea.js';
import * as field_cvt_textfield_multi from './field_cvt_textfield_multi.js';
import * as field_cvt_textfield from './field_cvt_textfield.js';
import * as field_cvt_uri from './field_cvt_uri.js';

// Expand this to add additional coverage.
// For each field to be tested, add a new file that exports two methods as
// follows:
// - 'edit' - The edit method receives the current Cypress instance and
// should perform pre-condition checks (e.g. assert the default state), then
// make an edit to the field.
// - 'assertData' - The assertData method receives the JSON:API representation
// of the entity after the form has been submitted and the entity has been
// published. It should make use of expect to assert the value was correctly
// submitted.
// @see canvas_test_article_fields_install for where the fields are created.
export default {
  field_cvt_comment,
  field_cvt_moderation_state,
  field_cvt_language,
  field_cvt_options_buttons,
  field_cvt_telephone,
  field_cvt_textfield,
  field_cvt_textarea,
  field_cvt_uri,
  field_cvt_entity_autocomplete,
  field_cvt_daterange_default,
  field_cvt_textarea_summary,
  field_cvt_datetime_timestamp,
  field_cvt_daterange_datelist,
  field_cvt_datetime_datelist,
  field_cvt_entity_ref_tags,
  field_cvt_boolean_checkbox,
  field_cvt_textfield_multi,
  field_cvt_int,
};
