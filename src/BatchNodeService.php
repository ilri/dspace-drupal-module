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

use Drupal\cgspace_importer\Commands\CGSpaceImporterCommands;
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

      // Get the endpoint from the configuration.
      $endpoint = \Drupal::config('cgspace_importer.settings.general')->get('endpoint');

      // Instantiate the CGSpaceProxy with the required dependencies.
      $proxy = self::getProxy();

      $context['results'][$item_id] = $proxy->getItem($item_id);
      $context['message'] = t('Getting @current/@total – @percentage%. (@url)', [
        '@current' => $current + 1,
        '@total' => $total,
        '@percentage' => round(($current + 1) * 100 / $total, 1),
        '@url' => $endpoint . '/server/api/core/items/' . $item_id,
      ]);
    }

  }

  /**
   * Common batch processing callback for all operations.
   */
  public static function batchListProcess($collection, $query, $page, &$context) {

    if(!isset($context['results']['total'])) {
      $context['results']['total'] = [
        'updated' => 0,
        'created' => 0,
        'deleted' => 0,
        'skipped' => 0,
      ];
    }

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

    if (!isset($context['results']['total'])) {
      $context['results']['total'] = [
        'updated' => 0,
        'created' => 0,
        'deleted' => 0,
        'skipped' => 0
      ];
    }

    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'type' => 'cgspace_publication',
      'field_cg_uuid' => $uuid
    ]);

    $endpoint = \Drupal::config('cgspace_importer.settings.general')->get('endpoint');
    $url = $endpoint . '/server/api/core/items/' . $uuid;
    $node_importer = new NodeImporter();

    if (empty($nodes)) {
      $context['message'] = t("Adding @current/@total - @percentage%. (@url)",
        [
          '@current' => $current + 1,
          '@total' => $total,
          '@percentage' => round(($current + 1) * 100 / $total, 1),
          '@url' => $url,
        ]);
      //add node
      if(empty($context['results'][$uuid]['name']) || ($context['results'][$uuid]['name'] == 'null')) {
        $context['results']['total']['skipped']++;
      } else {
        $node_importer->add($context['results'][$uuid]);
        $context['results']['total']['created']++;
      }

    } else {

      foreach ($nodes as $node) {
        if ($node instanceof NodeInterface) {
          if ($node->hasField('field_cg_uuid') && !$node->get('field_cg_uuid')->isEmpty()) {

            $field_cg_uuid = $node->get('field_cg_uuid')->getValue();

            if ($field_cg_uuid[0]['value'] == $uuid) {
              $context['message'] = t("Updating @current/@total - @percentage%. (@url)",
                [
                  '@current' => $current + 1,
                  '@total' => $total,
                  '@percentage' => round(($current + 1) * 100 / $total, 1),
                  '@url' => $url,
                ]);
              //update node
              if(empty($context['results'][$uuid]['name']) || ($context['results'][$uuid]['name'] == 'null')) {
                $context['results']['total']['skipped']++;
              } else {
                $node_importer->update($node, $context['results'][$uuid]);
                $context['results']['total']['updated']++;
              }
            }
          }
        }
      }
    }

  }


  public static function batchDeleteProcess($uuid, &$context) {

    if(!isset($context['results']['total'])) {
      $context['results']['total'] = [
        'updated' => 0,
        'created' => 0,
        'deleted' => 0,
        'skipped' => 0,
      ];
    }

    if(!in_array($uuid, $context['results']['items'])) {

      try {
        $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
          'type' => 'cgspace_publication',
          'field_cg_uuid' => $uuid
        ]);

        if(!empty($nodes)) {
          $node = reset($nodes);

          if($node instanceof NodeInterface) {

              $node->delete();
              $context['message'] = t('Deleting node @nid (@uuid).', [
                '@nid'  => $node->id(),
                '@uuid' => $uuid
              ]);

          }
        }
      }
      catch(EntityStorageException $ex) {
        \Drupal::messenger()->addError(
          t("Error deleting node @nid. @error",
            [
              '@item' => $node->id(),
              '@error' => $ex->getMessage()
            ])
        );
      }

      $context['results']['total']['deleted']++;
    }


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
}
