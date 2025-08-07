<?php

namespace Drupal\cgspace_importer\Plugin\NodeImporterProcessors;

use Drupal\cgspace_importer\BaseProcessor;
use Drupal\cgspace_importer\NodeImporterProcessorInterface;

/**
 * @NodeImporterProcessor(
 *   id = "cgspace_processor_markup",
 *   label = @Translation("Markup Processor"),
 *   description = @Translation("Provides markup processor for Formatted Drupal text. You can specify the filter format with format parameter."),
 *   )
 */
class MarkupProcessor extends BaseProcessor implements NodeImporterProcessorInterface {

  /**
   * {@inheritDoc}
   */
  public function process(string $source, string $target, array $item):array {

    if(!isset($this->configuration['format'])) {
      Throw new \Exception('Missing format parameter!');
    }

    $value = $this->getSourceValue($source, $item);
    //set format to $this->configuration['format'] if it exists otherwise fallback to plain_text
    $formats = filter_formats();

    if(!is_null($value)) {
      return [
        $target => [
          'value' => $value,
          'format' => in_array($this->configuration['format'], $formats) ? $this->configuration['format'] : 'plain_text'
        ]
      ];
    }

    return [];

  }


}
