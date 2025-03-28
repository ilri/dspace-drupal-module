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
 *   id = "process_authored_on"
 * )
 */
class ProcessAuthoredOn extends ProcessPluginBase {

  /**
   * The main function for the plugin, actually doing the data conversion.
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {

    if (isset($value)) {

      if(is_string($value)) {
        $date_parts = explode('-', $value);

        if (count($date_parts) === 1) {
          //we have only year
          $value .= '-01-01';
        }
        if (count($date_parts) === 2) {
          //we have year and month
          $value .= '-01';
        }

        $date = \DateTimeImmutable::createFromFormat("Y-m-d", $value);

        if($date !== false) {
          if ($destination_property === 'created') {
            $format = 'U';
          } else {
            $format = 'Y-m-d';
          }
          $result = $date->format($format);
        }
        else {
          throw new MigrateException('Unable to convert $value to a valid timestamp');
        }
      }
      else {
        throw new MigrateException('Unable to convert $value to a valid timestamp');
      }
    }
    else {
      throw new MigrateException('invalid source');
    }

    return $result;

  }


}
