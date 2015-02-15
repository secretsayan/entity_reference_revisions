<?php

/**
 * @file
 * Contains \Drupal\entity_reference_revisions\Plugin\Field\FieldType\EntityReferenceRevisionsItem.
 */

namespace Drupal\entity_reference_revisions\Plugin\Field\FieldType;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\TypedData\EntityDataDefinition;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\DataReferenceDefinition;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;

/**
 * Defines the 'entity_reference_revisions' entity field type.
 *
 * Supported settings (below the definition's 'settings' key) are:
 * - target_type: The entity type to reference. Required.
 * - target_bundle: (optional): If set, restricts the entity bundles which may
 *   may be referenced. May be set to an single bundle, or to an array of
 *   allowed bundles.
 *
 * @FieldType(
 *   id = "entity_reference_revisions",
 *   label = @Translation("Entity reference revisions"),
 *   description = @Translation("An entity field containing an entity reference."),
 *   no_ui = TRUE,
 *   list_class = "\Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList",
 *   constraints = {"ValidReference" = {}}
 * )
 */
class EntityReferenceRevisionsItem extends EntityReferenceItem {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);

    $settings = $field_definition->getSettings();
    $target_type_info = \Drupal::entityManager()->getDefinition($settings['target_type']);

    if ($target_type_info->getKey('revision')) {
      $target_revision_id_definition = DataDefinition::create('integer')
        ->setLabel(t('@label revision ID', array($target_type_info->getLabel())))
        ->setSetting('unsigned', TRUE);

      $target_revision_id_definition->setRequired(TRUE);
      $properties['target_revision_id'] = $target_revision_id_definition;
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = parent::schema($field_definition);

    $target_type = $field_definition->getSetting('target_type');
    $target_type_info = \Drupal::entityManager()->getDefinition($target_type);

    if ($target_type_info->getKey('revision')) {
      $schema['columns']['target_revision_id'] = array(
        'description' => 'The revision ID of the target entity.',
        'type' => 'int',
        'unsigned' => TRUE,
      );
      $schema['indexes']['target_revision_id'] = array('target_revision_id');
    }

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($values, $notify = TRUE) {
    if (isset($values) && !is_array($values)) {
      // If either a scalar or an object was passed as the value for the item,
      // assign it to the 'entity' property since that works for both cases.
      $this->set('entity', $values, $notify);
    }
    else {
      parent::setValue($values, FALSE);
      if (isset($values['target_revision_id']) && !isset($values['entity'])) {
        $this->onChange('target_revision_id', FALSE);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    if ($this->hasNewEntity()) {
      // Save the entity if it has not already been saved by some other code.
      if ($this->entity->isNew()) {
        $this->entity->save();
      }
      // Make sure the parent knows we are updating this property so it can
      // react properly.
      $this->target_id = $this->entity->id();
      $this->target_revision_id = $this->entity->getRevisionId();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $manager = \Drupal::service('plugin.manager.entity_reference_selection');
    if ($referenceable = $manager->getSelectionHandler($field_definition)->getReferenceableEntities()) {
      $group = array_rand($referenceable);
      $values['target_id'] = array_rand($referenceable[$group]);
      return $values;
    }
  }

  /**
   * Determines whether the item holds an unsaved entity.
   *
   * This is notably used for "autocreate" widgets, and more generally to
   * support referencing freshly created entities (they will get saved
   * automatically as the hosting entity gets saved).
   *
   * @return bool
   *   TRUE if the item holds an unsaved entity.
   */
  public function hasNewEntity() {
    return $this->target_id === static::$NEW_ENTITY_MARKER;
  }

  /**
   * {@inheritdoc}
   */
  public static function calculateDependencies(FieldDefinitionInterface $field_definition) {
    $dependencies = [];
    if (is_array($field_definition->default_value) && count($field_definition->default_value)) {
      $target_entity_type = \Drupal::entityManager()->getDefinition($field_definition->getFieldStorageDefinition()->getSetting('target_type'));
      foreach ($field_definition->default_value as $default_value) {
        if (is_array($default_value) && isset($default_value['target_uuid'])) {
          $entity = \Drupal::entityManager()->loadEntityByUuid($target_entity_type->id(), $default_value['target_uuid']);
          // If the entity does not exist do not create the dependency.
          // @see \Drupal\Core\Field\EntityReferenceFieldItemList::processDefaultValue()
          if ($entity) {
            $dependencies[$target_entity_type->getConfigDependencyKey()][] = $entity->getConfigDependencyName();
          }
        }
      }
    }
    return $dependencies;
  }

}
