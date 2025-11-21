<?php

namespace Drupal\cgspace_importer;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Queue\RequeueException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\TransferStats;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;

Class CGSpaceProxyBase {

  use DependencySerializationTrait;

  protected $endpoint;
  protected ClientInterface $httpClient;
  protected ImmutableConfig $configuration;
  protected LoggerChannelInterface $logger;

  public function __construct($endpoint, ConfigFactoryInterface $configFactory, ClientInterface $httpClient, LoggerChannelFactoryInterface $loggerFactory) {
    $this->configuration = $configFactory->get('cgspace_importer.settings.general');
    $this->endpoint = empty($endpoint)? $this->configuration->get('endpoint') : $endpoint;
    $this->httpClient = $httpClient;
    $this->logger = $loggerFactory->get('cgspace_importer');

  }

  public static function create(ContainerInterface $container): static {
    $configFactory = $container->get('config.factory');
    $configuration = $configFactory->get('cgspace_importer.settings.general');
    return new static(
      $configuration->get('endpoint') ?? '',
      $configFactory,
      $container->get('http_client'),
      $container->get('logger.factory'),
    );
  }

  /**
   * Load Data from URL with JSON endpoint and return them as array
   *
   * @param $url
   * The url where to make the http call
   * @return mixed|void
   * the result array
   */
  protected function getJsonData(string $url) {
    try {
      $resultJson = $this->getData($url);
      $result = json_decode($resultJson, true);

      if (!$result) {
        throw new RequeueException();
      }

      return $result;
    }
    catch (RequestException $ex) {
      //An error happened.
      print $ex->getMessage();
    }
  }

  /**
   * Load Data from URL with XML endpoint and return them as SimpleXMLElement
   *
   * @param $url
   * The url where to make the http call
   * @return void|SimpleXMLElement
   * the result SimpleXMLElement object
   */
  protected function getXMLData(string $url) {
    try {
      $resultXML = $this->getData($url);
      $result = simplexml_load_string($resultXML);

      if (!$result) {
        throw new RequeueException();
      }

      return $result;
    }
    catch (RequestException $ex) {
      //An error happened.
      print $ex->getMessage();
    }
  }


  /**
   * Load Data from URL with JSON endpoint and return them as array
   *
   * @param string $url
   * The url where to make the http call
   * @return mixed|void
   * the result array
   */
  protected function getDataBitstream(string $url) {

    try {
      $resultJson = $this->getData($url);
      if(!is_null($resultJson)) {
        return json_decode($resultJson, true);
      }
      return [];
    }
    catch (RequestException $ex) {
      // Non bloccare il flusso: gestisci l'errore silenziosamente o loggalo
      if ($ex->hasResponse() && $ex->getResponse()->getStatusCode() == 401) {
        $this->logger->warning('401 Error while trying to get bitstream URL: @url', ['@url' => $url]);
      }
      else {
        $this->logger->error('Error getting Item Bitstream: @message', [
            '@message' => $ex->getMessage()
          ]);
      }
    }
  }

  /**
   * Raw Load Data from URL endpoint and return the response body content
   *
   * @param $url
   * The url where to make the http call
   * @return mixed|void
   * the httpclient response body content
   */
  private function getData(string $url) {

    $importer = $this->configuration->get('importer');

    try {
      $request = $this->httpClient->request('GET', $url, [
        'headers' => [
          'User-Agent' => $importer." Publications Importer BOT"
        ],
        'timeout' => 60000,
        'on_stats' => function (TransferStats $stats) {
          if($this->configuration->get('debug')) {
            $this->logger->notice(
              t('[@time] CGSpace request to @uri.',
                [
                  '@uri' => $stats->getEffectiveUri(),
                  '@time' => $stats->getTransferTime()
                ])
            );
          }
        }
      ]);
      $status = $request->getStatusCode();

      if ($status != 200) {
        throw new RequeueException();
      }
      else {
        return $request->getBody()->getContents();
      }
    }
    catch (RequestException $ex) {
      //An error happened.
      $this->logger->error('Error getting Item (@code): @message', [
        '@code' => $ex->getCode(),
        '@message' => $ex->getMessage()
      ]);
    }
  }
}
