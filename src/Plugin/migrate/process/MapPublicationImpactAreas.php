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
 *   id = "map_publication_impact_areas"
 * )
 */
class MapPublicationImpactAreas extends ProcessPluginBase {

  /**
   * The main function for the plugin, actually doing the data conversion.
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $fields = $row->getSource();

    if(is_array($fields)) {

      if (isset($value)) {
        $termName = $this->mapImpactAreas(strtolower($value[0]));
      }
    }
    else {
      throw new MigrateException('invalid source');
    }
    if(!empty($termName)) {
      // Getting a term by lookup (if it exists), or creating one
      if (!empty($term = $this->getTerm($termName, $row, $this->configuration['vocabulary']))) {
        $term->save();
        // Yes, all we need is ID.
        return $term->id();
      };
    }
  }

  protected function mapImpactAreas($name) {

    switch($name) {
      case 'animal health':
      case 'disease control':
      case 'epidemiology':
      case 'food safety':
      case 'food security':
      case 'food systems':
      case 'one health':
      case 'human health':
      case 'nutrition':
      case 'zoonotic diseases':
        $term = 'Nutrition, health and food security';
        break;
      case 'gender':
      case 'women':
        $term = 'Gender equality, youth and social inclusion';
        break;
      case 'climate change':
      case 'GHG emissions':
      case 'resilience':
        $term = 'Climate adaptation and mitigation';
        break;
      case 'agri-health':
      case 'biodiversity':
      case 'environment':
      case 'pests':
      case 'wildlife':
        $term = 'Environmental health and biodiversity';
        break;
      case 'consumption':
      case 'genetics':
      case 'innovation systems':
      case 'livelihoods':
      case 'policy':
      case 'pro-poor livestock':
      case 'scaling':
      case 'trade':
      case 'vulnerability':
        $term = 'Poverty reduction, livelihoods and jobs';
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

    return null;
  }

}
