import { getDrupalSettings } from '@/utils/drupal-globals';

const drupalSettings = getDrupalSettings();

const addAjaxPageState = (query: string) => {
  // Drupal's AJAX API automatically adds ajaxPageState as a parameter, but
  // since these requests are not made via Drupal.Ajax, it is added explicitly.
  // This information is used to, among other things, determine which libraries
  // attached to the response have not yet been added to the page.
  const queryAsObject = new URLSearchParams(query);
  queryAsObject.set(
    'ajaxPageState',
    JSON.stringify(drupalSettings?.ajaxPageState || {}),
  );
  return queryAsObject.toString();
};

export default addAjaxPageState;
