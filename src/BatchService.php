<?php

namespace Drupal\cgspace_importer;

use Drupal\cgspace_importer\Commands\CGSpaceImporterCommands;
use Drupal\cgspace_importer\Plugin\cgspace_importer\CGSpaceProxy;
use Drupal\Core\File\FileExists;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Console\Output\ConsoleOutput;
/**
 * Class BatchService.
 */
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

class BatchService {
  protected $logger;

  public function __construct(LoggerChannelFactoryInterface $logger_factory) {
    $this->logger = $logger_factory->get('cgspace_importer');
  }

  /**
   * Common batch processing callback for all operations.
   */
  public static function batchProcess($item_id, &$context) {

    // Get the endpoint from the configuration.
    $endpoint = \Drupal::config('cgspace_importer.settings')->get('endpoint');

    // Instantiate the CGSpaceProxy with the required dependencies.
    $proxy = self::getProxy();

    $context['results'][] = $proxy->getItem($item_id);

    // Optional message displayed under the progressbar.
    $context['message'] = t("Getting item @url", [
      '@url' => $endpoint.'/server/api/core/items/'.$item_id,
    ]);

  }

  /**
   * Batch finished callback.
   */
  public static function batchFinished($success, $results, $operations) {

    if ($success) {

      \Drupal::messenger()->addMessage(
        t("\033[1m@items total items loaded\033[0m from CGSpace.",
        [
          "@items" => count($results)
        ])
      );

      self::saveProxyDatabase($results);

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

  /**
   * Batch Update finished callback.
   */
  public static function updateFinished($success, $results, $operations)
  {

    if ($success) {
      \Drupal::messenger()->addMessage(t("Items correctly downloaded from CGSpace.Starting to update Proxy!"));

      // Get the endpoint from the configuration.
      $endpoint = \Drupal::config('cgspace_importer.settings')->get('endpoint');

      //overwrite source data with updated items
      //load source data
      $proxy = self::loadProxyDatabase();

      //index data by uuid
      $source_items = [];
      foreach ($proxy['items'] as $item) {
        if (isset($item['uuid'])) {
          $source_items[$item['uuid']] = $item;
        }
      }

      // add or update items
      $total_updated = 0;
      $total_added = 0;
      foreach ($results as $item) {
        if (isset($item['uuid'])) {
          $url = $endpoint . '/server/api/core/items/' . $item['uuid'];

          if (isset($source_items[$item['uuid']])) {
            \Drupal::messenger()->addMessage("Updating item ".$item['uuid']);
            $total_updated++;
          } else {
            \Drupal::messenger()->addMessage("Adding new item ".$item['uuid']);
            $total_added++;
          }

          $source_items[$item['uuid']] = $item;
        }
      }


      //get updated list of items for the configured collections to compare and check for deleted items
      $proxy = self::getProxy();

      $collections = \Drupal::config('cgspace_importer.settings.collections')->get();
      $new_items = [];
      foreach ($collections as $collection_key => $collection_value) {
        if ($collection_value) {

          $collectionItems = $proxy->getAllItems($collection_key);
          foreach ($collectionItems as $item) {
            $new_items[] = $item;
          }
        }
      }


      $total_deleted = 0;
      foreach($source_items as $source_item_uuid => $source_item) {
        if(!in_array($source_item_uuid, $new_items)) {
          //delete from source_items and increase total_deleted
          unset($source_items[$source_item_uuid]);
          $total_deleted++;
        }
      }

      \Drupal::messenger()->addMessage(
        t("\033[1m@items total items updated\033[0m.",
        [
          '@items' => $total_updated
        ])
      );
      \Drupal::messenger()->addMessage(
        t("\033[1m@items total items added\033[0m.",
        [
          '@items' => $total_added
        ])
      );
      \Drupal::messenger()->addMessage(
        t("\033[1m@items total items deleted\033[0m.",
        [
          '@items' => $total_deleted
        ])
      );

      //save updated proxy data
      $proxy = [];
      foreach($source_items as $item) {
        $proxy['items'][] = $item;
      }
      self::saveProxyDatabase($proxy['items']);
    }

    else {
      $error_operation = reset($operations);
      \Drupal::messenger()->addError(
        t('An error occurred while processing @operation with arguments : @args',
        [
          '@operation' => $error_operation[0],
          '@args' => print_r($error_operation[0], TRUE)
        ])
      );
    }
  }

  private static function saveProxyDatabase(array $items): void {
    $destination = CGSpaceImporterCommands::JSON_DATABASE_FILE;

    try {
      $json = json_encode(
        ['items' => $items],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK
      );

      if ($json === false) {
        throw new \RuntimeException('JSON encoding failed: ' . json_last_error_msg());
      }

      $action = !file_exists($destination) ? 'created' : 'updated';

      $file = \Drupal::service('file.repository')->writeData(
        $json,
        $destination,
        FileExists::Replace
      );

      if (!$file) {
        throw new \RuntimeException("Unable to write JSON to file at $destination");
      }


      $url = \Drupal::service('file_url_generator')->generateAbsoluteString($destination);
      \Drupal::messenger()->addMessage(
        t("\033[1mDSpace Proxy JSON file @action \033[0m at @url.",
        [
          "@action" => $action,
          "@url" => $url
        ])
      );

    } catch (\Throwable $e) {
      \Drupal::messenger()->addError(
        t("Error saving JSON database: @message",
        ['@message' => $e->getMessage()]
      ));
    }
  }

  private static function loadProxyDatabase() {
    $uri = CGSpaceImporterCommands::JSON_DATABASE_FILE;

    try {
      $path = \Drupal::service('file_system')->realpath($uri);

      if (!file_exists($path)) {
        \Drupal::messenger()->addError(
          t('JSON file does not exist at @path',
          [
            '@path' => $path
          ])
        );
        return [];
      }

      $contents = file_get_contents($path);

      if ($contents === false) {
        \Drupal::messenger()->addError(
          t('Unable to read JSON file at @path',
          [
            '@path' => $path
          ])
        );
        return [];
      }

      $data = json_decode($contents, true);

      if (json_last_error() !== JSON_ERROR_NONE) {
        \Drupal::messenger()->addError(
          t('Invalid JSON format in file at @path: @error', [
            '@path' => $path,
            '@error' => json_last_error_msg(),
          ])
        );
        return [];
      }

      return $data;

    } catch (\Exception $e) {
      \Drupal::messenger()->addError(
        t('Exception while loading JSON file: @message',
        [
          '@message' => $e->getMessage()
        ])
      );
      return [];
    }
  }

  public static function getProxy() {
    $configFactory = \Drupal::service('config.factory');
    $httpClient = \Drupal::service('http_client');
    $endpoint = $configFactory->get('cgspace_importer.settings')->get('endpoint');

    return new CGSpaceProxy($endpoint, $configFactory, $httpClient);
  }

}
