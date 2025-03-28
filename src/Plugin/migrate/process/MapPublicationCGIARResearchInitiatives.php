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
 *   id = "map_publication_cgiar_research_initiatives"
 * )
 */
class MapPublicationCGIARResearchInitiatives extends ProcessPluginBase {

  /**
   * The main function for the plugin, actually doing the data conversion.
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $fields = $row->getSource();

    if(is_array($fields)) {

      if (isset($value)) {
        $result = $this->mapCGIARResearchInitiatives($value);
        if(!is_null($result)) {
          return $result;
        }
        else {
          throw new MigrateException('Unable to map Initiative '.$value);
        }
      }
    }
    else {
      throw new MigrateException('invalid source');
    }
  }

  protected function mapCGIARResearchInitiatives($name) {

    switch($name) {

      case 'Sustainable Animal Productivity':
        //Sustainable Animal Productivity for Livelihoods, Nutrition and Gender Inclusion
        $term = 3618;
        break;
      case 'Livestock and Climate':
        //Livestock, Climate and System Resilience
        $term = 3619;
        break;
      case 'One Health':
        //Protecting Human Health Through a One Health Approach
        $term = 3620;
        break;
      case 'Resilient Cities':
        //Resilient Cities Through Sustainable Urban and Peri-Urban Agrifood Systems
        $term = 3621;
        break;
      case 'National Policies and Strategies':
        //National Policies and Strategies for Food, Land and Water Systems Transformation
        $term = 3622;
        break;
      case 'Mixed Farming Systems':
        //Sustainable Intensification of Mixed Farming Systems
        $term = 3623;
        break;
      case 'Diversification in East and Southern Africa':
        //Ukama Ustawi: Diversification for Resilient Agribusiness Ecosystems in East and Southern Africa
        $term = 3624;
        break;
      case 'Gender Equality':
        //Harnessing Gender and Social Equality for Resilience in Agrifood Systems
        $term = 3626;
        break;
      case 'Low-Emission Food Systems':
        //Mitigate+: Research for Low-Emission Food Systems
        $term = 3627;
        break;
      case 'NEXUS Gains':
        //NEXUS Gains: Realizing Multiple Benefits Across Water, Energy, Food and Ecosystems
        $term = 3628;
        break;
      case 'Plant Health':
        //Plant Health and Rapid Response to Protect Food Security and Livelihoods
        $term = 3630;
        break;
      case 'Foresight':
        //Foresight and Metrics to Accelerate Inclusive and Sustainable Agrifood System Transformation
        $term = 3631;
        break;
      case 'Digital Innovation':
        //Harnessing Digital Technologies for Timely Decision-Making Across Food, Land and Water Systems
        $term = 3632;
        break;
      case 'Excellence in Agronomy':
        //Excellence in Agronomy for Sustainable Intensification and Climate Change Adaptation
        $term = 3634;
        break;
      case 'Genebanks':
        //Protecting Human Health Through a One Health Approach
        $term = 3625;
        break;
      case 'Transforming Agrifood Systems in South Asia':
        //Transforming Agrifood Systems in South Asia
        $term = 3633;
        break;
      default:
        $term = null;
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
