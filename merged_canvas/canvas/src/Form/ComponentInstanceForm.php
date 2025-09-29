<?php

declare(strict_types=1);

namespace Drupal\canvas\Form;

use Drupal\canvas\Entity\ContentTemplate;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\EventSubscriber\AjaxResponseSubscriber;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\canvas\Entity\Component;
use Drupal\canvas\Entity\ComponentInterface;
use Drupal\canvas\Storage\ComponentTreeLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Allows editing a component instance.
 *
 * @see \Drupal\canvas\ComponentSource\ComponentSourceInterface::buildComponentInstanceForm()
 */
final class ComponentInstanceForm extends FormBase {

  public const FORM_ID = 'component_instance_form';

  public function __construct(
    // These must be protected so that DependencySerializationTrait, which is
    // used by the parent class, can access it.
    protected ComponentTreeLoader $componentTreeLoader,
    protected ThemeHandlerInterface $themeHandler,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $component_tree_loader = $container->get(ComponentTreeLoader::class);
    assert($component_tree_loader instanceof ComponentTreeLoader);

    return new static(
      $component_tree_loader,
      $container->get('theme_handler'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return self::FORM_ID;
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\Core\Entity\Entity\EntityFormDisplay::buildForm()
   * @see \Drupal\Core\Field\WidgetBase::form()
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?EntityInterface $entity = NULL, ?FieldableEntityInterface $preview_entity = NULL): array {
    // ⚠️ This is HORRIBLY HACKY and will go away! ☺️
    // @see \Drupal\canvas\Controller\ApiLayoutController
    if (is_null($entity)) {
      throw new \UnexpectedValueException('The $entity parameter should never be NULL.');
    }
    \assert($entity instanceof FieldableEntityInterface || ($entity instanceof ContentTemplate && $preview_entity instanceof FieldableEntityInterface));
    // @phpstan-ignore-next-line property.notFound
    if (!$this->themeHandler->themeExists('canvas_stark') || !$this->themeHandler->listInfo()['canvas_stark']->status) {
      return [
        '#type' => 'markup',
        '#markup' => $this->t('The canvas_stark theme must be enabled for this form to work.'),
      ];
    }
    $host_entity = $entity instanceof ContentTemplate ? $preview_entity : $entity;
    $this->componentTreeLoader->load($entity);

    $request = $this->getRequest();
    $tree = $request->get('form_canvas_tree');
    [$component_id, $version] = \explode('@', \json_decode($tree, TRUE)['type']);
    if (empty($version)) {
      throw new \UnexpectedValueException('No component version specified.');
    }
    $component = Component::load($component_id);
    \assert($component instanceof ComponentInterface);
    // Load the version of the Component that was instantiated. This is what
    // allows older component instances to continue to use older/previous
    // component-source specific settings, such as the field type/widget for a
    // particular SDC or code component prop.
    $component->loadVersion($version);
    $component_instance_uuid = $request->get('form_canvas_selected');

    $props = $request->get('form_canvas_props');
    $client_model = json_decode($props, TRUE);

    // Make sure these get sent in subsequent AJAX requests.
    // Note: they're prefixed with `form_` to avoid storage in the UI state.
    // @see ui/src/components/form/inputBehaviors.tsx
    $form['form_canvas_selected'] = [
      '#type' => 'hidden',
      '#value' => $component_instance_uuid,
    ];
    $form['form_canvas_tree'] = [
      '#type' => 'hidden',
      '#value' => $tree,
    ];
    $form['form_canvas_props'] = [
      '#type' => 'hidden',
      '#value' => $props,
    ];

    // Prevent form submission while specifying values for component instance's
    // inputs, because changes are saved via Redux instead of a traditional
    // submit.
    // @see ui/src/components/form/inputBehaviors.tsx
    // @see https://developer.mozilla.org/en-US/docs/Web/HTML/Element/form#method
    $form['#method'] = 'dialog';

    $inputs = $component->getComponentSource()->clientModelToInput($component_instance_uuid, $component, $client_model, $host_entity);

    $form['#component'] = $component;
    $form['#attributes']['data-form-id'] = self::FORM_ID;

    $parents = ['canvas_component_props', $component_instance_uuid];
    $sub_form = ['#parents' => $parents, '#component' => $component, '#tree' => TRUE];
    $form['canvas_component_props'][$component_instance_uuid] = $component->getComponentSource()->buildComponentInstanceForm($sub_form, $form_state, $component, $component_instance_uuid, $inputs, $entity, $component->get('settings'));
    $form['#pre_render'][] = [FormIdPreRender::class, 'addFormId'];
    if ($request->get(AjaxResponseSubscriber::AJAX_REQUEST_PARAMETER) !== NULL) {
      // Add the data-ajax flag and manually add the form ID as pre render
      // callbacks aren't fired during AJAX rendering because the whole form is
      // not rendered, just the returned elements.
      FormIdPreRender::addAjaxAttribute($form, $form['#attributes']['data-form-id']);
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // @todo implement submitForm() method.
  }

}

