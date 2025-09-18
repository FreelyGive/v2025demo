<?php

declare(strict_types=1);

namespace Drupal\canvas\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\Validator\Constraint;

/**
 * Checks that the name is a unique string per Folder type.
 *
 * @internal
 */
#[\Drupal\Core\Validation\Attribute\Constraint(
  id: 'UniqueNamePerFolderTypeConstraint',
  label: new TranslatableMarkup('Unique name per Folder config entity type', [], ['context' => 'Validation']),
  type: ['string']
)]
final class UniqueNamePerFolderTypeConstraint extends Constraint {

  public string $configEntityTypeId;
  public string $id;

  public string $notUnique = 'Name %value is not unique in Folder type "%configEntityTypeId"';

  /**
   * {@inheritdoc}
   */
  public function getRequiredOptions(): array {
    return ['id', 'configEntityTypeId'];
  }

}
