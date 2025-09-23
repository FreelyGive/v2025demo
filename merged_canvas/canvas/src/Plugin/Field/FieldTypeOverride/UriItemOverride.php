<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Field\FieldTypeOverride;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\UriItem;

/**
 * @todo Fix upstream.
 */
class UriItemOverride extends UriItem {

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $values = parent::generateSampleValue($field_definition);
    // @todo The `uri` field type's sample value violates the JSON schema validation for `format=uri` due to an upstream bug: https://github.com/jsonrainbow/json-schema/issues/685
    // So until either https://bugs.php.net/bug.php?id=81332 or https://github.com/jsonrainbow/json-schema/issues/685 is fixed, be pragmatic: generate a random URI pointing to example.com, with the original random value used as the path.
    return [
      'value' => "http://example.com/" . parse_url($values['value'], PHP_URL_HOST),
    ];
  }

}
