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
 *   id = "map_cgiar_tags"
 * )
 */
class MapCGIARTags extends MapCGIARTerms {

  protected static $LABEL = "Tags";
  protected static $CONFIG = "cgspace_importer.processors.tags";
  protected static $VOCABULARY = "cgspace_tags";

}
