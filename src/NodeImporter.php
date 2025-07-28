<?php

namespace Drupal\cgspace_importer;

use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

class NodeImporter {

  private $config;
  private $plugin_manager;

  public function __construct()
  {
    $this->config = \Drupal::config('cgspace_importer.mappings');
    $this->plugin_manager = \Drupal::service('plugin.manager.cgspace_importer.processors');
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
      \Drupal::messenger()->addError(
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

}
