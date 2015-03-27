<?php

/**
 * @file
 * Contains \Drupal\entity_reference_revisions\ConfigurableEntityReferenceItem.
 */

namespace Drupal\entity_reference_revisions;

use Drupal\Component\Utility\String;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\OptGroup;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TypedData\OptionsProviderInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\Validation\Plugin\Validation\Constraint\AllowedValuesConstraint;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\entity_reference_revisions\Plugin\Field\FieldType\EntityReferenceRevisionsItem;

/**
 * Alternative plugin implementation of the 'entity_reference_revisions' field type.
 *
 * Replaces the Core 'entity_reference_revisions' entity field type implementation, this
 * supports configurable fields, auto-creation of referenced entities and more.
 *
 * Required settings are:
 *  - target_type: The entity type to reference.
 *
 * @see entity_reference_revisions_field_info_alter().
 */
class ConfigurableEntityReferenceRevisionsItem extends EntityReferenceRevisionsItem implements OptionsProviderInterface {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    $settings = parent::defaultStorageSettings();
    // The target bundle is handled by the 'target_bundles' property in the
    // 'handler_settings' instance setting.
    unset($settings['target_bundle']);
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return array(
      'handler_settings' => array(),
    ) + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function getPossibleValues(AccountInterface $account = NULL) {
    return $this->getSettableValues($account);
  }

  /**
   * {@inheritdoc}
   */
  public function getPossibleOptions(AccountInterface $account = NULL) {
    return $this->getSettableOptions($account);
  }

  /**
   * {@inheritdoc}
   */
  public function getSettableValues(AccountInterface $account = NULL) {
    // Flatten options first, because "settable options" may contain group
    // arrays.
    $flatten_options = OptGroup::flattenOptions($this->getSettableOptions($account));
    return array_keys($flatten_options);
  }

  /**
   * {@inheritdoc}
   */
  public function getSettableOptions(AccountInterface $account = NULL) {
    $field_definition = $this->getFieldDefinition();
    if (!$options = \Drupal::service('plugin.manager.entity_reference_selection')->getSelectionHandler($field_definition, $this->getEntity())->getReferenceableEntities()) {
      return array();
    }

    // Rebuild the array by changing the bundle key into the bundle label.
    $target_type = $field_definition->getSetting('target_type');
    $bundles = \Drupal::entityManager()->getBundleInfo($target_type);

    $return = array();
    foreach ($options as $bundle => $entity_ids) {
      $bundle_label = String::checkPlain($bundles[$bundle]['label']);
      $return[$bundle_label] = $entity_ids;
    }

    return count($return) == 1 ? reset($return) : $return;
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $constraints = parent::getConstraints();

    // Remove the 'AllowedValuesConstraint' validation constraint because entity
    // reference fields already use the 'ValidReference' constraint.
    foreach ($constraints as $key => $constraint) {
      if ($constraint instanceof AllowedValuesConstraint) {
        unset($constraints[$key]);
      }
    }

    return $constraints;
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {

    $entity_types = \Drupal::entityManager()->getDefinitions();
    $options = array();
    foreach ($entity_types as $entity_type) {
      if ($entity_type->isRevisionable()) {
        $options[$entity_type->id()] = $entity_type->getLabel();
      }
    }

    $element['target_type'] = array(
      '#type' => 'select',
      '#title' => t('Type of item to reference'),
      '#options' => $options,
      '#default_value' => $this->getSetting('target_type'),
      '#required' => TRUE,
      '#disabled' => $has_data,
      '#size' => 1,
    );

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $field = $form_state->get('field');

    // Get all selection plugins for this entity type.
    $selection_plugins = \Drupal::service('plugin.manager.entity_reference_selection')->getSelectionGroups($this->getSetting('target_type'));
    $handlers_options = array();
    foreach (array_keys($selection_plugins) as $selection_group_id) {
      // We only display base plugins (e.g. 'default', 'views', ...) and not
      // entity type specific plugins (e.g. 'default:node', 'default:user',
      // ...).
      if (array_key_exists($selection_group_id, $selection_plugins[$selection_group_id])) {
        $handlers_options[$selection_group_id] = String::checkPlain($selection_plugins[$selection_group_id][$selection_group_id]['label']);
      }
      elseif (array_key_exists($selection_group_id . ':' . $this->getSetting('target_type'), $selection_plugins[$selection_group_id])) {
        $selection_group_plugin = $selection_group_id . ':' . $this->getSetting('target_type');
        $handlers_options[$selection_group_plugin] = String::checkPlain($selection_plugins[$selection_group_id][$selection_group_plugin]['base_plugin_label']);
      }
    }

    $form = array(
      '#type' => 'container',
      '#process' => array(
        '_entity_reference_revisions_field_field_settings_ajax_process',
      ),
      '#element_validate' => array(array(get_class($this), 'fieldSettingsFormValidate')),
    );
    $form['handler'] = array(
      '#type' => 'details',
      '#title' => t('Reference type'),
      '#open' => TRUE,
      '#tree' => TRUE,
      '#process' => array('_entity_reference_revisions_form_process_merge_parent'),
    );

    $form['handler']['handler'] = array(
      '#type' => 'select',
      '#title' => t('Reference method'),
      '#options' => $handlers_options,
      '#default_value' => $field->getSetting('handler'),
      '#required' => TRUE,
      '#ajax' => TRUE,
      '#limit_validation_errors' => array(),
    );
    $form['handler']['handler_submit'] = array(
      '#type' => 'submit',
      '#value' => t('Change handler'),
      '#limit_validation_errors' => array(),
      '#attributes' => array(
        'class' => array('js-hide'),
      ),
      '#submit' => array('entity_reference_revisions_settings_ajax_submit'),
    );

    $form['handler']['handler_settings'] = array(
      '#type' => 'container',
      '#attributes' => array('class' => array('entity_reference-settings')),
    );

    $handler = \Drupal::service('plugin.manager.entity_reference_selection')->getSelectionHandler($field);
    $form['handler']['handler_settings'] += $handler->buildConfigurationForm(array(), $form_state);

    return $form;
  }

  /**
   * Form element validation handler; Stores the new values in the form state.
   *
   * @param array $form
   *   The form where the settings form is being included in.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the (entire) configuration form.
   */
  public static function fieldSettingsFormValidate(array $form, FormStateInterface $form_state) {
    if ($form_state->hasValue('field')) {
      $form_state->unsetValue(array('field', 'settings', 'handler_submit'));
      $form_state->get('field')->settings = $form_state->getValue(['field', 'settings']);

      $handler = \Drupal::service('plugin.manager.entity_reference_selection')->getSelectionHandler($form_state->get('field'));
      $handler->validateConfigurationForm($form, $form_state);
    }
  }
}
