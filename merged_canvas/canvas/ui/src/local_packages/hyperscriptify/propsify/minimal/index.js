import camelCase from 'just-camel-case';
import mapKeys from 'just-map-keys';
import mapValues from 'just-map-values';
import set from 'just-safe-set';

import factory from '../factory';

const propsify = factory({ camelCase, mapKeys, mapValues, set });
export default propsify;
