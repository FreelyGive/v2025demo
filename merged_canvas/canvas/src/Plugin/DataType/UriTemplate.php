<?php

namespace Drupal\canvas\Plugin\DataType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\Core\TypedData\Plugin\DataType\StringData;

/**
 * The URI template data type.
 *
 * @see https://tools.ietf.org/html/rfc6570
 * @see \League\Uri\UriTemplate
 *
 * @internal
 */
#[DataType(
  id: self::PLUGIN_ID,
  label: new TranslatableMarkup("URI template")
)]
class UriTemplate extends StringData {

  public const string PLUGIN_ID = 'uri_template';

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    throw new \LogicException('@todo This should return a \League\Uri\UriTemplate. Exception for now to ensure nothing calls it to avoid BC breaks.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCastedValue() {
    return $this->value;
  }

}
