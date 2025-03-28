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
 *   id = "map_publication_regions"
 * )
 */
class MapPublicationRegions extends ProcessPluginBase {

  /**
   * The main function for the plugin, actually doing the data conversion.
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $fields = $row->getSource();

    if(is_array($fields)) {

      if (isset($value)) {
        $termName = $this->mapRegions($value);
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

  protected function mapRegions($name) {

    switch($name) {
      //East Africa
      case 'Djibouti':
      case 'Eritrea':
      //case 'Ethiopia':
      //case 'Kenya':
      case 'Rwanda':
      case 'Seychelles':
      case 'Somalia':
      //case 'Uganda':
      //case 'Tanzania':
      case 'Burundi':
      case 'East Africa':
      //Southern Africa
      case 'Angola':
      case 'Botswana':
      case 'Lesotho':
      case 'Madagascar':
      case 'Malawi':
      case 'Mauritius':
      case 'Mozambique':
      case 'Namibia':
      case 'Swaziland':
      case 'Zambia':
      //case 'Zimbabwe':
      case 'South Africa':
        $term = 'Eastern and Southern Africa';
        break;
      case 'Benin':
      //case 'Burkina Faso':
      case 'Cape Verde':
      case 'Côte d\'Ivoire':
      case 'Gambia':
      case 'Ghana':
      case 'Guinea':
      case 'Guinea-Bissau':
      case 'Liberia':
      //case 'Mali':
      case 'Mauritania':
      case 'Niger':
      //case 'Nigeria':
      //case 'Senegal':
      case 'Sierra Leone':
      case 'Togo':
        $term = 'West Africa';
        break;
      case 'Afghanistan':
      case 'Bangladesh':
      case 'Bhutan':
      //case 'India':
      case 'Maldives':
      case 'Nepal':
      //case 'Pakistan':
      case 'Sri Lanka':
        $term = "South Asia";
        break;
      case 'Cambodia':
      case 'Indonesia':
      case 'Laos':
      case 'Malaysia':
      case 'Myanmar':
      case 'Philippines':
      case 'Thailand':
      case 'Timor-Leste':
      //case 'Vietnam':
        $term = "East and Southeast Asia";
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
