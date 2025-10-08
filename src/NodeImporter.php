<?php

namespace Drupal\cgspace_importer;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Config\ImmutableConfig;

class NodeImporter {

  use DependencySerializationTrait;
  private ImmutableConfig $config;
  private NodeImporterProcessorManager $pluginManager;
  private Connection $connection;
  private EntityStorageInterface $entityStorage;
  private LoggerChannelInterface $logger;

  public function __construct()
  {
    $this->config = \Drupal::config('cgspace_importer.mappings');
    $this->pluginManager = \Drupal::service('plugin.manager.cgspace_importer.processors');
    $this->connection = \Drupal::database();
    $this->entityStorage = \Drupal::entityTypeManager()->getStorage('node');
    $loggerFactory = \Drupal::service('logger.factory');
    $this->logger = $loggerFactory->get('cgspace_importer');
  }

  /**
   * Create a Node from passed array using the process plugins and mappings configuration
   *
   * @param array $item
   * The array with raw data returned by CGSpace API
   * @return void
   */
  public function add(array $item):void {

    try {

      $content_type = $this->config->get('content_type');

      $data = [
        'type' => $content_type,
        'uid'   => 1,
        'status'  => true,
        'langcode'  => 'und',
      ] + $this->getNodeData($item);


      $node = Node::create($data);

      $node->save();
    }
    catch(\Exception $ex) {
      $this->logger->error(
        t("Unable to save Item @item. @error",
          [
            '@item' => $item['uuid'],
            '@error' => $ex->getMessage()
          ])
      );
    }
  }

  /**
   * Update the passed Node with item array passed as argument using the process plugins and mappings configuration
   *
   * @param array $item
   * The array with raw data returned by CGSpace API
   * @return void
   */
  public function update(NodeInterface $node, array $item):void {
    try {

      $data = $this->getNodeData($item);
      foreach($data as $field_name => $field_value) {
        $node->set($field_name, $field_value);
      }

      $node->save();

    } catch(\Exception $ex) {
      $this->logger->error(
        t("Unable to update Item @item. @error",
          [
            '@item' => $item['uuid'],
            '@error' => $ex->getMessage()
          ])
      );
    }
  }

  /**
   * @param array $item
   * @return array
   */
  private function getNodeData(array $item):array {
    $data = [];
    $configuration = [];

    foreach($this->config->get('mappings') as $mapping) {
      if(isset($mapping['process'])) {
        $plugin_name = $mapping['process']['plugin'];
        $configuration = $mapping['process'];
      }
      else {
        $plugin_name = 'default';
      }
      $plugin_definition = $this->pluginManager->getDefinition('cgspace_processor_' . $plugin_name);
      $plugin = $this->pluginManager->createInstance($plugin_definition['id'], $configuration);
      if(!empty($field = $plugin->process($mapping['source'], $mapping['target'], $item))) {
        $data += $field;
      }
    }

    //$this->logger->notice(t("Processing @item", ['@item' => $item['name']]));

    return $data;
  }

  /**
   * Check that Node with uuid_field equal to UUID passed as argument exists
   * @param $uuid
   * the UUID of the Node to check
   * @return bool
   * true if it exists false otherwise
   */
  public function exists(string $uuid):bool {

    $query = $this->connection->select('node__'.$this->config->get('uuid_field'), 'f');
    $query->join('node_field_data', 'n', 'n.nid = f.entity_id');
    $query->fields('f', [$this->config->get('uuid_field').'_value']);
    $query->condition('n.type', $this->config->get('content_type'));
    $query->condition('f.'.$this->config->get('uuid_field').'_value', $uuid);

    return !empty($query->execute()->fetchCol());
  }

  /**
   * Load Node with uuid_field equal to UUID passed as argument
   *
   * @param $uuid
   * the UUID of the Node to load
   * @return mixed
   */
  public function get($uuid):mixed {
    $nodes = $this->entityStorage->loadByProperties([
      'type' => $this->config->get('content_type'),
      $this->config->get('uuid_field') => $uuid
    ]);

    return reset($nodes);
  }

  /**
   * Delete an array of Nodes with uuid_field equal to passed uuids
   *
   * @param $uuids
   * the array of nodes with uuid field equal to passed uuids
   * @param $context
   * the Batch process context
   * @return bool
   * true if nodes are deleted false otherwise
   */
  public function delete(array $uuids, array &$context):bool {
    try {

      $nids = \Drupal::entityQuery('node')
        ->accessCheck(FALSE)
        ->condition($this->config->get('uuid_field'), $uuids, 'IN')
        ->execute();

      if(!empty($nids) && !empty($nodes = $this->entityStorage->loadMultiple($nids))) {
        $this->entityStorage->delete($nodes);

        $context['message'] = t('Deleting nodes (@nids).', [
          '@nids' => implode(',', $nids)
        ]);

        return true;
      }

    }
    catch (\Exception $ex) {
      $this->logger->error(
        t("Error deleting node . @error",
          [
            '@error' => $ex->getMessage()
          ])
      );
    }
    return false;
  }

}
