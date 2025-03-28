<?php

namespace Drupal\cgspace_importer\Commands;

use Drupal\cgspace_importer\Plugin\cgspace_importer\CGSpaceProxy;
use Drush\Commands\DrushCommands;
use Drupal\cgspace_importer\Controller\SyncContentController;
/**
 * A Drush commandfile.
 */
class SyncContentCommand extends DrushCommands {


  public function __construct()
  {
    $this->endpoint = \Drupal::config('cgspace_importer.settings')->get('endpoint');
    $this->collections = \Drupal::config('cgspace_importer.settings.collections')->get();
    $this->proxy = new CGSpaceProxy($this->endpoint);
  }
  /**
   * Echos back hello with the argument provided.
   *
   *   Argument provided to the drush command.
   *
   * @command cgspace_importer:sync
   * @aliases cgspace-sync
   * @usage cgspace_importer:sync
   */
  public function sync() {
    // Create batch which collects all the specified queue items and process them one after another
    $batch = array(
      'title' => t("Syncing Publications from CGSpace"),
      'operations' => array(),
      'finished' => 'Drupal\cgspace_importer\BatchService::batchFinished',
    );

    $items = [];
    foreach($this->collections as $collection_key => $collection_value) {
      if($collection_value) {

        $number_items = $this->proxy->getCollectionNumberItems($collection_key);

        foreach ($this->proxy->getItems($collection_key, $number_items) as $item) {
          $items[] = $item;
        }
      }
    }

    $items = array_unique($items);
    // Count number of the items in this queue, and create enough batch operations
    foreach($items as $item) {
      // Create batch operations
      $batch['operations'][] = array('Drupal\cgspace_importer\BatchService::batchProcess', array($item));
    }

    // Adds the batch sets
    batch_set($batch);
    // Process the batch and after redirect to the frontpage
    drush_backend_batch_process();

  }

}
