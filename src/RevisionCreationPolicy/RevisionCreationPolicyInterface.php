<?php

namespace Drupal\entity_reference_revisions\RevisionCreationPolicy;

use Drupal\entity_reference_revisions\Plugin\Field\FieldType\EntityReferenceRevisionsItem;

interface RevisionCreationPolicyInterface {

  public function initializer(RevisionCreationPolicyInterface $policy);

  public function shouldCreateNewRevision(EntityReferenceRevisionsItem $item);

}
