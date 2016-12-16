<?php

namespace Drupal\Tests\entity_reference_revisions\Kernel;

use Drupal\entity_composite_relationship_test\Entity\EntityTestCompositeRelationship;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\simpletest\ContentTypeCreationTrait;
use Drupal\simpletest\NodeCreationTrait;

/**
 * Tests the entity_reference_revisions composite relationship.
 *
 * @group entity_reference_revisions
 */
class EntityReferenceRevisionsCompositeTest extends EntityKernelTestBase {

  use ContentTypeCreationTrait;
  use NodeCreationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array(
    'node',
    'field',
    'entity_reference_revisions',
    'entity_composite_relationship_test',
    'language'
  );

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('entity_test_composite');
    $this->installSchema('node', ['node_access']);

    // Create article content type.
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    // Create the reference to the composite entity test.
    $field_storage = FieldStorageConfig::create(array(
      'field_name' => 'composite_reference',
      'entity_type' => 'node',
      'type' => 'entity_reference_revisions',
      'settings' => array(
        'target_type' => 'entity_test_composite'
      ),
    ));
    $field_storage->save();
    $field = FieldConfig::create(array(
      'field_storage' => $field_storage,
      'bundle' => 'article',
      'translatable' => FALSE,
    ));
    $field->save();
  }

  /**
   * Test for maintaining composite relationship.
   *
   * Tests that the referenced entity saves the parent type and id when saving.
   */
  public function testEntityReferenceRevisionsCompositeRelationship() {
    // Create the test composite entity.
    $composite = EntityTestCompositeRelationship::create(array(
      'uuid' => $this->randomMachineName(),
      'name' => $this->randomMachineName(),
    ));
    $composite->save();

    // Create a node with a reference to the test composite entity.
    $node = Node::create(array(
      'title' => $this->randomMachineName(),
      'type' => 'article',
      'composite_reference' => $composite,
    ));
    $node->save();

    // Verify the value of parent type and id after create a node.
    $composite = EntityTestCompositeRelationship::load($composite->id());
    $this->assertEqual($composite->parent_type->value, $node->getEntityTypeId());
    $this->assertEqual($composite->parent_id->value, $node->id());
    $this->assertEqual($composite->parent_field_name->value, 'composite_reference');

    // Test that the composite entity is deleted when its parent is deleted.
    $node->delete();
    $this->assertNull(EntityTestCompositeRelationship::load($composite->id()));
  }

  /**
   * Tests composite relationship with translations and an untranslatable field.
   */
  function testCompositeRelationshipWithTranslationNonTranslatableField() {

    ConfigurableLanguage::createFromLangcode('de')->save();

    // Create the test composite entity with a translation.
    $composite = EntityTestCompositeRelationship::create(array(
      'uuid' => $this->randomMachineName(),
      'name' => $this->randomMachineName(),
    ));
    $composite->addTranslation('de', $composite->toArray());
    $composite->save();


    // Create a node with a reference to the test composite entity.
    $node = Node::create(array(
      'title' => $this->randomMachineName(),
      'type' => 'article',
      'composite_reference' => $composite,
    ));
    $node->addTranslation('de', $node->toArray());
    $node->save();

    // Verify the value of parent type and id after create a node.
    $composite = EntityTestCompositeRelationship::load($composite->id());
    $this->assertEqual($composite->parent_type->value, $node->getEntityTypeId());
    $this->assertEqual($composite->parent_id->value, $node->id());
    $this->assertEqual($composite->parent_field_name->value, 'composite_reference');
    $this->assertTrue($composite->hasTranslation('de'));

    // Test that the composite entity is not when the german translation of the
    // parent is deleted.
    $node->removeTranslation('de');
    $node->save();
    $composite = EntityTestCompositeRelationship::load($composite->id());
    $this->assertNotNull($composite);
    // @todo Support deleting translations of a composite reference.
    //   @see https://www.drupal.org/node/2834314.
    //$this->assertFalse($composite->hasTranslation('de'));

    // Test that the composite entity is deleted when its parent is deleted.
    $node->delete();
    $composite = EntityTestCompositeRelationship::load($composite->id());
    $this->assertNull($composite);
  }

  /**
   * Tests composite relationship with translations and a translatable field.
   */
  function testCompositeRelationshipWithTranslationTranslatableField() {
    $field_config = FieldConfig::loadByName('node', 'article', 'composite_reference');
    $field_config->setTranslatable(TRUE);
    $field_config->save();

    ConfigurableLanguage::createFromLangcode('de')->save();

    // Create the test composite entity with a translation.
    $composite = EntityTestCompositeRelationship::create(array(
      'uuid' => $this->randomMachineName(),
      'name' => $this->randomMachineName(),
    ));
    $composite->addTranslation('de', $composite->toArray());
    $composite->save();

    // Create a node with a reference to the test composite entity.
    $node = Node::create(array(
      'title' => $this->randomMachineName(),
      'type' => 'article',
      'composite_reference' => $composite,
    ));
    $node->addTranslation('de', $node->toArray());
    $node->save();

    // Verify the value of parent type and id after create a node.
    $composite = EntityTestCompositeRelationship::load($composite->id());
    $this->assertEqual($composite->parent_type->value, $node->getEntityTypeId());
    $this->assertEqual($composite->parent_id->value, $node->id());
    $this->assertEqual($composite->parent_field_name->value, 'composite_reference');

    // Test that the composite entity is not when the german translation of the parent is deleted.
    $node->removeTranslation('de');
    $node->save();
    //\Drupal::entityTypeManager()->getStorage('entity_test_composite')->resetCache();
    $composite = EntityTestCompositeRelationship::load($composite->id());
    $this->assertNotNull($composite);

    // Test that the composite entity is deleted when its parent is deleted.
    $node->delete();
    $composite = EntityTestCompositeRelationship::load($composite->id());
    // @todo Support deletions for translatable fields.
    //   @see https://www.drupal.org/node/2834374
    // $this->assertNull($composite);
  }

  /**
   * Tests composite relationship with revisions.
   */
  function testCompositeRelationshipWithRevisions() {

    // Create the test composite entity with a translation.
    $composite = EntityTestCompositeRelationship::create(array(
      'uuid' => $this->randomMachineName(),
      'name' => $this->randomMachineName(),
    ));
    $composite->save();

    // Create a node with a reference to the test composite entity.
    $node = Node::create(array(
      'title' => $this->randomMachineName(),
      'type' => 'article',
      'composite_reference' => $composite,
    ));
    $node->save();


    // Verify the value of parent type and id after create a node.
    $composite = EntityTestCompositeRelationship::load($composite->id());
    $composite_original_revision_id = $composite->getRevisionId();
    $node_original_revision_id = $node->getRevisionId();
    $this->assertEqual($composite->parent_type->value, $node->getEntityTypeId());
    $this->assertEqual($composite->parent_id->value, $node->id());
    $this->assertEqual($composite->parent_field_name->value, 'composite_reference');

    $node->setNewRevision(TRUE);
    // @todo Enforce this when saving a new revision.
    $node->composite_reference->entity->setNeedsSave(TRUE);
    $node->composite_reference->entity->setNewRevision(TRUE);
    $node->save();

    // Ensure that we saved a new revision ID.
    $composite = EntityTestCompositeRelationship::load($composite->id());
    $this->assertNotEqual($composite->getRevisionId(), $composite_original_revision_id);

    // Test that deleting the first revision does not delete the composite.
    \Drupal::entityTypeManager()->getStorage('node')->deleteRevision($node_original_revision_id);
    $composite = EntityTestCompositeRelationship::load($composite->id());
    $this->assertNotNull($composite);

    // Ensure that the composite revision was deleted as well.
    $composite_revision = \Drupal::entityTypeManager()->getStorage('entity_test_composite')->loadRevision($composite_original_revision_id);
    // @todo Support host revision delete.
    //   @see https://www.drupal.org/node/2771523.
    // $this->assertNull($composite_revision);

    // Test that the composite entity is deleted when its parent is deleted.
    $node->delete();
    $composite = EntityTestCompositeRelationship::load($composite->id());
    $this->assertNull($composite);
  }

}
