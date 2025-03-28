<?php

namespace Drupal\cgspace_importer\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Language\LanguageInterface;

/**
 * This plugin tries to find a term match on a given vocabulary from a string.
 *
 * @MigrateProcessPlugin(
 *   id = "map_publication_projects"
 * )
 */
class MapPublicationProjects extends ProcessPluginBase {

  /**
   * The main function for the plugin, actually doing the data conversion.
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $fields = $row->getSource();

    if(is_array($fields)) {

      if (isset($value)) {
        $termName = $this->mapResearchPrograms($value);
      }
    }
    else {
      throw new MigrateException('invalid source');
    }
    if(!empty($termName)) {
      // Getting a term by lookup (if it exists), or creating one
      if (!empty($term = $this->getTerm($termName, $row, $this->configuration['vocabulary']))) {
        // Yes, all we need is ID.
        return $term->id();
      };
    }
  }

  protected function mapResearchPrograms($name) {

    switch($name) {
      //East Africa
      case 'Biosciences eastern and central Africa (BecA) ILRI Hub':
        $term = 'BecA-ILRI hub';
        break;
      case 'ILRI animal and human health program':
        $term = 'Animal and human health';
        break;
      case 'ILRI feed and forage development program':
        $term = "Feed and forage development";
        break;
      case 'ILRI impact at scale program':
        $term = "Impact at scale";
        break;
      case 'ILRI livestock genetics program':
        $term = "Livestock genetics";
        break;
      case 'ILRI policies, institutions and livelihoods program':
        $term = "Policies, institutions and livelihoods";
        break;
      case 'ILRI sustainable livestock systems program':
        $term = "Sustainable livestock systems";
        break;
      default:
        $term = $name;
        break;
    }

    return $term;

  }

  /**
   * Creates a new or returns an existing term for the target vocabulary.
   *
   * @param string $name
   *   The value.
   * @param Row $row
   *   The source row.
   * @param string $vocabulary
   *   The vocabulary name.
   *
   * @return Term
   *   The term.
   */
  protected function getTerm($name, Row $row, $vocabulary) {
    // Attempt to fetch an existing term.
    $properties = [];
    if (!empty($name)) {
      $properties['name'] = $name;
    }
    $vocabularies = \Drupal::entityQuery('taxonomy_vocabulary')->execute();
    if (isset($vocabularies[$vocabulary])) {
      $properties['vid'] = $vocabulary;
    }
    else {
      // Return NULL when filtering by a non-existing vocabulary.
      return NULL;
    }

    $terms = \Drupal::getContainer()->get('entity_type.manager')->getStorage('taxonomy_term')->loadByProperties($properties);
    $term = reset($terms);
    if (!empty($term)) {
      return $term;
    }

    if($this->configuration['create']) {
      $term = Term::create($properties);

      return $term;
    }

    return NULL;
  }

}
