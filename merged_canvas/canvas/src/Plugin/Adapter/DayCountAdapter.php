<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Adapter;

use Drupal\Core\StringTranslation\TranslatableMarkup;

#[Adapter(
  id: 'day_count',
  label: new TranslatableMarkup('Count days'),
  inputs: [
    'oldest' => ['type' => 'string', 'format' => 'date'],
    'newest' => ['type' => 'string', 'format' => 'date'],
  ],
  requiredInputs: ['oldest'],
  output: ['type' => 'integer'],
)]
final class DayCountAdapter extends AdapterBase {

  protected string $oldest;
  protected ?string $newest = NULL;

  public function adapt(): mixed {
    $utc = new \DateTimeZone("UTC");
    $oldest = \DateTime::createFromFormat('Y-m-d', $this->oldest, $utc);
    $newest = $this->newest
      ? \DateTime::createFromFormat('Y-m-d', $this->newest, $utc)
      : new \DateTimeImmutable("now", $utc);
    // Note: $oldest and $newest are already guaranteed to be valid, so this
    // assertion exists only to satisfy PHPStan.
    assert($oldest !== FALSE && $newest !== FALSE);
    return $newest->diff($oldest)->days;
  }

}
