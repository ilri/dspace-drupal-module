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

  public const MAX_FILENAME_LENGTH = 255;

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

    if(isset($context['results']['items'][$current])) {

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

      if (empty($nodes)) {
        $context['message'] = t("Adding @current/@total - @percentage%. (@url)",
          [
            '@current' => $current + 1,
            '@total' => $total,
            '@percentage' => round(($current + 1) * 100 / $total, 1),
            '@url' => $url,
          ]);
        //add node
        if (empty($context['results'][$uuid]['name']) || ($context['results'][$uuid]['name'] == 'null')) {
          $context['results']['total']['skipped']++;
        } else {
          self::addCGSpacePublication($context['results'][$uuid]);
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
                if (empty($context['results'][$uuid]['name']) || ($context['results'][$uuid]['name'] == 'null')) {
                  $context['results']['total']['skipped']++;
                } else {
                  self::updateCGSpacePublication($node, $context['results'][$uuid]);
                  $context['results']['total']['updated']++;
                }
              }
            }
          }
        }
      }
    } else {
      Throw new \Exception("UUID (".$current.") not set");
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

  private static function addCGSpacePublication($item) {

    try {
      $data = [
        'type' => 'cgspace_publication',
        'title' => substr($item['name'], 0, 255),
        'uid'   => 1,
        'status'  => true,
        'langcode'  => 'und',
        'field_cg_uuid' => $item['uuid'],
      ];

      if(isset($item['metadata']['dc.contributor.author'][0]['value'])) {
        $data += [
          'field_cg_authors' => $item['metadata']['dc.contributor.author'][0]['value']
        ];
      }

      if(isset($item['metadata']['dcterms.abstract'][0]['value'])) {
        $data += [
          'body' => [
            'value' => $item['metadata']['dcterms.abstract'][0]['value'],
            'format' => 'filtered_html'
          ]
        ];
      }

      if(isset($item['metadata']['dcterms.issued'][0]['value'])) {
        $data += [
          'field_cg_published_on' => $item['metadata']['dcterms.issued'][0]['value'],
          'field_cg_published_on_date' => self::processAuthoredOnDate($item['metadata']['dcterms.issued'][0]['value'])
        ];

        //field_cg_publication_year_ref
        if (!empty($term = self::processPublicationYear(trim(substr($item['metadata']['dcterms.issued'][0]['value'], 0, 4)),'cgspace_publication_year', true))) {
          $data += [
            'field_cg_publication_year_ref' => [
              'target_id' => $term->id(),
            ]
          ];
        }
      }

      if(isset($item['metadata']['dcterms.publisher'][0]['value'])) {
        $data += [
          'field_cg_publisher' => $item['metadata']['dcterms.publisher'][0]['value']
        ];
      }

      if(isset($item['metadata']['dcterms.bibliographicCitation'][0]['value'])) {
        $data += [
          'field_cg_citation' => $item['metadata']['dcterms.bibliographicCitation'][0]['value']
        ];
      }

      if(isset($item['metadata']['dcterms.isPartOf'][0]['value'])) {
        $data += [
          'field_cg_series' => $item['metadata']['dcterms.isPartOf'][0]['value']
        ];
      }

      if(isset($item['metadata']['dcterms.accessRights'][0]['value'])) {
        $data += [
          'field_cg_access_rights' => $item['metadata']['dcterms.accessRights'][0]['value']
        ];
      }

      if(isset($item['metadata']['dc.identifier.uri'][0]['value'])) {
        $data += [
          'field_cg_permanent_link' => [
            'uri' => $item['metadata']['dc.identifier.uri'][0]['value']
          ]
        ];
      }

      if(isset($item['handle'])) {
        $data += [
          'field_cg_handle' => $item['handle']
        ];
      }

      if(isset($item['metadata']['cg.identifier.doi'][0]['value'])) {
        $data += [
          'field_cg_doi' => str_replace(['http://dx.doi.org/', 'http://dx.doi.org/'], '', $item['metadata']['cg.identifier.doi'][0]['value']),
          'field_cg_link_journal' => [
            'uri' => $item['metadata']['dc.identifier.uri'][0]['value']
          ]
        ];
      }


      if(isset($item['metadata']['cg.identifier.url'][0]['value'])) {
        $data += [
          'field_cg_link_document' => [
            'uri' => $item['metadata']['cg.identifier.url'][0]['value']
          ]
        ];
      }

      if(isset($item['attachment']['uri'])) {
        $data += [
          'field_cg_download_link' => [
            'uri' => $item['attachment']['uri'],
            'title' => $item['attachment']['name'] ?? '',
          ]
        ];
      }

      if(isset($item['metadata']['cg.coverage.country'][0]['value'])) {
        $countries = [];
        foreach($item['metadata']['cg.coverage.country'] as $country) {
          array_push($countries, self::processCountries($country['value']));
        }

        $data += [
          'field_cg_countries' => $countries
        ];
      }

      if(isset($item['picture']['name']) && isset($item['picture']['uri'])) {
        $file = self::processCover($item['picture']['name'], $item['picture']['uri']);

        if ($file instanceof File) {

          $data += [
            'field_cg_image' => [
              'target_id' => $file->id(),
              'alt' => $item['name'],
              'title' => $item['name'],
            ]
          ];
        }
      }

      if(isset($item['metadata']['cg.contributor.initiative'])) {
        $initiatives = [];
        foreach($item['metadata']['cg.contributor.initiative'] as $initiative) {
          $term = self::processTaxonomyTerm($initiative['value'], 'cgspace_research_initiatives', 'cgspace_importer.settings.processors.research_initiatives');
          if($term instanceof Term) {
            $initiatives[] = [
              'target_id' => $term->id(),
            ];
          }
        }
        if(!empty($initiatives)) {
          $data += ['field_cg_initiatives_ref' => $initiatives];
        }
      }

      if(isset($item['metadata']['cg.subject.ilri'])) {
        $subjects = [];
        $tags = [];
        foreach($item['metadata']['cg.subject.ilri'] as $subject) {
          $term = self::processTaxonomyTerm($subject['value'], 'cgspace_impact_areas', 'cgspace_importer.settings.processors.impact_areas');
          if($term instanceof Term) {
            $subjects[] = [
              'target_id' => $term->id(),
            ];
          }
          $term = self::processTaxonomyTerm($subject['value'], 'cgspace_tags', 'cgspace_importer.settings.processors.tags');
          if($term instanceof Term) {
            $tags[] = [
              'target_id' => $term->id(),
            ];
          }
        }
        if(!empty($subjects)) {
          $data += ['field_cg_impact_areas_ref' => $subjects];
        }
        if(!empty($tags)) {
          $data += ['field_cg_tags_ref' => $tags];
        }
      }

      $node = Node::create($data);

      $node->save();
    }
    catch(EntityStorageException $ex) {
      \Drupal::messenger()->addError(
        t("Unable to save Item @item. @error",
        [
          '@item' => $item['uuid'],
          '@error' => $ex->getMessage()
        ])
      );
    }

  }

  private static function updateCGSpacePublication(NodeInterface $node, array $item) {
    try {
      $node->set('field_cg_uuid', $item['uuid']);
      $node->set('title', substr($item['name'], 0, 255));
      $node->set('uid', '1');
      $node->set('status', true);
      $node->set('langcode', 'und');

      if(isset($item['metadata']['dc.contributor.author'][0]['value'])) {
        $node->set('field_cg_authors', $item['metadata']['dc.contributor.author'][0]['value']);
      }

      if(isset($item['metadata']['dcterms.abstract'][0]['value'])) {
        $body = [
          'value' => $item['metadata']['dcterms.abstract'][0]['value'],
          'format' => 'filtered_html'
        ];
        $node->set('body', $body);
      }

      if(isset($item['metadata']['dcterms.issued'][0]['value'])) {
        $node->set('field_cg_published_on', $item['metadata']['dcterms.issued'][0]['value']);
        $node->set('field_cg_published_on_date',self::processAuthoredOnDate($item['metadata']['dcterms.issued'][0]['value']));

        if (!empty($term = self::processPublicationYear(trim(substr($item['metadata']['dcterms.issued'][0]['value'], 0, 4)),'publication_year', true))) {
          $node->set('field_cg_publication_year_ref', ['target_id' => $term->id()]);
        }
      }

      if(isset($item['metadata']['dcterms.publisher'][0]['value'])) {
        $node->set('field_cg_publisher', $item['metadata']['dcterms.publisher'][0]['value']);
      }

      if(isset($item['metadata']['dcterms.bibliographicCitation'][0]['value'])) {
        $node->set('field_cg_citation', $item['metadata']['dcterms.bibliographicCitation'][0]['value']);
      }

      if(isset($item['metadata']['dcterms.isPartOf'][0]['value'])) {
        $node->set('field_cg_series', $item['metadata']['dcterms.isPartOf'][0]['value']);
      }

      if(isset($item['metadata']['dcterms.accessRights'][0]['value'])) {
        $node->set('field_cg_access_rights', $item['metadata']['dcterms.accessRights'][0]['value']);
      }

      if(isset($item['metadata']['dc.identifier.uri'][0]['value'])) {
        $node->set('field_cg_permanent_link', ['uri' => $item['metadata']['dc.identifier.uri'][0]['value']]);
      }

      if(isset($item['handle'])) {
        $node->set('field_cg_handle', $item['handle']);
      }

      if(isset($item['metadata']['cg.identifier.doi'][0]['value'])) {
        $node->set('field_cg_doi', str_replace(['http://dx.doi.org/', 'http://dx.doi.org/'], '', $item['metadata']['cg.identifier.doi'][0]['value']));
        $node->set('field_cg_link_journal', ['uri' => $item['metadata']['cg.identifier.doi'][0]['value']]);
      }

      if(isset($item['metadata']['cg.identifier.url'][0]['value'])) {
        $node->set('field_cg_link_document', ['uri' => $item['metadata']['cg.identifier.url'][0]['value']]);
      }

      if(isset($item['attachment']['uri'])) {
        $node->set('field_cg_download_link', [
          'uri' => $item['attachment']['uri'],
          'title' => $item['attachment']['name'] ?? '',
          ]
        );
      }

      if(isset($item['metadata']['cg.coverage.country'])) {
        $countries = [];
        foreach($item['metadata']['cg.coverage.country'] as $country) {
          array_push($countries, self::processCountries($country['value']));
        }
        $node->set('field_cg_countries', $countries);
      }

      if(isset($item['picture']['name']) && isset($item['picture']['uri'])) {

        $file = self::processCover($item['picture']['name'], $item['picture']['uri']);

        if ($file instanceof File) {

          $node->set('field_cg_image', [
            'target_id' => $file->id(),
            'alt' => $item['name'],
            'title' => $item['name'],
          ]);
        }
      }


      if(isset($item['metadata']['cg.contributor.initiative'])) {
        $initiatives = [];
        foreach($item['metadata']['cg.contributor.initiative'] as $initiative) {
          $term = self::processTaxonomyTerm($initiative['value'], 'cgspace_research_initiatives', 'cgspace_importer.settings.processors.research_initiatives');
          if($term instanceof Term) {
            $initiatives[] = [
              'target_id' => $term->id(),
            ];
          }
        }
        if(!empty($initiatives)) {
          $node->set('field_cg_initiatives_ref', $initiatives);
        }
      }

      if(isset($item['metadata']['cg.subject.ilri'])) {
        $subjects = [];
        $tags = [];
        foreach($item['metadata']['cg.subject.ilri'] as $subject) {
          $term = self::processTaxonomyTerm($subject['value'], 'cgspace_impact_areas', 'cgspace_importer.settings.processors.impact_areas');
          if($term instanceof Term) {
            $subjects[] = [
              'target_id' => $term->id(),
            ];
          }
          $term = self::processTaxonomyTerm($subject['value'], 'cgspace_tags', 'cgspace_importer.settings.processors.tags');
          if($term instanceof Term) {
            $tags[] = [
              'target_id' => $term->id(),
            ];
          }
        }
        if(!empty($subjects)) {
          $node->set('field_cg_impact_areas_ref', $subjects);
        }
        if(!empty($tags)) {
          $node->set('field_cg_tags_ref', $tags);
        }
      }

      $node->save();

    } catch(EntityStorageException $ex) {
      \Drupal::messenger()->addError(
        t("Unable to update Item @item. @error",
          [
            '@item' => $item['uuid'],
            '@error' => $ex->getMessage()
          ])
      );
    }
  }

  private static function processAuthoredOnDate($value) {
    $result = '';

    $date_parts = explode('-', $value);

    if (count($date_parts) === 1) {
      //we have only year
      $value .= '-01-01';
    }
    if (count($date_parts) === 2) {
      //we have year and month
      $value .= '-01';
    }

    $date = \DateTimeImmutable::createFromFormat("Y-m-d", $value);

    if($date !== false) {
      $format = 'Y-m-d';
      $result = $date->format($format);
    }

    return $result;
  }

  private static function processCountries($name) {
    $country_code = '';
    $countries = $countries = [
      'AC' => 'Ascension Island',
      'AD' => 'Andorra',
      'AE' => 'United Arab Emirates',
      'AF' => 'Afghanistan',
      'AG' => 'Antigua & Barbuda',
      'AI' => 'Anguilla',
      'AL' => 'Albania',
      'AM' => 'Armenia',
      'AN' => 'Netherlands Antilles',
      'AO' => 'Angola',
      'AQ' => 'Antarctica',
      'AR' => 'Argentina',
      'AS' => 'American Samoa',
      'AT' => 'Austria',
      'AU' => 'Australia',
      'AW' => 'Aruba',
      'AX' => 'Åland Islands',
      'AZ' => 'Azerbaijan',
      'BA' => 'Bosnia & Herzegovina',
      'BB' => 'Barbados',
      'BD' => 'Bangladesh',
      'BE' => 'Belgium',
      'BF' => 'Burkina Faso',
      'BG' => 'Bulgaria',
      'BH' => 'Bahrain',
      'BI' => 'Burundi',
      'BJ' => 'Benin',
      'BL' => 'St. Barthélemy',
      'BM' => 'Bermuda',
      'BN' => 'Brunei',
      'BO' => 'Bolivia',
      'BQ' => 'Caribbean Netherlands',
      'BR' => 'Brazil',
      'BS' => 'Bahamas',
      'BT' => 'Bhutan',
      'BV' => 'Bouvet Island',
      'BW' => 'Botswana',
      'BY' => 'Belarus',
      'BZ' => 'Belize',
      'CA' => 'Canada',
      'CC' => 'Cocos (Keeling) Islands',
      'CD' => 'Congo - Kinshasa',
      'CF' => 'Central African Republic',
      'CG' => 'Congo - Brazzaville',
      'CH' => 'Switzerland',
      'CI' => 'Côte d’Ivoire',
      'CK' => 'Cook Islands',
      'CL' => 'Chile',
      'CM' => 'Cameroon',
      'CN' => 'China',
      'CO' => 'Colombia',
      'CP' => 'Clipperton Island',
      'CR' => 'Costa Rica',
      'CU' => 'Cuba',
      'CV' => 'Cape Verde',
      'CW' => 'Curaçao',
      'CX' => 'Christmas Island',
      'CY' => 'Cyprus',
      'CZ' => 'Czechia',
      'DE' => 'Germany',
      'DG' => 'Diego Garcia',
      'DJ' => 'Djibouti',
      'DK' => 'Denmark',
      'DM' => 'Dominica',
      'DO' => 'Dominican Republic',
      'DZ' => 'Algeria',
      'EA' => 'Ceuta & Melilla',
      'EC' => 'Ecuador',
      'EE' => 'Estonia',
      'EG' => 'Egypt',
      'EH' => 'Western Sahara',
      'ER' => 'Eritrea',
      'ES' => 'Spain',
      'ET' => 'Ethiopia',
      'FI' => 'Finland',
      'FJ' => 'Fiji',
      'FK' => 'Falkland Islands',
      'FM' => 'Micronesia',
      'FO' => 'Faroe Islands',
      'FR' => 'France',
      'GA' => 'Gabon',
      'GB' => 'United Kingdom',
      'GD' => 'Grenada',
      'GE' => 'Georgia',
      'GF' => 'French Guiana',
      'GG' => 'Guernsey',
      'GH' => 'Ghana',
      'GI' => 'Gibraltar',
      'GL' => 'Greenland',
      'GM' => 'Gambia',
      'GN' => 'Guinea',
      'GP' => 'Guadeloupe',
      'GQ' => 'Equatorial Guinea',
      'GR' => 'Greece',
      'GS' => 'South Georgia & South Sandwich Islands',
      'GT' => 'Guatemala',
      'GU' => 'Guam',
      'GW' => 'Guinea-Bissau',
      'GY' => 'Guyana',
      'HK' => 'Hong Kong SAR China',
      'HM' => 'Heard & McDonald Islands',
      'HN' => 'Honduras',
      'HR' => 'Croatia',
      'HT' => 'Haiti',
      'HU' => 'Hungary',
      'IC' => 'Canary Islands',
      'ID' => 'Indonesia',
      'IE' => 'Ireland',
      'IL' => 'Israel',
      'IM' => 'Isle of Man',
      'IN' => 'India',
      'IO' => 'British Indian Ocean Territory',
      'IQ' => 'Iraq',
      'IR' => 'Iran',
      'IS' => 'Iceland',
      'IT' => 'Italy',
      'JE' => 'Jersey',
      'JM' => 'Jamaica',
      'JO' => 'Jordan',
      'JP' => 'Japan',
      'KE' => 'Kenya',
      'KG' => 'Kyrgyzstan',
      'KH' => 'Cambodia',
      'KI' => 'Kiribati',
      'KM' => 'Comoros',
      'KN' => 'St. Kitts & Nevis',
      'KP' => 'North Korea',
      'KR' => 'South Korea',
      'KW' => 'Kuwait',
      'KY' => 'Cayman Islands',
      'KZ' => 'Kazakhstan',
      'LA' => 'Laos',
      'LB' => 'Lebanon',
      'LC' => 'St. Lucia',
      'LI' => 'Liechtenstein',
      'LK' => 'Sri Lanka',
      'LR' => 'Liberia',
      'LS' => 'Lesotho',
      'LT' => 'Lithuania',
      'LU' => 'Luxembourg',
      'LV' => 'Latvia',
      'LY' => 'Libya',
      'MA' => 'Morocco',
      'MC' => 'Monaco',
      'MD' => 'Moldova',
      'ME' => 'Montenegro',
      'MF' => 'St. Martin',
      'MG' => 'Madagascar',
      'MH' => 'Marshall Islands',
      'MK' => 'North Macedonia',
      'ML' => 'Mali',
      'MM' => 'Myanmar (Burma)',
      'MN' => 'Mongolia',
      'MO' => 'Macao SAR China',
      'MP' => 'Northern Mariana Islands',
      'MQ' => 'Martinique',
      'MR' => 'Mauritania',
      'MS' => 'Montserrat',
      'MT' => 'Malta',
      'MU' => 'Mauritius',
      'MV' => 'Maldives',
      'MW' => 'Malawi',
      'MX' => 'Mexico',
      'MY' => 'Malaysia',
      'MZ' => 'Mozambique',
      'NA' => 'Namibia',
      'NC' => 'New Caledonia',
      'NE' => 'Niger',
      'NF' => 'Norfolk Island',
      'NG' => 'Nigeria',
      'NI' => 'Nicaragua',
      'NL' => 'Netherlands',
      'NO' => 'Norway',
      'NP' => 'Nepal',
      'NR' => 'Nauru',
      'NU' => 'Niue',
      'NZ' => 'New Zealand',
      'OM' => 'Oman',
      'PA' => 'Panama',
      'PE' => 'Peru',
      'PF' => 'French Polynesia',
      'PG' => 'Papua New Guinea',
      'PH' => 'Philippines',
      'PK' => 'Pakistan',
      'PL' => 'Poland',
      'PM' => 'St. Pierre & Miquelon',
      'PN' => 'Pitcairn Islands',
      'PR' => 'Puerto Rico',
      'PS' => 'Palestinian Territories',
      'PT' => 'Portugal',
      'PW' => 'Palau',
      'PY' => 'Paraguay',
      'QA' => 'Qatar',
      'QO' => 'Outlying Oceania',
      'RE' => 'Réunion',
      'RO' => 'Romania',
      'RS' => 'Serbia',
      'RU' => 'Russia',
      'RW' => 'Rwanda',
      'SA' => 'Saudi Arabia',
      'SB' => 'Solomon Islands',
      'SC' => 'Seychelles',
      'SD' => 'Sudan',
      'SE' => 'Sweden',
      'SG' => 'Singapore',
      'SH' => 'St. Helena',
      'SI' => 'Slovenia',
      'SJ' => 'Svalbard & Jan Mayen',
      'SK' => 'Slovakia',
      'SL' => 'Sierra Leone',
      'SM' => 'San Marino',
      'SN' => 'Senegal',
      'SO' => 'Somalia',
      'SR' => 'Suriname',
      'SS' => 'South Sudan',
      'ST' => 'São Tomé & Príncipe',
      'SV' => 'El Salvador',
      'SX' => 'Sint Maarten',
      'SY' => 'Syria',
      'SZ' => 'Eswatini',
      'TA' => 'Tristan da Cunha',
      'TC' => 'Turks & Caicos Islands',
      'TD' => 'Chad',
      'TF' => 'French Southern Territories',
      'TG' => 'Togo',
      'TH' => 'Thailand',
      'TJ' => 'Tajikistan',
      'TK' => 'Tokelau',
      'TL' => 'Timor-Leste',
      'TM' => 'Turkmenistan',
      'TN' => 'Tunisia',
      'TO' => 'Tonga',
      'TR' => 'Turkey',
      'TT' => 'Trinidad & Tobago',
      'TV' => 'Tuvalu',
      'TW' => 'Taiwan',
      'TZ' => 'Tanzania',
      'UA' => 'Ukraine',
      'UG' => 'Uganda',
      'UM' => 'U.S. Outlying Islands',
      'US' => 'United States',
      'UY' => 'Uruguay',
      'UZ' => 'Uzbekistan',
      'VA' => 'Vatican City',
      'VC' => 'St. Vincent & Grenadines',
      'VE' => 'Venezuela',
      'VG' => 'British Virgin Islands',
      'VI' => 'U.S. Virgin Islands',
      'VN' => 'Vietnam',
      'VU' => 'Vanuatu',
      'WF' => 'Wallis & Futuna',
      'WS' => 'Samoa',
      'XK' => 'Kosovo',
      'YE' => 'Yemen',
      'YT' => 'Mayotte',
      'ZA' => 'South Africa',
      'ZM' => 'Zambia',
      'ZW' => 'Zimbabwe',
    ];

    // Sort the list.
    natcasesort($countries);

    foreach($countries as $key => $value) {
      if($value === $name) {
        $country_code = $key;
      }
    }
    return $country_code;
  }

  private static function processPublicationYear($name, $vocabulary, $create=false) {
    $properties = [
      'name' => $name,
      'vid'  => $vocabulary
    ];

    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties($properties);

    $term = reset($terms);
    if (!empty($term)) {
      return $term;
    }

    if($create) {
      $term = Term::create($properties);
      $term->save();

      return $term;
    }

    return null;
  }

  private static function processCover($filename, $uri) {

    if(strlen($filename) > self::MAX_FILENAME_LENGTH) {
      \Drupal::messenger()->addError(
        t("Filename too long for @file. Max length is @length",
          [
            '@file' => $filename,
            '@length' => self::MAX_FILENAME_LENGTH
          ]
        ));

      $filename = substr($filename, 0, self::MAX_FILENAME_LENGTH);
    }

    $destination = 'public://publication-covers/'.$filename;

    try {
      $data = \Drupal::httpClient()->get($uri)->getBody();

      $file = \Drupal::service('file.repository')->writeData($data, $destination, FileExists::Replace);

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

  private static function processTaxonomyTerm($name, $vocabulary, $configuration) {
    //get configuration
    $config = \Drupal::configFactory()->getEditable($configuration);

    if (empty($name)) {
      return NULL;
    }
    // Attempt to fetch an existing term.
    $properties = [];

    //create term on research initiatives vocabulary
    if((string) $config->get('create') == '1') {
      $properties['vid'] = $vocabulary;
      $properties['name'] = $name;

      //check if the term already exists and return it
      $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties($properties);
      $term = reset($terms);
      if (empty($term)) {
        //if it doesn't exist create it
        $term = Term::create($properties);
        try {
          $term->save();
        }
        catch (EntityStorageException $exception) {
          \Drupal::messenger()->addError(
            t("Error saving Term @term: @message",
              [
                '@term' => $term->getName(),
                '@message' => $exception->getMessage()
              ]
            ));
        }
      }

      return $term;
    }
    //try to map term on existing vocabulary
    else {
      $properties['name'] = $name;
      $properties['vid'] = $config->get('vocabulary');

      $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties($properties);
      $term = reset($terms);
      if (!empty($term)) {
        return $term;
      }
    }

    return NULL;
  }

  private static function getProxy() {
    $configFactory = \Drupal::service('config.factory');
    $httpClient = \Drupal::service('http_client');
    $endpoint = $configFactory->get('cgspace_importer.settings.general')->get('endpoint');

    return new CGSpaceProxy($endpoint, $configFactory, $httpClient);
  }
}
