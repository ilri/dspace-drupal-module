<?php

namespace Drupal\cgspace_importer\Commands;

use Drupal\cgspace_importer\BatchNodeService;
use Drupal\cgspace_importer\CGSpaceProxy;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\DatabaseException;
use Drush\Commands\DrushCommands;
use GuzzleHttp\ClientInterface;
/**
 * A Drush commandfile.
 */
class CGSpaceImporterCommands extends DrushCommands {

  protected $collections;
  protected $proxy;
  protected $configuration;


  public function __construct(ConfigFactoryInterface $configFactory, ClientInterface $httpClient) {
    parent::__construct();
    $this->configuration = $configFactory->get('cgspace_importer.settings.general')->get();
    $this->collections = $configFactory->get('cgspace_importer.settings.collections')->get();
    $this->proxy = new CGSpaceProxy($this->configuration['endpoint'], $configFactory, $httpClient);
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
  public function create(array $options = ['all' => false]) {

    //TODO: check there is at least one collection selected in configuration
    try {
      $this->logger()->notice(t("\033[1mCreating Nodes from CGSpace Items.\033[0m"));

      $batch = new BatchBuilder();
      $batch->setTitle('Creating Nodes from CGSpace Items.')
        ->setFinishCallback([BatchNodeService::class, 'batchCreateFinished'])
        ->setInitMessage('Initializing...')
        ->setProgressMessage('Processing...')
        ->setErrorMessage('An error occurred during processing.');

      if($options['all'] !== false) {
        //load items from sitemap
        $items = $this->proxy->getItemsFromSitemap();

        // Create batch which collects all the specified queue items and process them one after another
        $chunk_size = $this->configuration['node_chunk_size'];
        $current = 0;
        foreach (array_chunk($items, $chunk_size) as $chunk) {
          // Create batch operations
          $batch->addOperation([BatchNodeService::class, 'batchCreateFromSitemapProcess'], [$chunk, $current, ceil(count($items) / $chunk_size)]);
          $current++;
        }
      }
      else {
        //add List operations and count number of items to process
        $num_items = 0;
        foreach ($this->collections as $community => $collections) {
          $collection_num_items = 0;
          foreach ($collections as $collection) {
            $collection_num_items = $this->proxy->countCollectionItems($collection, '');
            $pages = ceil($collection_num_items / $this->configuration['page_size']);
            for ($page = 0; $page < $pages; $page++) {
              //get the number of pages
              $batch->addOperation([BatchNodeService::class, 'batchListProcess'], [$collection, '', $page]);
            }
          }
          $num_items += $collection_num_items;
        }

        for($i=0; $i<$num_items; $i++) {
          // Create batch operations
          $batch->addOperation([BatchNodeService::class, 'batchLoadProcess'], [$i, $num_items]);
          $batch->addOperation([BatchNodeService::class, 'batchUpdateProcess'], [$i, $num_items]);
        }

      }

      // Adds the batch sets
      batch_set($batch->toArray());
      // Process the batch and after redirect to the frontpage
      drush_backend_batch_process();
    }  catch(\Exception $exception) {
      $this->logger()->error(t("Error creating the Batch process: @message", ["@message" => $exception->getMessage()]));
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
  public function update(string $last_modified = '', array $options = ['all' => false]) {

    try {
      //TODO: check processors have been configured
      //TODO: check there is at least one collection selected in configuration
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

      $this->logger()->notice("\033[1mUpdating Items from CGSpace\033[0m – Since: \033[1;32m$last_run\033[0m");

      // Create batch which collects all the specified queue items and process them one after another
      $batch = new BatchBuilder();
      $batch->setTitle('Syncing Items from CGSpace.')
        ->setFinishCallback([BatchNodeService::class, 'batchUpdateFinished'])
        ->setInitMessage('Initializing...')
        ->setProgressMessage('Processing...')
        ->setErrorMessage('An error occurred during processing.');

      $num_items = 0;

      if($options['all'] !== false) {
        $num_items = $this->proxy->countCollectionItems('', $query);
        $pages = ceil($num_items / $this->configuration['page_size']);
        for ($page = 0; $page < $pages; $page++) {
          //get the number of pages
          $batch->addOperation([BatchNodeService::class, 'batchListProcess'], ['', $query, $page]);
        }
      }

      else {

        foreach ($this->collections as $community => $collections) {
          foreach ($collections as $collection) {
            //if collection configuration has changed and we have collections added
            //run the BatchListProcess without query (fetch all the collection) otherwise use the lastModified query (fetch the collection since the last_run date)
            $collections_added = \Drupal::state()->get('cgspace_importer.collections_added');

            if (in_array($collection, $collections_added)) {
              $collection_num_items = $this->proxy->countCollectionItems($collection, '');
              $pages = ceil($collection_num_items / $this->configuration['page_size']);
              for ($page = 0; $page < $pages; $page++) {
                //get the number of pages
                $batch->addOperation([BatchNodeService::class, 'batchListProcess'], [$collection, '', $page]);
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
              $pages = ceil($collection_num_items / $this->configuration['page_size']);
              for ($page = 0; $page < $pages; $page++) {
                //get the number of pages
                $batch->addOperation([BatchNodeService::class, 'batchListProcess'], [$collection, $query, $page]);
              }
            }
            $num_items += $collection_num_items;
          }
        }
      }

      for($i=0; $i<$num_items; $i++) {
        // Create batch operations
        $batch->addOperation([BatchNodeService::class, 'batchLoadProcess'], [$i, $num_items]);
        $batch->addOperation([BatchNodeService::class, 'batchUpdateProcess'], [$i, $num_items]);
      }

      // Adds the batch sets
      batch_set($batch->toArray());
      // Process the batch and after redirect to the frontpage
      drush_backend_batch_process();
    }
    catch(\Exception $exception) {
      $this->logger()->error(t("Error creating the Batch process: @message", ["@message" => $exception->getMessage()]));
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
  public function delete(array $options = ['all' => false]) {

    //get full list of field_cg_uuid for published cgspace_publications
    try {
      // Create batch which collects all the specified queue items and process them one after another
      $batch = new BatchBuilder();
      $batch->setTitle('Deleting CGSpace Publications Nodes.')
        ->setFinishCallback([BatchNodeService::class, 'batchFinished'])
        ->setInitMessage('Initializing...')
        ->setProgressMessage('Processing...')
        ->setErrorMessage('An error occurred during processing.');

      //load all uuids from backend configuration

      if($options['all'] !== false) {
        $this->logger()->notice(t("\033[1mDeleting Nodes according to CGSpace sitemap Index.\033[0m"));
        $batch->addOperation([BatchNodeService::class, 'batchListFromSitemapProcess']);
      }
      else {
        $this->logger()->notice(t("\033[1mDeleting Nodes according to CGSpace Selected collections.\033[0m"));
        foreach ($this->collections as $community => $collections) {
          foreach ($collections as $collection) {
            $collection_num_items = $this->proxy->countCollectionItems($collection, '');
            $pages = ceil($collection_num_items / $this->configuration['page_size']);
            for ($page = 0; $page < $pages; $page++) {
              //get the number of pages
              $batch->addOperation([BatchNodeService::class, 'batchListProcess'], [$collection, '', $page]);
            }
          }
        }
      }

      $uuids = $this->getPublicationNodesUUIDs();
      $chunk_size = $this->configuration['node_chunk_size'];
      // Create batch which collects all the specified queue items and process them one after another
      $current = 0;
      foreach (array_chunk($uuids, $chunk_size) as $chunk) {
        // Create batch operations
        $batch->addOperation([BatchNodeService::class, 'batchDeleteProcess'], [$chunk, $current, ceil(count($uuids) / $chunk_size)]);
        $current++;
      }

      // Adds the batch sets
      batch_set($batch->toArray());
      // Process the batch and after redirect to the frontpage
      drush_backend_batch_process();
    }
    catch (\Exception $ex) {
      $this->logger()->error(t("Error creating the Batch process: @message", ["@message" => $ex->getMessage()]));
    }
  }


  private function getPublicationNodesUUIDs():array {
    try {
      $connection = \Drupal::database();

      $query = $connection->select('node__field_cg_uuid', 'f');
      $query->join('node_field_data', 'n', 'n.nid = f.entity_id');
      $query->fields('f', ['field_cg_uuid_value']);
      $query->condition('n.type', 'cgspace_publication');
      $query->condition('n.status', 1);

      $uuids = $query->execute()->fetchCol();

      return $uuids;
    }
    catch (DatabaseException $ex) {
      $this->logger()->error(t("Error connection to database: @message", ["@message" => $ex->getMessage()]));
    }
    return [];
  }
}
