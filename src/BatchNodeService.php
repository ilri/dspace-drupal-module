<?php

namespace Drupal\cgspace_importer;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\File\FileExists;
use Drupal\Core\Logger\LoggerChannelInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Console\Output\ConsoleOutput;
use Drupal\Core\Messenger\MessengerTrait;
/**
 * Class BatchNodeService.
 */

class BatchNodeService {

  use MessengerTrait;

  /**
   * @var CGSpaceProxy The CGSpaceProxy object
   */
  protected CGSpaceProxy $proxy;

  /**
   * @var  ImmutableConfig The cgspace_importer.settings.general configuration object
   */
  protected ImmutableConfig $configuration;

  /**
   * @var LoggerChannelInterface
   * The Logger Object for cgspace_importer channel
   */
  protected LoggerChannelInterface $logger;

  /**
   * @var NodeImporter the NodeImporter Object
   */
  protected NodeImporter $nodeImporter;


  public function __construct() {
    $configFactory = \Drupal::service('config.factory');
    $httpClient = \Drupal::service('http_client');
    $loggerFactory = \Drupal::service('logger.factory');
    $this->configuration = $configFactory->get('cgspace_importer.settings.general');

    $endpoint = $this->configuration->get('endpoint');
    $this->proxy = new CGSpaceProxy($endpoint, $configFactory, $httpClient, $loggerFactory);
    $this->logger = $loggerFactory->get('cgspace_importer');
    $this->nodeImporter = new NodeImporter();

  }

  /**
   * Load item from CGSpace and stores it on the Batch process $context['results'][$item_id]
   * @param $current
   * The current item index previosly stored on context['results']['items'] by List processes
   * @param $total
   * The total amount of items.
   * @param $context
   * The Batch Process context
   * @return void
   */
  public function batchLoadProcess($current, $total, &$context):void {
    //avoid to process skipped elements
    if(isset($context['results']['items'][$current])) {
      $item_id = $context['results']['items'][$current];

      $context['results'][$item_id] = $this->proxy->getItem($item_id);

      $this->printMessage("Getting", $current, $total, $context['message']);

    }
  }

  /**
   * Load the full list of items from CGSpace sitemap and stores it on the Batch process $context['results']['items']
   * @param $context
   * The Batch Process context
   * @return void
   */
  public function batchListFromSitemapProcess(&$context):void {

    $context['results']['items'] = $this->proxy->getItemsFromSitemap();

  }

  /**
   * Returns the list of items UUIDs for the passed collection applying
   * optional query and page parameters for CGSpace API
   * @param $collection
   * The CGSpace collection UUID
   * @param $query
   * The query parameter for CGSpace API
   * @param $page
   * The page for the CGSpace query
   * @param $context
   * The Batch Process context
   * @return void
   */
  public function batchListProcess($collection, $query, $page, &$context):void {

    $this->initContext($context);

    if(!isset($context['results']['items'])) {
      $context['results']['items'] = [];
    }

    if(!isset($context['results']['list'][$collection])) {
      $context['results']['list'][$collection] = 0;
    }

    $collection_items = $this->proxy->getPagedItemsByQuery($collection, $query, $page);

    foreach ($collection_items as $collection_item) {
      $context['results']['items'][] = $collection_item;
      $context['results']['list'][$collection] ++;
    }

    $context['message'] = t('@items items indexed for collection @collection', [
      '@items' => $context['results']['list'][$collection],
      '@collection' => $collection,
    ]);

  }

  /**
   * Create a new Node if node with current UUID doesn't exist otherwise update it
   * @param $current
   * The current item index previosly stored on context['results']['items'] by List processes
   * @param $total
   * The total amount of items.
   * @param $context
   * The Batch Process context
   * @return void
   */
  public function batchUpdateProcess($current, $total, &$context):void {

    $this->initContext($context);

    $uuid = $context['results']['items'][$current];

    //skip CGSpace items with empty name or name equal to null
    if(empty($context['results'][$uuid]['name']) || ($context['results'][$uuid]['name'] == 'null')) {
      $context['results']['total']['skipped']++;
    }
    else {
      if (empty($node = $this->nodeImporter->get($uuid))) {
        $action = "Adding";
        //add node
        $this->nodeImporter->add($context['results'][$uuid]);
        $context['results']['total']['created']++;
      } else {
        $action = "Updating";
        //update node
        $this->nodeImporter->update($node, $context['results'][$uuid]);
        $context['results']['total']['updated']++;
      }
      $this->printMessage($action, $current, $total, $context['message']);
    }

  }

