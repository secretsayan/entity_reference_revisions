<?php

namespace Drupal\entity_reference_revisions\RevisionCreationPolicy;

interface ChainRevisionCreationPolicyInterface extends RevisionCreationPolicyInterface {

  public function addPolicy(RevisionCreationPolicyInterface $policy);

  public function removePolicy(RevisionCreationPolicyInterface $policy);

  public function removeAllPolicies();

}
