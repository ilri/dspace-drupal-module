<?php

namespace Drupal\cgspace_importer\Plugin\cgspace_importer;

use Drupal\Core\Queue\RequeueException;

Class CGSpaceProxyBase {

  protected $endpoint;

  public function __construct($endpoint) {
    $this->endpoint = $endpoint;
  }

  protected function getData($url) {

    $importer = \Drupal::config('cgspace_importer.settings')->get('importer');
    $result = '';

    $client = \Drupal::httpClient();
    try {
      $request = $client->request('GET', $url, [
        'headers' => [
          'Accept' => 'application/xml',
          'User-Agent' => $importer." Publications Importer BOT"
        ],
        'timeout' => 60000,
      ]);
      $status = $request->getStatusCode();
      $result = $request->getBody()->getContents();

      if (!$result || $status != 200) {
        throw new RequeueException();
      }
    }
    catch (RequestException $e) {
      //An error happened.
      print $e->getMessage();
    }

    return $result;
  }

}
