<?php

namespace Drupal\cgspace_importer\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a cgspace_importer Process Plugin.
 *
 * Plugin Namespace: Plugin\NodeImporterProcessors.
 *
 * @Annotation
 */
class NodeImporterProcessor extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * URL to the element's API documentation.
   *
   * @var string
   */
  public $api;


  /**
   * The human-readable name of the processor.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * The human-readable description of the processor
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;


}
