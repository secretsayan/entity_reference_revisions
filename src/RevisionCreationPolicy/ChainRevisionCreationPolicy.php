<?php

namespace Drupal\entity_reference_revisions\RevisionCreationPolicy;

use Drupal\entity_reference_revisions\RevisionCreationPolicy\ChainRevisionCreationPolicyInterface;
use Drupal\entity_reference_revisions\Plugin\Field\FieldType\EntityReferenceRevisionsItem;
use Drupal\entity_reference_revisions\RevisionCreationPolicy\RevisionCreationPolicyInterface;

class ChainRevisionCreationPolicy implements ChainRevisionCreationPolicyInterface {

  /**
   * A list of policy rules to apply when this policy is checked.
   *
   * @var \Drupal\entity_reference_revisions\RevisionCreationPolicy\RevisionCreationPolicyInterface[]
   */
  protected array $rules = [];

  /**
   * @param \Drupal\entity_reference_revisions\RevisionCreationPolicy\RevisionCreationPolicyInterface $policy
   *
   * @return $this
   */
  public function addPolicy(RevisionCreationPolicyInterface $policy) {
    $this->rules[] = $policy;
    return $this;
  }

  public function initializer(RevisionCreationPolicyInterface $policy) {
    $policy->initializer($this);
  }

  public function policyManager(RevisionCreationPolicyInterface $policy) {
    return $this->initializer($policy);
  }

  /**
   * @param \Drupal\entity_reference_revisions\RevisionCreationPolicy\RevisionCreationPolicyInterface $policy
   *
   * @return $this
   */
  public function removePolicy(RevisionCreationPolicyInterface $policy) {
    if (($key = array_search($policy, $this->rules)) !== FALSE) {
      unset($this->rules[$key]);
    }
    return $this;
  }

  /**
   * @return $this
   */
  public function removeAllPolicies() {
    $this->rules = [];
    return $this;
  }

  /**
   * @param \Drupal\entity_reference_revisions\Plugin\Field\FieldType\EntityReferenceRevisionsItem $item
   *
   * @return bool
   */
  public function shouldCreateNewRevision(EntityReferenceRevisionsItem $item) {

    foreach ($this->rules as $rule) {
      $result = $rule->shouldCreateNewRevision($item);
      if ($result === TRUE) {
        return $result;
      }
    }
    return FALSE;
  }

}
