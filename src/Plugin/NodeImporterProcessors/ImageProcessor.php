<?php

namespace Drupal\cgspace_importer\Plugin\NodeImporterProcessors;


use Drupal\cgspace_importer\BaseProcessor;
use Drupal\Core\File\FileExists;
use Drupal\file\FileRepository;
use Drupal\Core\File\FileSystem;
use Drupal\cgspace_importer\NodeImporterProcessorInterface;
use GuzzleHttp\Client;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;

/**
 * @NodeImporterProcessor(
 *   id = "cgspace_processor_image",
 *   label = @Translation("Image Processor"),
 *   description = @Translation("Provides Image processor for CGSpace Importer plugin"),
 *   )
 */
class ImageProcessor extends BaseProcessor implements NodeImporterProcessorInterface {


  /**
   * @var Client Guzzle httpClient
   */
  protected Client $httpClient;
  /**
   * @var FileRepository file.repository Service
   */
  protected FileRepository $fileRepository;

  /**
   * @var FileSystem file_system Service
   */
  protected FileSystem $fileSystem;

  /**
   * Fields definition for content type configured through the cgspace_importer.mappings configuration
   *
   * @var array
   */
  protected array $fieldDefinitions;

  /**
   * Drupal field max length for uri on Image field
   */
  protected const MAX_FILENAME_LENGTH = 255;



  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->httpClient = \Drupal::httpClient();
    $this->fileRepository = \Drupal::service('file.repository');
    $this->fileSystem = \Drupal::service('file_system');
    $content_type = \Drupal::config('cgspace_importer.mappings')->get('content_type');
    $this->fieldDefinitions =  \Drupal::service('entity_field.manager')->getFieldDefinitions("node", $content_type);
  }


  /**
   * {@inheritDoc}
   */
  public function process(string $source, string $target, array $item):array {

    $source_value = $this->getSourceValue($source, $item);

    if(!is_null($source_value) && isset($source_value['name']) && isset($source_value['uri'])) {

      try {
        //get local path from field configuration
        $field_definition = $this->fieldDefinitions[$target];
        $field_settings = $field_definition->getSettings();
        //create directory if it doesn't exist.
        $directory = $field_settings['uri_scheme'].'://'.$field_settings['file_directory'];
        $this->fileSystem->prepareDirectory($directory, FileSystemInterface:: CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

        $local_uri = $directory .'/'.$source_value['name'];

        $file = $this->getImage($local_uri, $source_value['uri']);

        if($file instanceof FileInterface) {
          return [
            $target => [
              'target_id' => $file->id(),
              'alt' => $source_value['name'],
              'title' => $source_value['name'],
            ]
          ];
        }

      } catch (\Exception $ex) {
        $this->logger->error(
          t("Error saving Cover file: @message",
            ['@message' => $ex->getMessage()]
          ));
      }

    }

    return [];
  }

  /**
   * Download remote Image and save to destination directory
   *
   * @param $destination
   * The full local path
   * @param $remote_url
   * the full remote Url
   * @return mixed
   * the File Class Object created
   */
  private function getImage($destination, $remote_url): mixed {

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
      $this->logger->error(
        t("Error saving Cover file: @message",
          ['@message' => $ex->getMessage()]
        ));
    }

    return [];
  }

  /**
   * Check if uri length is within the MAX_FILENAME_LENGTH limit
   * @param $uri
   * the URI to check
   * @return bool
   * true if is valid false otherwise
   */
  private function isValidUri($uri):bool {
    return (strlen($uri) < self::MAX_FILENAME_LENGTH);
  }

  /**
   * Truncate URI truncating only the filename and preserving the extension
   * @param $uri
   * the full URI to be truncated
   * @return string
   * the truncated full URI
   */
  private function truncateUri($uri):string {

    $this->logger->warning(
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
