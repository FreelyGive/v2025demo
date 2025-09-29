<?php

declare(strict_types=1);

namespace Drupal\canvas\Audit;

/**
 * @internal
 * @see \Drupal\canvas\Audit\ComponentAudit::getContentRevisionIdsUsingComponentIds()
 * @todo When Canvas adds Workspaces support (see https://www.drupal.org/i/3512616), refactor this enum to allow specifying what is being audited is "which workspace contains this?"
 */
enum RevisionAuditEnum: string {

  // Returns 0–N revisions.
  // @see \Drupal\Core\Entity\Query\QueryInterface::allRevisions()
  case All = 'all';

  // Returns 0-1 revisions.
  // @see \Drupal\Core\Entity\RevisionableInterface::isDefaultRevision()
  // @see \Drupal\Core\Entity\Query\QueryInterface::currentRevision()
  case Default = 'default';

  // Returns 0-1 revisions.
  // @see \Drupal\Core\Entity\Query\QueryInterface::latestRevision()
  case Latest = 'latest';

  // @see \Drupal\canvas\AutoSave\AutoSaveManager
  case AutoSave = 'canvas-auto-saves';

}
