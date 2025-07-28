<?php

namespace Drupal\cgspace_importer\Plugin\NodeImporterProcessors;

use Drupal\cgspace_importer\NodeImporterProcessorInterface;

/**
 * @NodeImporterProcessor(
 *   id = "cgspace_processor_publication_year",
 *   label = @Translation("Publication Year Processor"),
 *   description = @Translation("Extends the Taxonomy Term Processor to map publication year truncating date source value."),
 *   )
 */
class PublicationYearProcessor extends TaxonomyTermProcessor implements NodeImporterProcessorInterface {

  /**
   * {@inheritDoc}
   */
  protected function setTerm($name, $vid) {
    return [
      'name' => trim(substr($name, 0, 4)),
      'vid'  => $vid
    ];
  }

}

