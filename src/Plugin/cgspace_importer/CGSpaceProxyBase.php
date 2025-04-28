<?php

namespace Drupal\cgspace_importer\Plugin\cgspace_importer;

use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Queue\RequeueException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\Serializer\SerializerInterface;

Class CGSpaceProxyBase {

  protected $endpoint;
  protected ClientInterface $httpClient;
  protected SerializerInterface $serializer;

  public function __construct($endpoint, ConfigFactoryInterface $configFactory, ClientInterface $httpClient, SerializerInterface $serializer) {
    $this->endpoint = empty($endpoint)? $configFactory->get('cgspace_importer.settings')->get('endpoint') : $endpoint;
    $this->httpClient = $httpClient;
    $this->serializer = $serializer;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('http_client'),
      $container->get('cgspace_importer.serializer'),
    );
  }

  protected function getData($url) {

    $importer = \Drupal::config('cgspace_importer.settings')->get('importer');
    $result = '';

    try {
      $request = $this->httpClient->request('GET', $url, [
        'headers' => [
         //'Accept' => 'application/xml',
          'User-Agent' => $importer." Publications Importer BOT"
        ],
        'timeout' => 60000,
      ]);
      $status = $request->getStatusCode();
      $resultJson = $request->getBody()->getContents();
      $decodedResult = json_decode($resultJson, true);
      $result = $this->serializer->serialize($decodedResult, 'xml');

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
