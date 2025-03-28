<?php

namespace Drupal\cgspace_importer\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Language\LanguageInterface;
use Drupal\user\Entity\User;

/**
 * This plugin tries to find a term match on a given vocabulary from a string.
 *
 * @MigrateProcessPlugin(
 *   id = "map_authors_to_users"
 * )
 */
class MapAuthorsToUsers extends ProcessPluginBase {

  /**
   * The main function for the plugin, actually doing the data conversion.
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {


    if(isset($value)) {

      $query = \Drupal::database()->select('user__field_usr_cgspace_full_name', 'u');
      $query->addField('u', 'entity_id');
      $query->condition('u.field_usr_cgspace_full_name_value', trim($value));
      $users = $query->execute()->fetchAll();
      if(!empty($users)) {
        $user = reset($users);
        //check that is an user entity
        $entity = \Drupal::entityTypeManager()->getStorage('user')->load($user->entity_id);
        if($entity instanceof User) {
          return $user->entity_id;
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
