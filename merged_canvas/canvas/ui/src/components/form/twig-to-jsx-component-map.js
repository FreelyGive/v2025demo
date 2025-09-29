import CanvasBox from '@/components/form/canvas-components/CanvasBox';
import CanvasText from '@/components/form/canvas-components/CanvasText';
import {
  DrupalContainerTextFormatFilterGuidelines,
  DrupalContainerTextFormatFilterHelp,
} from '@/components/form/components/drupal/DrupalContainerTextFormat';
import DrupalDetails from '@/components/form/components/drupal/DrupalDetails';
import DrupalForm from '@/components/form/components/drupal/DrupalForm';
import DrupalFormElement from '@/components/form/components/drupal/DrupalFormElement';
import DrupalFormElementLabel from '@/components/form/components/drupal/DrupalFormElementLabel';
import DrupalInput from '@/components/form/components/drupal/DrupalInput';
import DrupalPathWidget from '@/components/form/components/drupal/DrupalPathWidget';
import { DrupalRadioGroup } from '@/components/form/components/drupal/DrupalRadio';
import DrupalSelect from '@/components/form/components/drupal/DrupalSelect';
import DrupalTextArea from '@/components/form/components/drupal/DrupalTextArea';
import DrupalToggle from '@/components/form/components/drupal/DrupalToggle';
import DrupalVerticalTabs from '@/components/form/components/drupal/DrupalVerticalTabs';
import LinkedFieldBox from '@/components/form/components/drupal/LinkedFieldBox.js';
import PropLinker from '@/components/form/components/drupal/PropLinker.js';
import DrupalMediaLibraryWidgetContainer from '@/components/form/components/MediaLibraryWidgetContainer';

// This is where we map the Drupal Twig templates to the corresponding JSX component.
// @see \Drupal\canvas\Hook\SemiCoupledThemeEngineHooks::themeSuggestionsAlter()
// @see docs/semi-coupled-theme-engine.md
// @see themes/engines/semi_coupled/README.md
// @see themes/canvas_stark/templates/process_as_jsx/

const twigToJSXComponentMap = {
  'drupal-container--text-format-filter-guidelines':
    DrupalContainerTextFormatFilterGuidelines,
  'drupal-container--text-format-filter-help':
    DrupalContainerTextFormatFilterHelp,
  'drupal-details': DrupalDetails,
  'drupal-form': DrupalForm,
  'drupal-form-element': DrupalFormElement,
  'drupal-form-element-label': DrupalFormElementLabel,
  'drupal-input': DrupalInput,
  'drupal-input--checkbox--inwidget-boolean-checkbox': DrupalToggle,
  'drupal-input--url': DrupalInput,
  'drupal-input--textfield--inwidget-path': DrupalPathWidget,
  'drupal-radios': DrupalRadioGroup,
  'drupal-select': DrupalSelect,
  'drupal-textarea': DrupalTextArea,
  'drupal-vertical-tabs': DrupalVerticalTabs,
  'drupal-container--media-library-widget': DrupalMediaLibraryWidgetContainer,
  'canvas-text': CanvasText,
  'canvas-box': CanvasBox,
  'canvas-drupal-label': DrupalFormElementLabel,
  'drupal-linked-field-box': LinkedFieldBox,
  'drupal-prop-linker': PropLinker,
};

export default twigToJSXComponentMap;
