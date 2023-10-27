<?php

namespace Drupal\entity_policy_override_test;

use Drupal\entity_reference_revisions\Plugin\Field\FieldType\EntityReferenceRevisionsItem;
use Drupal\entity_reference_revisions\RevisionCreationPolicy\ChainRevisionCreationPolicy;
use Drupal\entity_reference_revisions\RevisionCreationPolicy\RevisionCreationPolicyInterface;

class EntityTestPolicyOverride implements RevisionCreationPolicyInterface {

  public function initializer(RevisionCreationPolicyInterface $chain) {
    $chain->removeAllPolicies();
    $chain->addPolicy($this);

    return $chain;
  }

  public function shouldCreateNewRevision(EntityReferenceRevisionsItem $item) {
    return FALSE;
  }

}
