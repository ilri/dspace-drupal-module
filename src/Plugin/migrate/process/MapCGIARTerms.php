<?php

namespace Drupal\cgspace_importer\Plugin\migrate\process;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\taxonomy\Entity\Term;


class MapCGIARTerms extends ProcessPluginBase {

  protected static $LABEL;
  protected static $CONFIG;
  protected static $VOCABULARY;

  /**
   * The main function for the plugin, actually doing the data conversion.
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $fields = $row->getSource();

    if(is_array($fields)) {

      if (isset($value)) {
        $term = $this->getTerm($value);
        if($term instanceof Term) {
          return $term->id();
        }
        else {
          throw new MigrateException('Unable to map ' . static::$LABEL .' '.$value);
        }
      }
    }
    else {
      throw new MigrateException('invalid source');
    }
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
  protected function getTerm($name) {
    //get configuration
    $config = \Drupal::configFactory()->getEditable(static::$CONFIG);

    if (empty($name)) {
      return NULL;
    }
    // Attempt to fetch an existing term.
    $properties = [];

    //create term on research initiatives vocabulary
    if((string) $config->get('create') == '1') {
      $properties['vid'] = static::$VOCABULARY;
      $properties['name'] = $name;

      //check if the term already exists and return it
      $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties($properties);
      $term = reset($terms);
      if (empty($term)) {
        //if it doesn't exist create it
        $term = Term::create($properties);
        try {
          $term->save();
        }
        catch (EntityStorageException $exception) {

        }
      }

      return $term;
    }
    //try to map term on existing vocabulary
    else {
      $properties['name'] = $name;
      $properties['vid'] = $config->get('vocabulary');

      $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties($properties);
      $term = reset($terms);
      if (!empty($term)) {
        return $term;
      }
    }

    return NULL;
  }

}
