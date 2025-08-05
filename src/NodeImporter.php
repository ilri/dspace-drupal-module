<?php

namespace Drupal\cgspace_importer;

use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

class NodeImporter {

  private $config;
  private $plugin_manager;
  private $connection;
  private $entityStorage;

  public function __construct()
  {
    $this->config = \Drupal::config('cgspace_importer.mappings');
    $this->plugin_manager = \Drupal::service('plugin.manager.cgspace_importer.processors');
    $this->connection = \Drupal::database();
    $this->entityStorage = \Drupal::entityTypeManager()->getStorage('node');
  }

  public function add(array $item) {

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
      \Drupal::logger('cgspace_importer')->error(
        t("Unable to save Item @item. @error",
          [
            '@item' => $item['uuid'],
            '@error' => $ex->getMessage()
          ])
      );
    }
  }

  public function update(NodeInterface $node, array $item) {
    try {

      $data = $this->getNodeData($item);
      foreach($data as $field_name => $field_value) {
        $node->set($field_name, $field_value);
      }

      $node->save();

    } catch(\Exception $ex) {
      \Drupal::messenger()->addError(
        t("Unable to update Item @item. @error",
          [
            '@item' => $item['uuid'],
            '@error' => $ex->getMessage()
          ])
      );
    }
  }

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
      $plugin_definition = $this->plugin_manager->getDefinition('cgspace_processor_' . $plugin_name);
      $plugin = $this->plugin_manager->createInstance($plugin_definition['id'], $configuration);
      if(!empty($field = $plugin->process($mapping['source'], $mapping['target'], $item))) {
        $data += $field;
      }
    }
    return $data;
  }

  public function exists($uuid):bool {

    $query = $this->connection->select('node__'.$this->config->get('uuid_field'), 'f');
    $query->join('node_field_data', 'n', 'n.nid = f.entity_id');
    $query->fields('f', [$this->config->get('uuid_field').'_value']);
    $query->condition('n.type', $this->config->get('content_type'));
    $query->condition('f.'.$this->config->get('uuid_field').'_value', $uuid);

    return !empty($query->execute()->fetchCol());
  }

  public function get($uuid) {
    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'type' => $this->config->get('content_type'),
      $this->config->get('uuid_field') => $uuid
    ]);

    return reset($nodes);
  }

  public function delete($uuids, &$context) {
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
      return false;
    }
    catch (\Exception $ex) {
      \Drupal::logger('cgspace_importer')->error(
        t("Error deleting node . @error",
          [
            '@error' => $ex->getMessage()
          ])
      );
    }
  }

}
