<?php

namespace Drupal\canvas\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\canvas\Entity\ComponentTreeEntityInterface;
use Drupal\canvas\Storage\ComponentTreeLoader;

/**
 * Checks access for editing an entity's component tree.
 *
 * @internal
 */
final class ComponentTreeEditAccessCheck implements AccessInterface {

  public function __construct(private readonly ComponentTreeLoader $componentTreeLoader) {}

  /**
   * Checks access for editing an entity's component tree.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   An entity containing a component tree.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account being checked.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(EntityInterface $entity, AccountInterface $account): AccessResultInterface {
    if ($entity instanceof FieldableEntityInterface || $entity instanceof ComponentTreeEntityInterface) {
      $tree = $this->componentTreeLoader->load($entity);
      // TRICKY: field access hooks must return AccessResult::forbidden() to
      // override the default field access. Then the forbidden field access's
      // reason would overwrite that of non-allowed entity access. Avoid that by
      // explicitly checking entity access and returning early.
      // @see \Drupal\Core\Field\FieldItemList::defaultAccess()
      $entity_access = $entity->access('update', $account, TRUE);
      if (!$entity_access->isAllowed()) {
        return $entity_access;
      }

      // If the component tree is a field on the entity, also check field access.
      if ($entity instanceof FieldableEntityInterface) {
        assert(
          // Every fieldable entity's component tree field has the edited entity
          // as its parent.
          // @phpstan-ignore-next-line method.notFound
          $tree->getParent()->getEntity() === $entity
          // TRICKY: when the component tree field itself is not translatable
          // but the containing entity is, the $entity object will not match the
          // field's parent entity object due to how Drupal loads untranslatable
          // fields: it always does so using the default entity translation. So
          // verify using config dependency names that both objects truly do
          // refer to the same content entity.
          // @see \Drupal\Core\Language\LanguageInterface::LANGCODE_DEFAULT
          // @see \Drupal\Core\Entity\ContentEntityBase::getTranslatedField()
          // @see \Drupal\Tests\canvas\Functional\TranslationTest
          || (
            // @phpstan-ignore-next-line method.notFound
            $entity->isDefaultTranslation() === FALSE
            && $tree->getFieldDefinition()->isTranslatable() === FALSE
            && $tree->getLangcode() !== $entity->language()->getId()
            // @phpstan-ignore-next-line method.nonObject
            && $tree->getParent()->getEntity()->getConfigDependencyName() === $entity->getConfigDependencyName()
          )
        );
        return $entity_access->andIf($tree->access('edit', $account, TRUE));
      }

      // Every non-fieldable entity containing a component tree either has a
      // dangling component tree with either:
      // - never a parent (`PageRegion`, `Pattern`)
      // - sometimes a parent, but of a different entity (`ContentTemplate`)
      // @see \Drupal\canvas\Entity\ContentTemplate::getComponentTree()
      // @phpstan-ignore-next-line method.notFound
      assert($tree->getParent() === NULL || $tree->getParent()->getEntity() !== $entity);

      return $entity_access;
    }
    // No opinion.
    return AccessResult::neutral();
  }

}
