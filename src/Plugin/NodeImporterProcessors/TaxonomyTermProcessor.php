<?php

namespace Drupal\cgspace_importer\Plugin\NodeImporterProcessors;

use Drupal\cgspace_importer\BaseProcessor;
use Drupal\cgspace_importer\NodeImporterProcessorInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy\Entity\Term;
/**
 * @NodeImporterProcessor(
 *   id = "cgspace_processor_taxonomy_term",
 *   label = @Translation("Taxonomy Term Processor"),
 *   description = @Translation("Try to map source item to a Taxonomy Term of the vocabulary passed as parameter. If not found the term is created if create option is set."),
 *   )
 */
class TaxonomyTermProcessor extends BaseProcessor implements NodeImporterProcessorInterface {

  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = \Drupal::entityTypeManager();
  }

  /**
   * {@inheritDoc}
   */
  public function process(string $source, string $target, array $item):array {

    if(!isset($this->configuration['vocabulary'])) {
      Throw new \Exception('Missing mandatory Vocabulary parameter!');
    }

    $result = [];
    $values = $this->getSourceValue($source, $item);

    if(!is_null($values)) {

      if (isset($this->configuration['configuration'])) {
        $vocabulary = \Drupal::config($this->configuration['configuration'])->get('vocabulary');
      }

      if(empty($vocabulary)) {
        $vocabulary = $this->configuration['vocabulary'];
      }

      if(!is_array($values)) {
        $values = [$values];
      }
      /*
      else {
        if(count($values) > 1) {
          $this->logger->notice(t('Item: @item - Field: @field - Values: @values',
              [
                '@item' => $item['name'],
                '@field' => $target,
                '@values' => print_r($values, TRUE),
              ]
            )
          );
        }
      }
      */
      foreach ($values as $value) {
        $properties = $this->setTerm($value, $vocabulary);

        $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties($properties);

        $term = reset($terms);
        if (!empty($term)) {
          $result[$target][] = [
            'target_id' => $term->id()
          ];
        }
        else if(isset($this->configuration['create'])) {
          $term = Term::create($properties);
          $term->save();

          $result[$target][] = [
            'target_id' => $term->id()
          ];
        }
      }

    }

    return $result;
  }

  /**
   * Search for source on the CGSpace item data structure looking on root elements and on metadata children
   *
   * @param string $source
   * The source item name
   * @param array $item
   * the full item data
   * @return mixed|null
   * the value of the source item or null if not found
   *
   */
  protected function getSourceValue(string $source,array $item) {
    $source_value = null;

    if(isset($item[$source])) {
      $source_value = $item[$source];
    }

    if(isset($item['metadata'][$source])) {
      if(is_array($item['metadata'][$source])) {
        foreach($item['metadata'][$source] as $value) {
          if(isset($value['value'])) {
            $source_value[] = $value['value'];
          }
        }
      } else {
        $source_value = $item['metadata'][$source];
      }
    }
    if(is_array($source_value) && count($source_value) === 1) {
      $source_value = reset($source_value);
    }

    return $source_value;
  }

  protected function setTerm($name, $vid) {
    return [
      'name' => $name,
      'vid'  => $vid
    ];
  }
}
