<?php

namespace Drupal\cgspace_importer;

use Drupal\Component\Plugin\Discovery\CachedDiscoveryInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\webform\WebformSubmissionInterface;

interface NodeImporterProcessorManagerInterface extends PluginManagerInterface, CachedDiscoveryInterface {

}

