export const AJAX_UPDATE_FORM_STATE_EVENT = 'ajaxUpdateFormState';
export const AJAX_UPDATE_FORM_BUILD_ID_EVENT = 'ajaxUpdateFormBuildId';
export interface AjaxUpdateFormStateEvent {
  detail: {
    formId: string | null;
    updates: Record<string, string | null>;
  };
}
export interface AjaxUpdateFormBuildIdEvent {
  detail: {
    formId: string;
    oldFormBuildId: string;
    newFormBuildId: string;
  };
}
