<?php

namespace Drupal\Tests\sitestudio_jsonapi\Kernel;

use Drupal\cohesion_elements\Entity\CohesionLayout;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests anonymous JSON:API reads of layouts whose owner denies view access.
 *
 * @group Cohesion
 */
class CohesionLayoutJsonApiAccessTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'file',
    'serialization',
    'jsonapi',
    'jsonapi_extras',
    'entity_reference_revisions',
    'token',
    'cohesion',
    'cohesion_elements',
    'cohesion_templates',
    'cohesion_website_settings',
    'sitestudio_data_transformers',
    'sitestudio_jsonapi',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('cohesion', ['coh_usage', 'coh_element_schema_info']);
    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('cohesion_layout');
    $this->installConfig(['node', 'field', 'user', 'jsonapi_extras', 'sitestudio_jsonapi']);

    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_layout',
      'entity_type' => 'node',
      'type' => 'entity_reference_revisions',
      'cardinality' => 1,
      'settings' => ['target_type' => 'cohesion_layout'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_layout',
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => 'Layout',
    ])->save();

    // Site Studio disables its routes until assets are imported and an API key
    // is configured.
    $this->config('cohesion.settings')
      ->set('asset_is_imported', TRUE)
      ->set('use_dx8', 'enable')
      ->set('api_key', 'test')
      ->save();

    $dx8_no_send_to_api = &drupal_static('dx8_no_send_to_api');
    $dx8_no_send_to_api = TRUE;

    // Anonymous users may only see published content.
    user_role_grant_permissions('anonymous', ['access content']);
    \Drupal::service('router.builder')->rebuild();
  }

  /**
   * Creates a node with a layout and returns the layout.
   */
  protected function createLayout(bool $published): CohesionLayout {
    $layout = CohesionLayout::create([
      'json_values' => '{"canvas":[],"mapper":{},"model":{}}',
      'parent_type' => 'node',
      'parent_field_name' => 'field_layout',
    ]);
    $layout->save();

    $node = Node::create([
      'type' => 'page',
      'title' => 'Page',
      'status' => $published,
      'field_layout' => [
        'target_id' => $layout->id(),
        'target_revision_id' => $layout->getRevisionId(),
      ],
    ]);
    $node->save();

    $layout->set('parent_id', $node->id());
    $layout->save();
    return $layout;
  }

  /**
   * Performs an anonymous JSON:API request.
   */
  protected function request(string $uuid): int {
    $this->setCurrentUser(new AnonymousUserSession());
    $request = Request::create('/jsonapi/cohesion_layout/cohesion_layout/' . $uuid, 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/vnd.api+json']);
    $response = $this->container->get('http_kernel')->handle($request);
    return $response->getStatusCode();
  }

  /**
   * An anonymous client cannot read a layout owned by an unpublished node.
   */
  public function testAnonymousCannotReadLayoutOfUnpublishedOwner(): void {
    $layout = $this->createLayout(FALSE);
    $this->assertContains($this->request($layout->uuid()), [403, 404]);
  }

  /**
   * An anonymous client can read a layout owned by a published node.
   */
  public function testAnonymousCanReadLayoutOfPublishedOwner(): void {
    $layout = $this->createLayout(TRUE);
    $this->assertSame(200, $this->request($layout->uuid()));
  }

}
