<?php

namespace Drupal\cgspace_importer;

use \Drupal\node\Entity\Node;
use Drupal\file\Entity\File;
use \Drupal\node\NodeInterface;
use \Drupal\taxonomy\Entity\Term;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\File\FileExists;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Class BatchService.
 */

class BatchNodeService {

  /**
   * Common batch processing callback for all operations.
   */
  public static function batchLoadProcess($current, $total, &$context) {
    //avoid to process skipped elements
    if(isset($context['results']['items'][$current])) {
      $item_id = $context['results']['items'][$current];

      $proxy = self::getProxy();

      $context['results'][$item_id] = $proxy->getItem($item_id);

      self::printMessage("Getting", $current, $total, $context['message']);

    }
  }


  public static function batchListFromSitemapProcess(&$context) {

    $context['results']['items'] = self::getProxy()->getItemsFromSitemap();

  }

  /**
   * Common batch processing callback for all operations.
   */
  public static function batchListProcess($collection, $query, $page, &$context) {

    self::init($context);

    if(!isset($context['results']['items'])) {
      $context['results']['items'] = [];
    }

    if(!isset($context['results']['list'][$collection])) {
      $context['results']['list'][$collection] = 0;
    }

    // Instantiate the CGSpaceProxy with the required dependencies.
    $proxy = self::getProxy();

    $collection_items = $proxy->getPagedItemsByQuery($collection, $query, $page);

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
   * Common batch processing callback for all operations.
   */
  public static function batchUpdateProcess($current, $total, &$context) {

    $uuid = $context['results']['items'][$current];

    self::init($context);

    $node_importer = new NodeImporter();

    if(empty($context['results'][$uuid]['name']) || ($context['results'][$uuid]['name'] == 'null')) {
      $context['results']['total']['skipped']++;
    }
    else {
      if (empty($node = $node_importer->get($uuid))) {
        $action = "Adding";
        //add node
        $node_importer->add($context['results'][$uuid]);
        $context['results']['total']['created']++;
      } else {
        $action = "Updating";
        //update node
        $node_importer->update($node, $context['results'][$uuid]);
        $context['results']['total']['updated']++;
      }
      self::printMessage($action, $current, $total, $context['message']);
    }

  }


  /**
   * Common batch processing callback for all operations.
   */
  public static function batchCreateFromSitemapProcess($uuids, $current, $total, &$context) {

    self::init($context);

    $node_importer = new NodeImporter();
    $proxy = self::getProxy();
    $skipped = 0;
    $added = 0;
    $processed = $current * \Drupal::config('cgspace_importer.settings.general')->get('node_chunk_size');
    foreach($uuids as $uuid) {

      if (!$node_importer->exists($uuid)) {

        $context['results'][$uuid] = $proxy->getItem($uuid);
        self::printMessage("Getting", $current, $total, $context['message']);

        //add node
        if (empty($context['results'][$uuid]['name']) || ($context['results'][$uuid]['name'] == 'null')) {

          \Drupal::logger('cgspace_importer')->warning(t('CGSpace Item @uuid has invalid name: @name.', [
            '@uuid' => $uuid,
            '@name' =>  $context['results'][$uuid]['name'] ?? '',
          ])
          );

          $context['results']['total']['skipped']++;
          $skipped++;
        } else {
          $node_importer->add($context['results'][$uuid]);
          $context['results']['total']['created']++;
          $added++;
        }
      } else {
        $skipped++;
      }
      $processed++;
    }

    self::printMessage("$processed Processed, $added Added, $skipped Skipped Chunk:", $current, $total, $context['message']);
  }


  public static function batchDeleteProcess($uuids, $current, $total, &$context) {

    self::init($context);

    $uuids_to_delete = [];
    foreach($uuids as $uuid) {
      if(!isset($context['results']['items']) || !in_array($uuid, $context['results']['items'])) {
        $uuids_to_delete[] = $uuid;
      }
    }

    if(!empty($uuids_to_delete)) {
      try {
        $node_importer = new NodeImporter();
        if($node_importer->delete($uuids, $context)) {
          $context['results']['total']['deleted']++;
        }
      }
      catch(EntityStorageException $ex) {
        \Drupal::logger('cgspace_importer')->addError(
          t("Error deleting node @nid. @error",
            [
              '@error' => $ex->getMessage()
            ])
        );
      }
    }

    self::printMessage("Deleting...", $current, $total, $context['message']);

  }
  /**
   * Batch finished callback.
   */
  public static function batchFinished($success, $results, $operations) {

    if(!isset($results['total'])) {
      $results['total'] = [
        'updated' => 0,
        'created' => 0,
        'deleted' => 0,
        'skipped' => 0,
      ];
    }

    if ($success) {

      \Drupal::messenger()->addMessage(
        t("\033[1m@created/@updated/@deleted/@skipped\033[0m (created/updated/deleted/skipped) nodes.",
          [
            "@created" => $results['total']['created'],
            "@updated" => $results['total']['updated'],
            "@deleted" => $results['total']['deleted'],
            "@skipped" => $results['total']['skipped']
          ])
      );

      \Drupal::messenger()->addMessage(
        t("\033[1m@total\033[0m Processed items.",
          [
            "@total" => $results['total']['created'] + $results['total']['updated'] + $results['total']['deleted'],
            "@skipped" => $results['total']['skipped']
          ])
      );

    }
    else {
      $error_operation = reset($operations);
      \Drupal::messenger()->addError(
        t("An error occurred while processing @operation with arguments : @args",
          [
            '@operation' => $error_operation[0],
            '@args' => print_r($error_operation[0], TRUE)
          ])
      );
    }
  }


  public static function batchCreateFinished($success, $results, $operations) {

    if($success) {
      //set last update to create date
      $last_run = \Drupal::state()->get('cgspace_importer.last_run');
      if(empty($last_run)) {
        $date = new \DateTimeImmutable();
        $last_run = \DateTime::createFromImmutable($date)->format('Y-m-d\Th:i:s\Z');
        \Drupal::state()->set('cgspace_importer.last_run', $last_run);
      }
    }
    self::batchFinished($success, $results, $operations);

  }

  /**
   * Batch finished callback.
   */
  public static function batchUpdateFinished($success, $results, $operations) {

    if ($success) {
      //set last run date
      $date = new \DateTimeImmutable();
      $last_run = \DateTime::createFromImmutable($date)->format('Y-m-d\Th:i:s\Z');

      \Drupal::state()->set('cgspace_importer.last_run', $last_run);
    }

    self::batchFinished($success, $results, $operations);

  }
  private static function getProxy() {
    $configFactory = \Drupal::service('config.factory');
    $httpClient = \Drupal::service('http_client');
    $endpoint = $configFactory->get('cgspace_importer.settings.general')->get('endpoint');

    return new CGSpaceProxy($endpoint, $configFactory, $httpClient);
  }

  private static function init(&$context) {
    if(!isset($context['results']['total'])) {
      $context['results']['total'] = [
        'updated' => 0,
        'created' => 0,
        'deleted' => 0,
        'skipped' => 0,
      ];
    }
  }

  private static function printMessage($action, $current, $total, &$message) {

    $message = t("@action @current/@total - @percentage%.",
      [
        '@action' => $action,
        '@current' => $current + 1,
        '@total' => $total,
        '@percentage' => round(($current + 1) * 100 / $total, 1),
      ]);
  }
}
