<?php

namespace Drupal\sitestudio_jsonapi\Plugin\jsonapi\FieldEnhancer;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Utility\Token;
use Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerBase;
use Drupal\node\NodeInterface;
use Drupal\sitestudio_data_transformers\Services\LayoutCanvasManager;
use Shaper\DataAdaptor\DataAdaptorTransformerTrait;
use Shaper\DataAdaptor\DataAdaptorValidatorTrait;
use Shaper\Util\Context;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Perform additional manipulations to timestamp fields.
 *
 * @ResourceFieldEnhancer(
 *   id = "cohesion_layout",
 *   label = @Translation("Cohesion Layout (Site Studio Layout Canvas field)"),
 *   description = @Translation("Formats a layout canvas JSON in consumable format."),
 *   dependencies = {"cohesion"}
 * )
 */
class CohesionLayoutFieldEnhancer extends ResourceFieldEnhancerBase implements ContainerFactoryPluginInterface {

  use DataAdaptorTransformerTrait;
  use DataAdaptorValidatorTrait;

  /**
   * LayoutCanvasManager service.
   *
   * @var \Drupal\sitestudio_data_transformers\Services\LayoutCanvasManager
   */
  protected $layoutCanvasManager;

  /**
   * Token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * The currently active route match object.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The currently active user account proxy.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a new JSONFieldEnhancer.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Component\Serialization\Json $encoder
   *   The serialization json.
   * @param \Drupal\Core\Utility\Token $token
   *   Token service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The currently active route match object.
   * @param \Drupal\Core\Session\AccountProxyInterface $accountProxy
   *   The currently active user account proxy.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    LayoutCanvasManager $layoutCanvasManager,
    Token $token,
    RouteMatchInterface $routeMatch,
    AccountProxyInterface $accountProxy
  ) {
    $this->layoutCanvasManager = $layoutCanvasManager;
    $this->token = $token;
    $this->routeMatch = $routeMatch;
    $this->currentUser = $accountProxy;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('sitestudio_data_transformers.layout_canvas_manager'),
      $container->get('token'),
      $container->get('current_route_match'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputJsonSchema() {
    $definitions = $this->layoutCanvasManager->getSchema();

    return [
      "type" => "array",
      "anyOf" => [
        (object) ['$ref' => "#/definitions/layout_canvas_items"],
        (object) [
          "type" => "array",
          "items" => (object) ['$ref' => "#/definitions/layout_canvas_items"],
        ],
      ],
      "definitions" => $definitions,
    ];
  }

  /**
   * {@inheritdoc}
   *
   * public function getOutputValidator() {
   * return new JsonSchemaValidator($this->getOutputJsonSchema(), new Validator(), Constraint::CHECK_MODE_VALIDATE_SCHEMA);
   * }*/

  /**
   * Transforms outgoing data into another shape.
   *
   * This method will validate data coming in and going out using validators.
   *
   * @param mixed $data
   *   The data to transform.
   * @param \Shaper\Util\Context $context
   *   Additional information that will affect how the data is transformed.
   *
   * @return mixed
   *   The data in the new shape.
   *
   * @throws \TypeError
   *   When the transformation cannot be applied.
   */
  protected function doUndoTransform($data, Context $context) {
    $token_context['user'] = $this->currentUser;
    $entity = $this->routeMatch->getParameter('entity');
    if ($entity instanceof NodeInterface) {
      $token_context['node'] = $entity;
    }

    $data = json_encode($this->layoutCanvasManager->transformLayoutCanvasJson($data));
    $data = $this->token->replacePlain($data, $token_context);

    return json_decode($data);
  }

  /**
   * Transforms incoming data into another shape.
   *
   * This method will validate data coming in and going out using validators.
   *
   * @param mixed $data
   *   The data to transform.
   * @param \Shaper\Util\Context $context
   *   Additional information that will affect how the data is transformed.
   *
   * @return mixed
   *   The data in the new shape.
   *
   * @throws \TypeError
   *   When the transformation cannot be applied.
   */
  protected function doTransform($data, Context $context) {
    return json_decode($data);
  }

}
