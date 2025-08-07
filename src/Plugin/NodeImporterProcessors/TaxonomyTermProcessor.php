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

    $value = $this->getSourceValue($source, $item);

    if(!is_null($value)) {

      if (isset($this->configuration['configuration'])) {
        $vocabulary = \Drupal::config($this->configuration['configuration'])->get('vocabulary');
      }

      if(empty($vocabulary)) {
        $vocabulary = $this->configuration['vocabulary'];
      }


      $properties = $this->setTerm($value, $vocabulary);

      $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties($properties);

      $term = reset($terms);
      if (!empty($term)) {
        return [
          $target => [
            'target_id' => $term->id()
          ]
        ];
      }

      if(isset($this->configuration['create'])) {
        $term = Term::create($properties);
        $term->save();

        return [
          $target => [
            'target_id' => $term->id()
          ]
        ];
      }
    }

    return [];
  }

  /**
   * Return an array with term name and vocabulary machine name ready for loadByProperties
   * @param $name
   * the term name
   * @param $vid
   * the vocabulary machine name
   * @return array
   * the array ready for loadByProperties
   */
  protected function setTerm($name, $vid) {
    return [
      'name' => $name,
      'vid'  => $vid
    ];
  }



}

