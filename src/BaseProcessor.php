<?php

namespace Drupal\cgspace_importer;


use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Logger\LoggerChannelInterface;

/**
 * Base Processor Plugin class
 */
class BaseProcessor extends PluginBase implements NodeImporterProcessorInterface {

  /**
   * The module logger
   * @var LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;
  public function __construct(array $configuration, $plugin_id, $plugin_definition)
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->logger = \Drupal::service('logger.factory')->get('cgspace_importer');
  }

  /**
   * Search for source on the CGSpace item data structure looking on root elements and on metadata children
   *
   * @param string $source
   * The source item name
   * @param array $item
   * the full item data
   * @return mixed|null
   * the value of the source item or null if not found
   *
   */
  protected function getSourceValue(string $source,array $item) {
    $source_value = null;

    if(isset($item[$source])) {
      $source_value = $item[$source];
    }

    if(isset($item['metadata'][$source])) {
      $source_value = $item['metadata'][$source][0]['value'];
    }

    return $source_value;
  }

  /**
   * {@inheritDoc}
   */
  public function process(string $source, string $target, array $item): array
  {
    return [];
  }
}
