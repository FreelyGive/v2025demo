import type {
  PropSource,
  StaticPropSource,
} from '@/features/layout/layoutModelSlice';
import type { PropsValues } from '@/types/Form';

export interface TransformOptions {
  [key: string]: any;
}

export type Transform = {
  [key in keyof Transforms]: TransformOptions;
};

export interface TransformConfig {
  [key: keyof PropsValues]: Partial<Transform>;
}

export const ENTITY_AUTOCOMPLETE_MATCH = /.+\s\(([^)]+)\)/;

type PropsValuesOrArrayOfPropsValues = Array<PropsValues> | PropsValues;

type Transformer<
  TransformerOptions extends any,
  TransformerReturn extends unknown = any,
  TransformerInput extends unknown = PropsValuesOrArrayOfPropsValues,
  FieldPropShape extends PropSource = StaticPropSource,
> = (
  value: TransformerInput,
  options: TransformerOptions,
  fieldPropShape: FieldPropShape,
) => TransformerReturn;

const isList = (
  props: PropsValues | Array<PropsValues>,
  list: boolean,
): props is Array<PropsValues> => list;

const mainProperty: Transformer<{
  name?: keyof PropsValues;
  list?: boolean;
}> = (value, options): any => {
  if (value === null) {
    return null;
  }
  const { name = 'value', list = true } = options;
  let first = value as PropsValues;
  if (isList(value, list)) {
    if (value.length === 0) {
      return null;
    }
    first = value.shift() as PropsValues;
  }
  if (first && name in first) {
    return first[name];
  }
  return null;
};

const firstRecord: Transformer<void, null | PropsValues> = (value) => {
  if (value === null || value.length === 0) {
    return null;
  }
  return value.pop() as PropsValues;
};

interface LinkPropShape extends StaticPropSource {
  sourceTypeSettings: {
    instance: {
      // @see DRUPAL_DISABLED
      // @see DRUPAL_OPTIONAL
      // @see DRUPAL_REQUIRED
      title: 0 | 1 | 2;
    };
  };
}

const link: Transformer<
  void,
  null | string | PropsValues,
  PropsValuesOrArrayOfPropsValues,
  LinkPropShape
> = (value, options, propSource) => {
  // `1` corresponds to `DRUPAL_OPTIONAL` and `2` to DRUPAL_REQUIRED on the
  // server side.
  if (![1, 2].includes(propSource?.sourceTypeSettings?.instance?.title)) {
    const uri = mainProperty(value, { name: 'uri' }, propSource);
    const match = uri.match(ENTITY_AUTOCOMPLETE_MATCH);
    if (match !== null) {
      // LinkWidget with autocomplete support only supports matching on node
      // entities.
      // @todo Add support for other entity types once core does -
      // https://www.drupal.org/i/2423093
      return `entity:node/${match[1]}`;
    }
    return uri;
  }
  const record = firstRecord(value, undefined, propSource);
  if (record === null) {
    return record;
  }
  const match = record.uri.match(ENTITY_AUTOCOMPLETE_MATCH);
  if (match !== null) {
    // LinkWidget with autocomplete support only supports matching on node
    // entities.
    // @todo Add support for other entity types once core does -
    // https://www.drupal.org/i/2423093
    return { ...record, uri: `entity:node/${match[1]}` };
  }
  return record;
};

const cast: Transformer<
  { to: 'number' | 'boolean' | 'integer' },
  null | number | boolean,
  null | string
> = (value, options) => {
  const { to = 'number' } = options;
  if (value === null) {
    return null;
  }
  if (to === 'number') {
    return Number(value);
  }
  if (to === 'integer') {
    return parseInt(value);
  }
  if (value === 'false') {
    return false;
  }
  return Boolean(value);
};

interface DateFieldPropSource extends StaticPropSource {
  sourceTypeSettings: {
    storage: {
      // @see \Drupal\datetime\Plugin\Field\FieldType\DateTimeItem::DATETIME_TYPE_DATE
      // @see \Drupal\datetime\Plugin\Field\FieldType\DateTimeItem::DATETIME_TYPE_DATETIME
      datetime_type: 'date' | 'datetime';
    };
  };
}

const dateTime: Transformer<
  { type: 'date' | 'datetime' },
  null | string,
  PropsValuesOrArrayOfPropsValues,
  DateFieldPropSource
> = (value, options, propSource) => {
  const type = propSource.sourceTypeSettings.storage.datetime_type;
  // @see \Drupal\Component\Datetime\DateTimePlus::setDefaultDateTime
  let timeString = '12:00:00';
  if (!('date' in value)) {
    return null;
  }
  const dateString = value.date;
  if (type === 'date') {
    return dateString;
  }
  if ('time' in value) {
    timeString = value.time;
  }
  // @todo Update this in https://www.drupal.org/project/canvas/issues/3501281, which will allow removing the FE-special casing in \Drupal\canvas\PropExpressions\StructuredData\Evaluator::evaluate()
  return new Date(`${dateString} ${timeString}+0000`).toISOString();
};

const mediaSelection: Transformer<void, null | PropsValues> = (value) => {
  if ('selection' in value) {
    return value.selection;
  }
  return null;
};

const transforms = {
  mainProperty,
  firstRecord,
  dateTime,
  mediaSelection,
  cast,
  link,
};

export type Transforms = typeof transforms;

export default transforms;
