<?php

namespace Drupal\cgspace_importer\Plugin\NodeImporterProcessors;

use Drupal\cgspace_importer\BaseProcessor;
use Drupal\cgspace_importer\NodeImporterProcessorInterface;

/**
 * @NodeImporterProcessor(
 *   id = "cgspace_processor_link",
 *   label = @Translation("Link Processor"),
 *   description = @Translation("Provides Link processor for CGSpace Importer plugin"),
 *   )
 */
class LinkProcessor extends BaseProcessor implements NodeImporterProcessorInterface {

  /**
   * {@inheritDoc}
   */
  public function process(string $source, string $target, array $item):array {

    $value = $this->getSourceValue($source, $item);

    if(!is_null($value)) {
      $result = [
        $target => [
          'uri' => $value
        ]
      ];

      if(isset($this->configuration['title'])) {
        if(isset($item[$this->configuration['title']])) {
          $title = $item[$this->configuration['title']];
        }

        if(isset($item['metadata'][$this->configuration['title']])) {
          $title = $item['metadata'][$this->configuration['title']][0]['value'];
        }

        if(!is_null($title)) {
          $result[$target] += ['title' => $title];
        }

      }

      return $result;
    }

    return [];
  }


}
