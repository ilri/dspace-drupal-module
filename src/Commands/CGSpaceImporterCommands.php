<?php

namespace Drupal\cgspace_importer\Commands;

use Drupal\cgspace_importer\Plugin\cgspace_importer\CGSpaceProxy;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drush\Commands\DrushCommands;
use Drupal\Core\File\FileExists;
use GuzzleHttp\ClientInterface;
/**
 * A Drush commandfile.
 */
class CGSpaceImporterCommands extends DrushCommands {

  protected $endpoint;
  protected $collections;
  protected $proxy;

  public const JSON_DATABASE_FILE = 'public://cgspace-proxy.json';

  public function __construct(ConfigFactoryInterface $configFactory, ClientInterface $httpClient) {
    parent::__construct();
    $this->endpoint = $configFactory->get('cgspace_importer.settings')->get('endpoint');
    $this->collections = $configFactory->get('cgspace_importer.settings.collections')->get();
    $this->proxy = new CGSpaceProxy($this->endpoint, $configFactory, $httpClient);
  }

  /**
   * Create a JSON Database to be used on first run by migration framework for the first import
   *
   *   Argument provided to the drush command.
   *
   * @command cgspace_importer:create
   * @aliases cgspace-create
   * @usage cgspace_importer:create
   * @throws \Exception
   *
   * @default $options []
   */
  public function create() {

    //check that json database exists and if exists alert the user
    if(file_exists(self::JSON_DATABASE_FILE)) {
      if(!$this->io()->confirm("The JSon Database file with CGSpace data already exists! Are you sure you want to overwrite?.")) {
        $this->logger()->notice("Operation cancelled.");
        exit();
      }
      $this->logger()->notice("Overwriting JSon database...");
    }

    $this->logger()->notice(t("\033[1mSyncing Items from CGSpace.\033[0m"));

    // Create batch which collects all the specified queue items and process them one after another
    $batch = array(
      'title' => t("Syncing Items from CGSpace"),
      'operations' => array(),
      'finished' => 'Drupal\cgspace_importer\BatchService::batchFinished',
    );

    $items = [];
    foreach ($this->collections as $collection_key => $collection_value) {
      if($collection_value) {
        $collectionItems = $this->proxy->getAllItems($collection_key);
        foreach ($collectionItems as $item) {
          $items[] = $item;
        }

        $this->logger()->notice(
          t("\033[1m@items items\033[0m for Collection @collection", [
            '@items' => count($collectionItems),
            '@collection' => $collection_key
          ])
        );
      }
    }

    $items = array_unique($items);

    // Count number of the items in this queue, and create enough batch operations
    foreach ($items as $item) {
      // Create batch operations
      $batch['operations'][] = array('Drupal\cgspace_importer\BatchService::batchProcess', array($item));
    }

    // Adds the batch sets
    batch_set($batch);
    // Process the batch and after redirect to the frontpage
    drush_backend_batch_process();
  }

  /**
   * Updates database since a passed date or the last_run date.
   *
   *   Argument provided to the drush command.
   *
   * @command cgspace_importer:update
   * @aliases cgspace-update
   * @usage cgspace_importer:update
   * @param string $last_modified optional date to update database with items since last modified (Format: YYYY-MM-DD)
   * @param array $options An options that takes multiple values
   * @options dry-run to get only the preview of what will be done to the database
   * @throws \Exception
   *
   * @default $options []
   */
  public function update(string $last_modified = '', array $options = ['dry-run' => false]) {

    //TODO: check the JSON database exists otherwise warn user that doesn't make sense

    if(!file_exists(self::JSON_DATABASE_FILE)) {
      $this->logger()->error("The JSon Database file with CGSpace data has not been created yet!\nPlease ensure you correctly configured your endpoint at /admin/config/cgspace/settings and run cgspace-create first.");
      exit();
    }

    if(empty($last_modified)) {
      //TODO: get date from configuration and define behaviour
      // if the last run is empty take it from last cgspace_importer migration run
      // otherwise save a last run each time the batch process is completed
      $last_modified = '2025-06-01';
    }

    $date = new \DateTimeImmutable($last_modified);
    $last_run = \DateTime::createFromImmutable( $date )->format('Y-m-d\Th:i:s\Z');


    $this->logger()->notice("\033[1mUpdating Items from CGSpace\033[0m – Last run on: \033[1;32m$last_run\033[0m");
    // Create batch which collects all the specified queue items and process them one after another
    $batch = array(
      'title' => t("Syncing Items from CGSpace\nLastRun:$last_run"),
      'operations' => array(),
      'finished' => 'Drupal\cgspace_importer\BatchService::updateFinished',
    );

    $items = [];

    foreach ($this->collections as $collection_key => $collection_value) {
      if ($collection_value) {

        //get the last changed items since last run for each collection we have
        //lastModified:[2025-06-01T00:00:00Z TO *]
        $query = "lastModified:[$last_run TO *]";
        $collectionItems = $this->proxy->getUpdatedItems($collection_key, $query);
        foreach ( $collectionItems as $item) {
          $items[] = $item;
        }

        $this->logger()->notice(
          t("\033[1m@items items\033[0m have been created/updated on Collection @collection", [
            '@items' => count($collectionItems),
            '@collection' => $collection_key
          ])
        );

      }
    }

    $items = array_unique($items);

    if(!$options['dry-run']) {
      // Count number of the items in this queue, and create enough batch operations
      foreach ($items as $item) {
        // Create batch operations
        $batch['operations'][] = array('Drupal\cgspace_importer\BatchService::batchProcess', array($item));
      }

      // Adds the batch sets
      batch_set($batch);
      // Process the batch and after redirect to the frontpage
      drush_backend_batch_process();
    }
  }

}
