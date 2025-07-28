<?php

namespace Drupal\cgspace_importer;


use Drupal\Component\Plugin\PluginBase;

class BaseProcessor extends PluginBase implements NodeImporterProcessorInterface {

  public function getSourceValue(string $source,array $item) {
    $source_value = null;

    if(isset($item[$source])) {
      $source_value = $item[$source];
    }

    if(isset($item['metadata'][$source])) {
      $source_value = $item['metadata'][$source][0]['value'];
    }

    return $source_value;
  }

  public function process(string $source, string $target, array $item): array
  {
    return [];
  }
}
