<?php

namespace Drupal\cgspace_importer\Commands;

use Drupal\cgspace_importer\BatchNodeService;
use Drupal\cgspace_importer\CGSpaceProxy;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\DatabaseException;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drush\Commands\DrushCommands;
use GuzzleHttp\ClientInterface;

/**
 * A Drush commandfile.
 */
class CGSpaceImporterCommands extends DrushCommands {

  protected ImmutableConfig $collections;
  protected ImmutableConfig $configuration;
  protected ImmutableConfig $settings;
  protected CGSpaceProxy $proxy;
  protected LoggerChannelInterface $loggerChannel;
  private BatchNodeService $nodeService;


  public function __construct(ConfigFactoryInterface $configFactory, ClientInterface $httpClient, LoggerChannelFactoryInterface $loggerChannelFactory) {
    parent::__construct();
    $this->configuration = $configFactory->get('cgspace_importer.settings.general');
    $this->collections = $configFactory->get('cgspace_importer.settings.collections');
    $this->settings = $configFactory->get('cgspace_importer.mappings');
    $this->proxy = new CGSpaceProxy($this->configuration->get('endpoint'), $configFactory, $httpClient, $loggerChannelFactory);
    $this->nodeService = new BatchNodeService();

    $loggerFactory = \Drupal::service('logger.factory');
    $this->loggerChannel = $loggerFactory->get('cgspace_importer');
  }

  /**
   * Create nodes from CGSpace items on sitemap.xml
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
  public function create(array $options = ['all' => false]):void {

    try {

      $batch = new BatchBuilder();
      $batch->setTitle('Creating Nodes from CGSpace Items.')
        ->setFinishCallback([$this->nodeService, 'batchUpdateFinished'])
        ->setInitMessage('Initializing...')
        ->setProgressMessage('Processing...')
        ->setErrorMessage('An error occurred during processing.');

      if($options['all'] !== false) {
        //save the full import has run to set default option on CGSpaceSync Form page
        \Drupal::state()->set('cgspace_importer.full_imported', true);
        //load items from sitemap
        $this->loggerChannel->notice("Creating Nodes from CGSpace Sitemap Index.");

        $items = $this->proxy->getItemsFromSitemap();

        // Create batch which collects all the specified queue items and process them one after another
        $chunk_size = $this->configuration->get('node_chunk_size');
        $current = 0;
        foreach (array_chunk($items, $chunk_size) as $chunk) {
          // Create batch operations
          $batch->addOperation([$this->nodeService, 'batchCreateFromSitemapProcess'], [$chunk, $current, ceil(count($items) / $chunk_size)]);
          $current++;
        }
      }
      else {
        $this->loggerChannel->notice("Creating Nodes from CGSpace selected Communities and Collections.");
        //add List operations and count number of items to process
        $num_items = 0;
        foreach ($this->collections->get() as $community => $collections) {

          foreach ($collections as $collection) {
            $collection_num_items = $this->proxy->countCollectionItems($collection, '');
            $num_items += $collection_num_items;
            $pages = ceil($collection_num_items / $this->configuration->get('page_size'));
            for ($page = 0; $page < $pages; $page++) {
              //get the number of pages
              $batch->addOperation([$this->nodeService, 'batchListProcess'], [$collection, '', $page]);
            }
          }
        }

        for($i=0; $i<$num_items; $i++) {
          // Create batch operations
          $batch->addOperation([$this->nodeService, 'batchLoadProcess'], [$i, $num_items]);
          $batch->addOperation([$this->nodeService, 'batchUpdateProcess'], [$i, $num_items]);
        }

      }

      // Adds the batch sets
      batch_set($batch->toArray());
      // Process the batch and after redirect to the frontpage
      drush_backend_batch_process();
    }  catch(\Exception $exception) {
      $this->loggerChannel->error("Error creating the Batch process: @message", ["@message" => $exception->getMessage()]);
    }
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
   * @throws \Exception
   *
   * @default $options []
   */
  public function update(string $last_modified = '', array $options = ['all' => false]):void {

    try {
      //TODO: check processors have been configured
      if(empty($last_modified)) {
        $last_run = \Drupal::state()->get('cgspace_importer.last_run');
      }
      else {
        //validate argument
        if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $last_modified)) {
          throw new \Exception("Invalid date argument! – usage: drush cgspace_importer:update YYYY-MM-DD");
        }

        $date = new \DateTimeImmutable($last_modified);
        $last_run = \DateTime::createFromImmutable($date)->format('Y-m-d\Th:i:s\Z');
      }

      $query = "lastModified:[$last_run TO *]";

      $this->loggerChannel->notice("Updating Items from CGSpace – Since: $last_run");

      // Create batch which collects all the specified queue items and process them one after another
      $batch = new BatchBuilder();
      $batch->setTitle('Syncing Items from CGSpace.')
        ->setFinishCallback([$this->nodeService, 'batchUpdateFinished'])
        ->setInitMessage('Initializing...')
        ->setProgressMessage('Processing...')
        ->setErrorMessage('An error occurred during processing.');

      $num_items = 0;

      if($options['all'] !== false) {
        $num_items = $this->proxy->countCollectionItems('', $query);
        $pages = ceil($num_items / $this->configuration->get('page_size'));
        for ($page = 0; $page < $pages; $page++) {
          //get the number of pages
          $batch->addOperation([$this->nodeService, 'batchListProcess'], ['', $query, $page]);
        }
      }

