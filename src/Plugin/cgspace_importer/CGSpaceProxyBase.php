<?php

namespace Drupal\cgspace_importer\Plugin\cgspace_importer;

use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Queue\RequeueException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

Class CGSpaceProxyBase {

  protected $endpoint;
  protected ClientInterface $httpClient;

  public function __construct($endpoint, ConfigFactoryInterface $configFactory, ClientInterface $httpClient) {
    $this->endpoint = empty($endpoint)? $configFactory->get('cgspace_importer.settings')->get('endpoint') : $endpoint;
    $this->httpClient = $httpClient;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('http_client'),
    );
  }

  protected function getData($url) {

    $importer = \Drupal::config('cgspace_importer.settings')->get('importer');
    $result = '';

    try {
      $request = $this->httpClient->request('GET', $url, [
        'headers' => [
          'User-Agent' => $importer." Publications Importer BOT"
        ],
        'timeout' => 60000,
      ]);
      $status = $request->getStatusCode();
      $resultJson = $request->getBody()->getContents();
      $result = json_decode($resultJson, true);

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
