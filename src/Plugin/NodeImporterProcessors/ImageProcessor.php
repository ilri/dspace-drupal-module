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

  protected array $field_configuration;

  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->httpClient = \Drupal::httpClient();
    $this->fileRepository = \Drupal::service('file.repository');

    $content_type = \Drupal::config('cgspace_importer.mappings')->get('content_type');
    $this->field_configuration =  \Drupal::service('entity_field.manager')->getFieldDefinitions("node", $content_type);
  }

  protected const MAX_FILENAME_LENGTH = 255;

  /**
   * {@inheritDoc}
   */
  public function process(string $source, string $target, array $item):array {

    $source_value = $this->getSourceValue($source, $item);

    if(!is_null($source_value) && isset($source_value['name']) && isset($source_value['uri'])) {

      try {
        //get local path from field configuration
        $field_definition = $this->field_configuration[$target];
        $field_settings = $field_definition->getSettings();
        $local_uri = $field_settings['uri_scheme'].'://'.$field_settings['file_directory'].'/'.$source_value['name'];

        $file = $this->getImage($local_uri, $source_value['uri']);

        if ($file instanceof File) {

          return [
            $target => [
              'target_id' => $file->id(),
              'alt' => $source_value['name'],
              'title' => $source_value['name'],
            ]
          ];
        }
      } catch (\Exception $ex) {
        \Drupal::logger('cgspace_importer')->error(
          t("Error saving Cover file: @message",
            ['@message' => $ex->getMessage()]
          ));
      }

    }

    return [];
  }

  private function getImage($destination, $remote_url) {

    try {
      $data = $this->httpClient->get($remote_url)->getBody();

      //check if destination path is larger than admitted field length
      if(!$this->isValidUri($destination)) {
        //truncate filename to fit database specifications
        $destination = $this->truncateUri($destination);
      }

      $file = $this->fileRepository->writeData($data, $destination, FileExists::Replace);

      if (!$file) {
        throw new \RuntimeException("Unable to download Cover file to $destination");
      }

      return $file;
    }
    catch (\Exception $ex) {
      \Drupal::logger("cgspace_importer")->error(
        t("Error saving Cover file: @message",
          ['@message' => $ex->getMessage()]
        ));
    }
  }

  private function isValidUri($uri):bool {
    return (strlen($uri) < self::MAX_FILENAME_LENGTH);
  }

  private function truncateUri($uri):string {

    \Drupal::logger('cgspace_importer')->warning(
      t("Filename too long for @file. Max length is @length",
        [
          '@file' => $uri,
          '@length' => self::MAX_FILENAME_LENGTH
        ]
      ));

    $local_scheme = parse_url($uri, PHP_URL_SCHEME).'://';
    $local_host = parse_url($uri, PHP_URL_HOST);
    $local_path = parse_url($uri, PHP_URL_PATH);

    $extension = pathinfo($local_path, PATHINFO_EXTENSION);
    $directory = pathinfo($local_path,PATHINFO_DIRNAME);
    $base_name = pathinfo($local_path, PATHINFO_BASENAME);

    $filename_length = self::MAX_FILENAME_LENGTH - strlen($local_scheme) - strlen($local_host) - strlen($directory) - ($extension ? strlen('.' . $extension) : 0);
    $shortened_filename = substr($base_name, 0, $filename_length);

    $return = $local_scheme.$local_host.$directory.$shortened_filename . ($extension ? '.' . $extension : '');

    return $return;
  }

}
