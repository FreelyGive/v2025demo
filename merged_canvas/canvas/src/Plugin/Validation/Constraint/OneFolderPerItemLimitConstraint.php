<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\Validator\Constraint;

/**
 * Checks that the Folder Item isn't assigned to a different Folder already.
 *
 * @internal
 */
#[\Drupal\Core\Validation\Attribute\Constraint(
  id: 'OneFolderPerItemLimitConstraint',
  label: new TranslatableMarkup('Item can only be assigned to one Folder.', [], ['context' => 'Validation']),
  type: ['string']
)]
final class OneFolderPerItemLimitConstraint extends Constraint {
  public string $configEntityTypeId;
  public string $id;
  public string $limitViolated = 'Folder item %item_id is already assigned to folder %folder_name';

  /**
   * {@inheritdoc}
   */
  public function getRequiredOptions(): array {
    return ['id', 'configEntityTypeId'];
  }

}
