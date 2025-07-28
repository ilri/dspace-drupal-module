<?php

namespace Drupal\cgspace_importer;

use Drupal\webform\WebformSubmissionInterface;

interface NodeImporterProcessorInterface {

  public function process(string $source, string $target, array $item): array;

}

