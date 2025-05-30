<?php

namespace Drupal\cgspace_importer_ldap\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;

/**
 * This plugin tries to find a term match on a given vocabulary from a string.
 *
 * @MigrateProcessPlugin(
 *   id = "map_cgiar_authors_to_users"
 * )
 */
class MapCGIARAuthorsToUsers extends ProcessPluginBase {

  /**
   * The main function for the plugin, actually doing the data conversion.
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {


    if(isset($value)) {

      $query = \Drupal::database()->select('user__field_usr_cg_full_name', 'u');
      $query->addField('u', 'entity_id');
      $query->condition('u.field_usr_cg_full_name_value', trim($value));
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
}
