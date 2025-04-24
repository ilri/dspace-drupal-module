<?php

namespace Drupal\cgspace_importer;

use Drupal\cgspace_importer\Plugin\cgspace_importer\CGSpaceProxy;
use Drupal\Core\File\FileExists;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class BatchService.
 */
class BatchService {

  /**
   * Common batch processing callback for all operations.
   */
  public static function batchProcess($item_id, &$context) {
    // Retrieve the necessary services from the container.
    $configFactory = \Drupal::service('config.factory');
    $httpClient = \Drupal::service('http_client');
    $serializer = \Drupal::service('serializer');

    // Get the endpoint from the configuration.
    $endpoint = $configFactory->get('cgspace_importer.settings')->get('endpoint');

    // Instantiate the CGSpaceProxy with the required dependencies.
    $proxy = new CGSpaceProxy($endpoint, $configFactory, $httpClient, $serializer);

    $context['results'][] = $proxy->getItem($item_id);

    // Optional message displayed under the progressbar.
    $context['message'] = t('Getting publication <a href="@url" target="_blank">@title</a>', [
      '@id' => $item_id,
      '@url' => 'https://cgspace.cgiar.org/server/api/core/items/'.$item_id,
      '@title' => 'https://cgspace.cgiar.org/server/api/core/items'.$item_id,
    ]);

  }

  /**
   * Batch finished callback.
   */
  public static function batchFinished($success, $results, $operations) {

    if ($success) {
      \Drupal::messenger()->addMessage(t("Contents are successfully synced from CGSpace."));

      $document = new \DOMDocument();
      $items = $document->createElement('items');
      $document->appendChild($items);

      foreach ($results as $item) {

        try {
          $item_xml = new \SimpleXMLElement($item);
          $dom_item_xml = dom_import_simplexml($item_xml);
          $dom_item_xml = $document->importNode($dom_item_xml, TRUE);
          $items->appendChild($dom_item_xml);
        }
        catch (\Exception $e) {
          \Drupal::logger('widget')->error($e->getMessage());
        }
      }

      $content = $document->saveXML();

      $destination = 'public://cgspace-proxy.xml';
      if (\Drupal::service('file.repository')->writeData($content, $destination, FileExists::Rename)) {
        \Drupal::messenger()->addMessage(t('DSpace Proxy XML file created <a href="@destination">here</a>.', array('@destination' => \Drupal::service('file_url_generator')->generateAbsoluteString($destination) )));

        //$redirect = new RedirectResponse('/admin/content/cgspace-sync-publications');
        //$redirect->send();
      }
      else {
        \Drupal::messenger()->addError(t('An error as occurred while saving the result XML file'));
      }

    }
    else {
      $error_operation = reset($operations);
      \Drupal::messenger()->addError(t('An error occurred while processing @operation with arguments : @args', array('@operation' => $error_operation[0], '@args' => print_r($error_operation[0], TRUE))));
    }

  }
}
