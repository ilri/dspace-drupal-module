<?php

namespace Drupal\cgspace_importer;

interface NodeImporterProcessorInterface {

  /**
   * Process the source value from CGSpace transforming data
   * on item before returning ready to be written on target Drupal field name
   *
   * @param string $source
   * The CGSpace field name to be processed
   * @param string $target
   * The Drupal field name where data will be saved
   * @param array $item
   * The CGSpace full item
   * @return array
   * The array with data ready to be saved on Drupal Database
   */
  public function process(string $source, string $target, array $item): array;

}

