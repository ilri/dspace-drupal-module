<?php

namespace Drupal\cgspace_importer\Plugin\NodeImporterProcessors;


use Drupal\cgspace_importer\BaseProcessor;
use Drupal\Core\File\FileExists;
use Drupal\file\Entity\File;
use Drupal\cgspace_importer\NodeImporterProcessorInterface;
use GuzzleHttp\ClientInterface;

/**
 * @NodeImporterProcessor(
 *   id = "cgspace_processor_image",
 *   label = @Translation("Image Processor"),
 *   description = @Translation("Provides Image processor for CGSpace Importer plugin"),
 *   )
 */
class ImageProcessor extends BaseProcessor implements NodeImporterProcessorInterface {


  protected ClientInterface $httpClient;
  protected $fileRepository;

  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->httpClient = \Drupal::httpClient();
    $this->fileRepository = \Drupal::service('file.repository');
  }

  protected const MAX_FILENAME_LENGTH = 255;
  protected const IMAGE_DIRECTORY_PATH = 'public://publication-covers/';

  /**
   * {@inheritDoc}
   */
  public function process(string $source, string $target, array $item):array {

    $source_value = $this->getSourceValue($source, $item);

    if(!is_null($source_value) && isset($source_value['name']) && isset($source_value['uri'])) {

      try {
        $file = $this->getImage($source_value['name'], $source_value['uri']);

        if ($file instanceof File) {

          return [
            $target => [
              'target_id' => $file->id(),
              'alt' => $source_value['name'],
              'title' => $source_value['name'],
            ]
          ];
        }
      } catch (\Throwable $e) {
        \Drupal::messenger()->addError(
          t("Error saving Cover file: @message",
            ['@message' => $e->getMessage()]
          ));
      }

    }

    return [];
  }

  private function getImage($filename, $uri) {

    if(strlen($filename) > self::MAX_FILENAME_LENGTH) {
      \Drupal::logger('cgspace_importer')->warning(
        t("Filename too long for @file. Max length is @length",
          [
            '@file' => $filename,
            '@length' => self::MAX_FILENAME_LENGTH
          ]
        ));

      $filename = substr($filename, 0, self::MAX_FILENAME_LENGTH);
    }

    $destination = self::IMAGE_DIRECTORY_PATH.$filename;

    try {
      $data = $this->httpClient->get($uri)->getBody();

      $file = $this->fileRepository->writeData($data, $destination, FileExists::Replace);

      if (!$file) {
        throw new \RuntimeException("Unable to download Cover file to $destination");
      }

      return $file;
    }
    catch (\Throwable $e) {
      \Drupal::messenger()->addError(
        t("Error saving Cover file: @message",
          ['@message' => $e->getMessage()]
        ));
    }
  }

}
