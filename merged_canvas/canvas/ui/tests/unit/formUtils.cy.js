import { formStateToObject, getPropsValues } from '@/components/form/formUtil';

let formState = {
  'canvas_component_props[all-props][heading][0][value]': 'hello, world!',
  'canvas_component_props[all-props][subheading][0][value]': '',
  'canvas_component_props[all-props][cta1][0][value]': '',
  'canvas_component_props[all-props][cta1href][0][uri]': 'https://drupal.org',
  'canvas_component_props[all-props][cta1href][0][title]': 'Do it',
  'canvas_component_props[all-props][cta2][0][value]': '',
  'canvas_component_props[all-props][a_boolean][value]': true,
  'canvas_component_props[all-props][options_select]': 'fine thx',
  'canvas_component_props[all-props][unchecked_boolean][value]': false,
  'canvas_component_props[all-props][date][0][value][date]': '2025-02-02',
  'canvas_component_props[all-props][datetime][0][value][date]': '2025-01-31',
  'canvas_component_props[all-props][datetime][0][value][time]': '20:30:33',
  'canvas_component_props[all-props][email][0][value]': 'bob@example.com',
  'canvas_component_props[all-props][number][0][value]': 123,
  'canvas_component_props[all-props][float][0][value]': 123.45,
  'canvas_component_props[all-props][textarea][0][value]': `Hi there
Multiline
Value`,
  'canvas_component_props[all-props][linkNoTitle][0][uri]':
    'http://example.com',
  'canvas_component_props[all-props][linkNoTitleEmpty][0][uri]': '',
  'canvas_component_props[all-props][media][selection][0][target_id]': 3,
  form_build_id: 'this-is-a-form-build-id',
  form_token: 'this-is-a-form-token',
  form_id: 'component_instance_form',
};
let inputAndUiData = {
  selectedComponent: 'all-props',
  selectedComponentType: 'sdc.sdc_test_all_props.all-props',
  layout: [],
  model: {
    'all-props': {
      // Minimal source representation.
      source: {
        a_boolean: {},
        unchecked_boolean: {},
        number: {},
        float: {},
        datetime: {
          sourceTypeSettings: {
            instance: {},
            storage: {
              datetime_type: 'datetime',
            },
          },
        },
        date: {
          sourceTypeSettings: {
            instance: {},
            storage: {
              datetime_type: 'date',
            },
          },
        },
        cta1href: {
          sourceTypeSettings: {
            instance: {
              // Simulate a title.
              title: 1,
            },
            storage: {},
          },
        },
        linkNoTitle: {
          sourceTypeSettings: {
            instance: {
              title: 0,
            },
            storage: {},
          },
        },
        linkNoTitleEmpty: {
          sourceTypeSettings: {
            instance: {
              title: 0,
            },
            storage: {},
          },
        },
      },
    },
  },
  components: {
    'sdc.sdc_test_all_props.all-props': {
      propSources: {
        a_boolean: {
          jsonSchema: {
            type: 'boolean',
          },
        },
        unchecked_boolean: {
          jsonSchema: {
            type: 'boolean',
          },
        },
        number: {
          jsonSchema: {
            type: 'integer',
          },
        },
        float: {
          jsonSchema: {
            type: 'number',
          },
        },
        datetime: {
          sourceTypeSettings: {
            instance: {},
            storage: {
              datetime_type: 'datetime',
            },
          },
        },
        date: {
          sourceTypeSettings: {
            instance: {},
            storage: {
              datetime_type: 'date',
            },
          },
        },
        cta1href: {
          sourceTypeSettings: {
            instance: {
              // Simulate a title.
              title: 1,
            },
            storage: {},
          },
        },
        linkNoTitle: {
          sourceTypeSettings: {
            instance: {
              title: 0,
            },
            storage: {},
          },
        },
        linkNoTitleEmpty: {
          sourceTypeSettings: {
            instance: {
              title: 0,
            },
            storage: {},
          },
        },
      },
    },
  },
};
// This metadata is defined in PHP and is duplicated here to improve testability.
// ⚠️ This should be kept in sync! ⚠️
// @see \Drupal\canvas\Hook\ReduxIntegratedFieldWidgetsHooks::fieldWidgetInfoAlter()
// @see \Drupal\canvas\Hook\ReduxIntegratedFieldWidgetsHooks::mediaLibraryFieldWidgetInfoAlter()
const transformConfig = {
  heading: { mainProperty: {} },
  subheading: { mainProperty: {} },
  cta1: { mainProperty: {} },
  cta1href: { link: {} },
  linkNoTitle: { link: {} },
  linkNoTitleEmpty: { link: {} },
  cta2: { mainProperty: {} },
  textarea: { mainProperty: {} },
  number: { mainProperty: {} },
  float: { mainProperty: {} },
  email: { mainProperty: {} },
  a_boolean: {
    mainProperty: { list: false },
  },
  unchecked_boolean: {
    mainProperty: { list: false },
  },
  datetime: {
    mainProperty: {},
    dateTime: {},
  },
  date: {
    mainProperty: {},
    dateTime: {},
  },
  media: {
    mediaSelection: {},
    mainProperty: { name: 'target_id' },
  },
};

describe('Form state to object', () => {
  it('Should transform flat structure into a nested object', () => {
    const asObject = formStateToObject(formState, 'all-props');
    expect(asObject).to.deep.equal({
      heading: [{ value: 'hello, world!' }],
      subheading: [{ value: '' }],
      cta1: [{ value: '' }],
      cta1href: [{ uri: 'https://drupal.org', title: 'Do it' }],
      cta2: [{ value: '' }],
      linkNoTitle: [{ uri: 'http://example.com' }],
      linkNoTitleEmpty: [{ uri: '' }],
      a_boolean: { value: 'true' },
      unchecked_boolean: { value: 'false' },
      date: [
        {
          value: {
            date: '2025-02-02',
          },
        },
      ],
      datetime: [
        {
          value: {
            date: '2025-01-31',
            time: '20:30:33',
          },
        },
      ],
      options_select: 'fine thx',
      email: [{ value: 'bob@example.com' }],
      number: [{ value: '123' }],
      float: [{ value: '123.45' }],
      textarea: [
        {
          value: `Hi there
Multiline
Value`,
        },
      ],
      media: {
        selection: [{ target_id: '3' }],
      },
    });
  });
});

describe('Get prop values from form state', () => {
  it('Should transform values from form state', () => {
    const { propsValues } = getPropsValues(
      formState,
      inputAndUiData,
      transformConfig,
    );
    expect(propsValues).to.deep.equal({
      a_boolean: true,
      unchecked_boolean: false,
      heading: 'hello, world!',
      subheading: '',
      cta1: '',
      cta2: '',
      cta1href: { uri: 'https://drupal.org', title: 'Do it' },
      linkNoTitle: 'http://example.com',
      linkNoTitleEmpty: '',
      textarea: `Hi there
Multiline
Value`,
      email: 'bob@example.com',
      number: 123,
      float: 123.45,
      options_select: 'fine thx',
      date: '2025-02-02',
      datetime: '2025-01-31T20:30:33.000Z',
      media: '3',
    });
  });
});
