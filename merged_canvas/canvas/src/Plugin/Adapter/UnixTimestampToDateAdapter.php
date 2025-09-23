<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Adapter;

use Drupal\Core\StringTranslation\TranslatableMarkup;

#[Adapter(
  id: 'unix_to_date',
  label: new TranslatableMarkup('UNIX timestamp to date'),
  inputs: [
    'unix' => ['type' => 'integer'],
  ],
  requiredInputs: ['unix'],
  output: ['type' => 'string', 'format' => 'date'],
)]
final class UnixTimestampToDateAdapter extends AdapterBase {

  protected string $unix;

  public function adapt(): mixed {
    // @todo Ensure that the `unix` input is constrained to the appropriate range.
    $datetime = \DateTime::createFromFormat('U', $this->unix);
    assert($datetime !== FALSE);
    return $datetime->format('Y-m-d');
  }

}
