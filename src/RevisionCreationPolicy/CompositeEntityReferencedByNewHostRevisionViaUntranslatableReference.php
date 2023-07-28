<?php

namespace Drupal\entity_reference_revisions\RevisionCreationPolicy;

use Drupal\entity_reference_revisions\Plugin\Field\FieldType\EntityReferenceRevisionsItem;
use Drupal\entity_reference_revisions\RevisionCreationPolicy\RevisionCreationPolicyInterface;

class CompositeEntityReferencedByNewHostRevisionViaUntranslatableReference implements RevisionCreationPolicyInterface {

  public function initializer(RevisionCreationPolicyInterface $chain) {
    $chain->addPolicy($this);
    return $chain;
  }

  public function shouldCreateNewRevision(EntityReferenceRevisionsItem $item) {
    $host = $item->getEntity();
    if (
      // A composite entity
      $item->entity && $item->entity->getEntityType()
        ->get('entity_revision_parent_id_field') &&

      // Referenced by a new host revision
      !$host->isNew() && $host->isNewRevision() &&

      // Via an untranslatable reference
      !$item->getFieldDefinition()->isTranslatable()
    ) {
      return TRUE;
    }
  }

}
