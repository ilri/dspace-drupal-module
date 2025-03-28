<?php
/**
 * @file
 * Contains \Drupal\cgspace_importer\Controller\SyncContentController.
 */

namespace Drupal\cgspace_importer\Controller;

use Drupal\cgspace_importer\Plugin\cgspace_importer\CGSpaceProxy;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\File\FileSystemInterface;

use Drupal\migrate_tools\MigrateBatchExecutable;
use Drupal\migrate\MigrateMessage;

use Symfony\Component\HttpFoundation\RedirectResponse;


class SyncContentController extends ControllerBase implements ContainerInjectionInterface{


  private $endpoint;
  private $collections;
  private $proxy;

  public function __construct()
  {
    $this->endpoint = \Drupal::config('cgspace_importer.settings')->get('endpoint');
    $this->collections = \Drupal::config('cgspace_importer.settings.collections')->get();
    $this->proxy = new CGSpaceProxy($this->endpoint);
  }

  /**
   * Process all queue items with batch
   */
  public function sync() {

    // Create batch which collects all the specified queue items and process them one after another
    $batch = array(
      'title' => t("Syncing Publications Nodes from CGSpace"),
      'operations' => array(),
      'finished' => 'Drupal\cgspace_importer\BatchService::batchFinished',
    );

    $items = [];
    foreach($this->collections as $collection_key => $collection_value) {
      if($collection_value) {
        foreach ($this->proxy->getItems($collection_key) as $item) {
          $items[] = $item;
        }
      }
    }

    // Count number of the items in this queue, and create enough batch operations
    foreach($items as $item) {
      // Create batch operations
      $batch['operations'][] = array('Drupal\cgspace_importer\BatchService::batchProcess', array($item));
    }

    // Adds the batch sets
    batch_set($batch);
    // Process the batch and after redirect to the frontpage
    return batch_process('<front>');

  }

  public function syncPublications() {
    $migration_id = 'cgspace_importer_publication';
    $migration = \Drupal::service('plugin.manager.migration')->createInstance($migration_id);

    $executable = new MigrateBatchExecutable($migration, new MigrateMessage(), ['update' => true]);
    $executable->batchImport();

    return batch_process('<front>');

  }




}
