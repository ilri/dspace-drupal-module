<?php

namespace Drupal\cgspace_importer\Plugin\NodeImporterProcessors;

use Drupal\cgspace_importer\BaseProcessor;
use Drupal\cgspace_importer\NodeImporterProcessorInterface;

/**
 * @NodeImporterProcessor(
 *   id = "cgspace_processor_doi",
 *   label = @Translation("Doi Processor"),
 *   description = @Translation("Provides DOI processor for CGSpace Importer plugin"),
 *   )
 */
class DoiProcessor extends BaseProcessor implements NodeImporterProcessorInterface {

  /**
   * {@inheritDoc}
   */
  public function process(string $source, string $target, array $item):array {

    $value = $this->getSourceValue($source, $item);

    if(!is_null($value)) {
      return [$target => str_replace(['http://dx.doi.org/', 'https://dx.doi.org/'], '', $value)];
    }

    return [];
  }


}
