<?php

namespace Drupal\cgspace_importer\Plugin\NodeImporterProcessors;

use Drupal\cgspace_importer\BaseProcessor;
use Drupal\cgspace_importer\NodeImporterProcessorInterface;

/**
 * @NodeImporterProcessor(
 *   id = "cgspace_processor_default",
 *   label = @Translation("Default Processor"),
 *   description = @Translation("Provides default processor for CGSpace Importer plugin"),
 *   )
 */
class DefaultProcessor extends BaseProcessor implements NodeImporterProcessorInterface {

  /**
   * {@inheritDoc}
   */
  public function process(string $source, string $target, array $item):array {

    $value = $this->getSourceValue($source, $item);

    if(!is_null($value)) {
      return [$target => $value];
    }

    return [];
  }


}
