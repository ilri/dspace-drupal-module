<?php

namespace Drupal\cgspace_importer\Plugin\NodeImporterProcessors;

use Drupal\cgspace_importer\BaseProcessor;
use Drupal\cgspace_importer\NodeImporterProcessorInterface;

/**
 * @NodeImporterProcessor(
 *   id = "cgspace_processor_truncate",
 *   label = @Translation("Truncate Processor"),
 *   description = @Translation("Provides truncate processor to length parameter for CGSpace Importer plugin"),
 *   )
 */
class TruncateProcessor extends BaseProcessor implements NodeImporterProcessorInterface {

  /**
   * {@inheritDoc}
   */
  public function process(string $source, string $target, array $item):array {

    if(!isset($this->configuration['length'])) {
      Throw new \Exception('Missing length parameter!');
    }

    $value = $this->getSourceValue($source, $item);

    if(!is_null($value)) {
      return [
        $target => substr($value, 0, $this->configuration['length'])
      ];
    }

    return [];
  }


}
