<?php

/**
 * @file
 * Contains \Drupal\entity_reference_revisions\Plugin\Field\FieldWidget\EntityReferenceRevisionsAutocompleteWidget.
 */

namespace Drupal\entity_reference_revisions\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Tags;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\EntityOwnerInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget;

/**
 * Plugin implementation of the 'entity_reference_autocomplete' widget.
 *
 * @FieldWidget(
 *   id = "entity_reference_revisions_autocomplete",
 *   label = @Translation("Autocomplete"),
 *   description = @Translation("An autocomplete text field."),
 *   field_types = {
 *     "entity_reference_revisions"
 *   }
 * )
 */
class EntityReferenceRevisionsAutocompleteWidget extends EntityReferenceAutocompleteWidget {

}
