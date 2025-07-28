<?php

namespace Drupal\cgspace_importer;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Provides a plugin manager for webform element plugins.
 *
 */
class NodeImporterProcessorManager extends DefaultPluginManager {


  /**
   * The configuration object factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;


  /**
   * The Entity type manager
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * List of already instantiated webform element plugins.
   *
   * @var array
   */
  protected $instances = [];

  /**
   * Constructs a WebformDonationSMSGatewayManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The theme handler.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration object factory.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct('Plugin/NodeImporterProcessors', $namespaces, $module_handler, 'Drupal\cgspace_importer\NodeImporterProcessorInterface', 'Drupal\cgspace_importer\Annotation\NodeImporterProcessor');
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;

    $this->alterInfo('cgspace_importer_processors_info');
    $this->setCacheBackend($cache_backend, 'cgspace_importer_processors_info_plugins');
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []) {
    // If configuration is empty create a single reusable instance for each
    // Webform element plugin.
    if (empty($configuration)) {
      if (!isset($this->instances[$plugin_id])) {
        $this->instances[$plugin_id] = parent::createInstance($plugin_id);
      }
      return $this->instances[$plugin_id];
    }
    else {
      return parent::createInstance($plugin_id, $configuration);
    }
  }


  public function process($source, $target): array
  {
    return [];
  }

}
