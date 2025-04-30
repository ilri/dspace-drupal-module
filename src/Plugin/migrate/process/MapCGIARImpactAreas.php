<?php

namespace Drupal\cgspace_importer\Plugin\migrate\process;

use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\taxonomy\Entity\Term;

/**
 * This plugin tries to find a term match on a given vocabulary from a string.
 *
 * @MigrateProcessPlugin(
 *   id = "map_cgiar_impact_areas"
 * )
 */
class MapCGIARImpactAreas extends MapCGIARTerms {

  protected static $LABEL = "Impact areas";
  protected static $CONFIG = "cgspace_importer.processors.impact_areas";
  protected static $VOCABULARY = "cgspace_impact_areas";

  /**
   * The main function for the plugin, actually doing the data conversion.
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $fields = $row->getSource();

    if(is_array($fields)) {

      if (isset($value)) {
        $term_name = $this->mapImpactAreas($value);
        if($term_name !== '') {
          $term = $this->getTerm($term_name);
          if($term instanceof Term) {
            return $term->id();
          }
        }
      }
    }
    else {
      throw new MigrateException('invalid source');
    }
  }

  private function mapImpactAreas($name) {
    $name = strtolower($name);
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
        $term = '';
        break;
    }

    return $term;

  }
}
