<?php

namespace Drupal\cgspace_importer\Plugin\migrate\process;

/**
 * This plugin tries to find a term match on a given vocabulary from a string.
 *
 * @MigrateProcessPlugin(
 *   id = "map_cgiar_research_initiatives"
 * )
 */
class MapCGIARResearchInitiatives extends MapCGIARTerms {

  protected static $LABEL = "Research Initiative";
  protected static $CONFIG = "cgspace_importer.processors.research_initiatives";
  protected static $VOCABULARY = "cgspace_research_initiatives";

}