      else {

        foreach ($this->collections->get() as $community => $collections) {
          foreach ($collections as $collection) {
            //if collection configuration has changed and we have collections added
            //run the BatchListProcess without query (fetch all the collection) otherwise use the lastModified query (fetch the collection since the last_run date)
            $collections_added = \Drupal::state()->get('cgspace_importer.collections_added');

            if (!is_null($collections_added) && in_array($collection, $collections_added)) {
              $collection_num_items = $this->proxy->countCollectionItems($collection, '');
              $pages = ceil($collection_num_items / $this->configuration->get('page_size'));
              $num_items += $collection_num_items;
              for ($page = 0; $page < $pages; $page++) {
                //get the number of pages
                $batch->addOperation([$this->nodeService, 'batchListProcess'], [$collection, '', $page]);
              }

              //remove the current collection from "collections_added" state variable
              $index = array_search($collection, $collections_added);
              if ($index !== false) {
                unset($collections_added[$index]);
                $collections_added = array_values($collections_added);
              }

              \Drupal::state()->set('cgspace_importer.collections_added', $collections_added);
            } else {
              $collection_num_items = $this->proxy->countCollectionItems($collection, $query);
              $num_items += $collection_num_items;
              $pages = ceil($collection_num_items / $this->configuration->get('page_size'));
              for ($page = 0; $page < $pages; $page++) {
                //get the number of pages
                $batch->addOperation([$this->nodeService, 'batchListProcess'], [$collection, $query, $page]);
              }
            }
          }
        }
      }

      for($i=0; $i<$num_items; $i++) {
        // Create batch operations
        $batch->addOperation([$this->nodeService, 'batchLoadProcess'], [$i, $num_items]);
        $batch->addOperation([$this->nodeService, 'batchUpdateProcess'], [$i, $num_items]);
      }

      // Adds the batch sets
      batch_set($batch->toArray());
      // Process the batch and after redirect to the frontpage
      if(PHP_SAPI === 'cli') {
        drush_backend_batch_process();
      }
    }
    catch(\Exception $exception) {
      $this->loggerChannel->error("Error creating the Batch process: @message", ["@message" => $exception->getMessage()]);
    }

  }


  /**
   * Delete Nodes depending on the selected collections on the configuration page.
   *
   *   Argument provided to the drush command.
   *
   * @command cgspace_importer:delete
   * @aliases cgspace-delete
   * @usage cgspace_importer:delete
   * @param array $options An options that takes multiple values
   * @throws \Exception
   *
   * @default $options []
   */
  public function delete(array $options = ['all' => false]):void {

    //get full list of field_cg_uuid for published cgspace_publications
    try {
      // Create batch which collects all the specified queue items and process them one after another
      $batch = new BatchBuilder();
      $batch->setTitle('Deleting CGSpace Publications Nodes.')
        ->setFinishCallback([$this->nodeService, 'batchFinished'])
        ->setInitMessage('Initializing...')
        ->setProgressMessage('Processing...')
        ->setErrorMessage('An error occurred during processing.');

      //load all uuids from backend configuration

      if($options['all'] !== false) {
        $this->loggerChannel->notice("Deleting Nodes according to CGSpace sitemap Index.");
        $batch->addOperation([$this->nodeService, 'batchListFromSitemapProcess']);
      }
      else {
        $this->loggerChannel->notice("Deleting Nodes according to CGSpace Selected collections.");
        foreach ($this->collections->get() as $community => $collections) {
          foreach ($collections as $collection) {
            $collection_num_items = $this->proxy->countCollectionItems($collection, '');
            $pages = ceil($collection_num_items / $this->configuration->get('page_size'));
            for ($page = 0; $page < $pages; $page++) {
              //get the number of pages
              $batch->addOperation([$this->nodeService, 'batchListProcess'], [$collection, '', $page]);
            }
          }
        }
      }

      $uuids = $this->getPublicationNodesUUIDs();
      $chunk_size = $this->configuration->get('node_chunk_size');
      // Create batch which collects all the specified queue items and process them one after another
      $current = 0;
      foreach (array_chunk($uuids, $chunk_size) as $chunk) {
        // Create batch operations
        $batch->addOperation([$this->nodeService, 'batchDeleteProcess'], [$chunk, $current, ceil(count($uuids) / $chunk_size)]);
        $current++;
      }

      // Adds the batch sets
      batch_set($batch->toArray());
      // Process the batch and after redirect to the frontpage
      drush_backend_batch_process();
    }
    catch (\Exception $ex) {
      $this->loggerChannel->error("Error creating the Batch process: @message", ["@message" => $ex->getMessage()]);
    }
  }


  /**
   * Return an array of UUIDs loaded from node field_cg_uuid database table
   * @return array
   */
  private function getPublicationNodesUUIDs():array {
    try {
      $connection = \Drupal::database();

      $query = $connection->select('node__'.$this->settings->get('uuid_field'), 'f');
      $query->join('node_field_data', 'n', 'n.nid = f.entity_id');
      $query->fields('f', [$this->settings->get('uuid_field').'_value']);
      $query->condition('n.type', $this->settings->get('content_type'));
      $query->condition('n.status', 1);

      $uuids = $query->execute()->fetchCol();

      return $uuids;
    }
    catch (DatabaseException $ex) {
      $this->loggerChannel->error("Error connection to database: @message", ["@message" => $ex->getMessage()]);
    }
    return [];
  }
}