  /**
   * Creates a list of Nodes loading them from CGSpace from the array of UUIDs passed as argument
   * @param $uuids
   * an array of CGSpace items uuids
   * @param $current
   * current process operation
   * @param $total
   * number of total operations
   * @param $context
   * the Batch process context
   * @return void
   */
  public function batchCreateFromSitemapProcess($uuids, $current, $total, &$context):void {

    $this->initContext($context);

    $skipped = 0;
    $added = 0;
    $processed = $current * $this->configuration->get('node_chunk_size');
    foreach($uuids as $uuid) {

      if (!$this->nodeImporter->exists($uuid)) {

        $context['results'][$uuid] = $this->proxy->getItem($uuid);
        $this->printMessage("Getting", $current, $total, $context['message']);

        //add node
        if (empty($context['results'][$uuid]['name']) || ($context['results'][$uuid]['name'] == 'null')) {

          $this->logger->warning('CGSpace Item @uuid has invalid name: @name.', [
            '@uuid' => $uuid,
            '@name' =>  $context['results'][$uuid]['name'] ?? '',
          ]);

          $context['results']['total']['skipped']++;
          $skipped++;
        } else {
          $this->nodeImporter->add($context['results'][$uuid]);
          $context['results']['total']['created']++;
          $added++;
        }
      } else {
        $skipped++;
      }
      $processed++;
    }

    $this->printMessage("$processed Processed, $added Added, $skipped Skipped Chunk:", $current, $total, $context['message']);
  }

  /**
   * Delete a list of Nodes with field mapped as uuid_field in the array of UUIDs passed as argument
   * @param $uuids
   * an array of CGSpace items uuids
   * @param $current
   * current process operation
   * @param $total
   * number of total operations
   * @param $context
   * the Batch process context
   * @return void
   */
  public function batchDeleteProcess($uuids, $current, $total, &$context):void {

    $this->initContext($context);

    $uuids_to_delete = [];
    foreach($uuids as $uuid) {
      if(!isset($context['results']['items']) || !in_array($uuid, $context['results']['items'])) {
        $uuids_to_delete[] = $uuid;
      }
    }

    if(!empty($uuids_to_delete)) {
      try {
        if($this->nodeImporter->delete($uuids, $context)) {
          $context['results']['total']['deleted']++;
        }
      }
      catch(EntityStorageException $ex) {
        $this->logger->error(
          t("Error deleting node @nid. @error",
            [
              '@error' => $ex->getMessage()
            ])
        );
      }
    }

    $this->printMessage("Deleting...", $current, $total, $context['message']);

  }

  /**
   * Print final results
   * @param $success
   * The Batch process status
   * @param $results
   * The batch process results
   * @param $operations
   * the Batch process operations
   * @return void
   */
  public function batchFinished($success, $results, $operations):void {

    if(!isset($results['total'])) {
      $results['total'] = [
        'updated' => 0,
        'created' => 0,
        'deleted' => 0,
        'skipped' => 0,
      ];
    }

    if ($success) {

      $recap_message =  t("@created/@updated/@deleted/@skipped (created/updated/deleted/skipped) nodes.",
        [
          "@created" => $results['total']['created'],
          "@updated" => $results['total']['updated'],
          "@deleted" => $results['total']['deleted'],
          "@skipped" => $results['total']['skipped']
        ]);

      $summary_message = t("@total Processed items.",
        [
          "@total" => $results['total']['created'] + $results['total']['updated'] + $results['total']['deleted'],
          "@skipped" => $results['total']['skipped']
        ]);


      $this->logger->notice($recap_message);
      $this->logger->notice($summary_message);

      if(PHP_SAPI !== 'cli') {
        $this->messenger()->addMessage($recap_message);
        $this->messenger()->addMessage($summary_message);
      }

    }
    else {
      $error_operation = reset($operations);
      $this->logger->error(
        t("An error occurred while processing @operation with arguments : @args",
          [
            '@operation' => $error_operation[0],
            '@args' => print_r($error_operation[0], TRUE)
          ])
      );
    }
  }

  /**
   * On Batch success set the cgspace_importer.last_run State variable
   * @param $success
   * The Batch process status
   * @param $results
   * The batch process results
   * @param $operations
   * the Batch process operations
   * @return void
   */
  public function batchUpdateFinished($success, $results, $operations):void {

    if ($success) {
      //set last run date
      $date = new \DateTimeImmutable();
      $last_run = \DateTime::createFromImmutable($date)->format('Y-m-d\Th:i:s\Z');

      \Drupal::state()->set('cgspace_importer.last_run', $last_run);
    }

    $this->batchFinished($success, $results, $operations);

  }

  /**
   * Internal function to initialize context variables
   * @param $context
   * The batch process context
   * @return void
   */
  private function initContext(&$context):void {
    if(!isset($context['results']['total'])) {
      $context['results']['total'] = [
        'updated' => 0,
        'created' => 0,
        'deleted' => 0,
        'skipped' => 0,
      ];
    }
  }

  /**
   * Internal function to print report messages
   *
   * @param $action
   * The initial text before statistics
   * @param $current
   * current process index
   * @param $total
   * number of items to process
   * @param $message
   * the Batch process context['message']
   * @return void
   */
  private function printMessage($action, $current, $total, &$message):void {

    $message = t("@action @current/@total - @percentage%.",
      [
        '@action' => $action,
        '@current' => $current + 1,
        '@total' => $total,
        '@percentage' => round(($current + 1) * 100 / $total, 1),
      ]);
  }
}
