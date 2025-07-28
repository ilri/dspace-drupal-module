<?php

namespace Drupal\cgspace_importer\Plugin\NodeImporterProcessors;

use Drupal\cgspace_importer\BaseProcessor;
use Drupal\cgspace_importer\NodeImporterProcessorInterface;

/**
 * @NodeImporterProcessor(
 *   id = "cgspace_processor_authored_on",
 *   label = @Translation("Authored On Processor"),
 *   description = @Translation("Fix incomplete dates issue (Ex: 01-2003) on CGSpace adding missing month and day set to 01."),
 *   )
 */
class AuthoredOnProcessor extends BaseProcessor implements NodeImporterProcessorInterface {

  /**
   * {@inheritDoc}
   */
  public function process(string $source, string $target, array $item):array {

    $value = $this->getSourceValue($source, $item);

    if(!is_null($value)) {

      $date_parts = explode('-', $value);

      if (count($date_parts) === 1) {
        //we have only year
        $value .= '-01-01';
      }
      if (count($date_parts) === 2) {
        //we have year and month
        $value .= '-01';
      }

      $date = \DateTimeImmutable::createFromFormat("Y-m-d", $value);

      if($date !== false) {
        $format = 'Y-m-d';
        return [$target => $date->format($format)];
      }
    }

    return [];
  }

}
