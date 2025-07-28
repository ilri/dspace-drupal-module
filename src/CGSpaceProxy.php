<?php

namespace Drupal\cgspace_importer;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

Class CGSpaceProxy extends CGSpaceProxyBase {

  use StringTranslationTrait;



  public function countCollectionItems($collection_uuid, $searchQuery = ''): int {

    $num_items = 0;

    try {
      $query = [
        "query" => [
          "scope" => $collection_uuid,
          "dsoType" => "item",
          "size" => 1,
          "page" => 0
        ]
      ];

      if(!empty($searchQuery)) {
        $query["query"] += ["query" => $searchQuery];
      }

      $url = Url::fromUri("$this->endpoint/server/api/discover/search/objects", $query);

      $result = $this->getData($url->toString());

      if(isset($result['_embedded']['searchResult']['page']['totalElements'])) {
        $num_items = $result['_embedded']['searchResult']['page']['totalElements'];
      }
    }
    catch(\Exception $ex) {
      \Drupal::logger('cgspace_importer')->error(
        t("Error getting number of items for collection @collection: @message", [
            "@collection" => $collection_uuid,
            "@message" => $ex->getMessage(),
          ]
        )
      );
    }

    return $num_items;
  }


  public function getCommunities(): array {
    $result = array();

    try {
      $communities = $this->getData($this->endpoint . '/server/api/core/communities/search/top?size=100');

      foreach ($communities["_embedded"]["communities"] as $community) {
        $result[(string)$community["uuid"]] = (string)$community["name"] . ' <strong>(' . (string)$community["archivedItemsCount"] . ')</strong>';
      }
    }
    catch(\Exception $ex) {
      \Drupal::logger('cgspace_importer')->error(
        t("Error getting communities: @message", [
            "@message" => $ex->getMessage(),
          ]
        )
      );
    }

    return $result;
  }

  public function getSubCommunities($community): array {
    $result = array();

    try {
      $communities = $this->getData("$this->endpoint/server/api/core/communities/$community/subcommunities?size=100");

      foreach ($communities["_embedded"]["subcommunities"] as $subCommunity) {
        if(!empty($subCommunity))
          $result[(string)$subCommunity["uuid"]] = (string)$subCommunity["name"] . ' <strong>(' . (string)$subCommunity["archivedItemsCount"] . ')</strong>';
      }
    }
    catch(\Exception $ex) {
      \Drupal::logger('cgspace_importer')->error(
        t("Error getting sub-communities for community @community: @message", [
            "@community" => $community,
            "@message" => $ex->getMessage(),
          ]
        )
      );
    }

    return $result;
  }

  public function getCollections($community): array {

    $result = array();

    try {
      $collections = $this->getData("$this->endpoint/server/api/core/communities/$community/collections?size=1000");

      //extract communities result array
      foreach ($collections["_embedded"]["collections"] as $collection) {
        $result[(string)$collection["uuid"]] = (string)$collection["name"] . ' <strong>(' . $collection["archivedItemsCount"] . ')</strong> ' . $this->formatPlural((string)$collection["archivedItemsCount"], t('item'), t('items'));
      }
    }
    catch(\Exception $ex) {
      \Drupal::logger('cgspace_importer')->error(
        t("Error getting collections for community @community: @message", [
            "@community" => $community,
            "@message" => $ex->getMessage(),
          ]
        )
      );
    }

    return $result;
  }

  public function getCommunityName($community): string {


    $result = \Drupal::config("cgspace_importer.settings.communities")->get($community);

    if(is_null($result)) {

      try {
        $community = $this->getData($this->endpoint . '/server/api/core/communities/' . $community);


        $result = (string)$community["name"];
      } catch (\Exception $ex) {
        \Drupal::logger('cgspace_importer')->error(
          t("Error getting name for community @community: @message", [
              "@community" => $community,
              "@message" => $ex->getMessage(),
            ]
          )
        );
      }

    }

    return $result;

  }

  public function getPagedItemsByQuery($collection, $searchQuery='', $page=0, $result=[]) {

    $query = [
      "query" => [
        "scope" => $collection,
        "dsoType" => "item",
        "size" => \Drupal::config('cgspace_importer.settings.general')->get('page_size'),
        "page" => $page
      ]
    ];
    if(!empty($searchQuery)) {
      $query["query"] += ["query" => $searchQuery];
    }

    $url = Url::fromUri("$this->endpoint/server/api/discover/search/objects", $query);

    $items = $this->getData($url->toString());

    foreach ($items['_embedded']['searchResult']['_embedded']['objects'] as $item) {
      if(isset($item['_embedded']['indexableObject']['uuid'])) {
        $result[] = $item['_embedded']['indexableObject']['uuid'];
      }
    }

    return $result;
  }

  public function getItem($item): array
  {
    // remove XML header
    $result = $this->getData("$this->endpoint/server/api/core/items/$item?embed=bundles/bitstreams,mappedCollections/parentCommunity");

    try {
      $result = $this->getItemBitstreams($result);
    }
    catch (\Exception $ex) {
      \Drupal::logger('cgspace_importer')->error(
        t("Error getting item @item: @message", [
            "@message" => $ex->getMessage(),
            "@collection" => $item
          ]
        )
      );
    }

    return $result;
  }

  /**
   * Searches for bitstream Thumbnail and PDF and add them to item as root elements to avoid not supported jsonpath expressions no migrate importer
   * @param array $item
   * the item associative array
   * @return array
   * the item associative array with picture and attachment root elements
   */
  private function getItemBitstreams(array $item): array {

    if(isset($item['_embedded']['bundles']['_embedded']['bundles'])) {
      foreach($item['_embedded']['bundles']['_embedded']['bundles'] as $bundle) {
        if(isset($bundle['_embedded']['bitstreams']['_embedded']['bitstreams'])) {
          foreach($bundle['_embedded']['bitstreams']['_embedded']['bitstreams'] as $bitstream) {
            if(isset($bitstream['bundleName']) && ($bitstream['bundleName'] === 'ORIGINAL')) {
              if(isset($bitstream['_links']['thumbnail']['href'])) {

                $thumbnail = $this->getDataBitstream($bitstream['_links']['thumbnail']['href']);
                if(!empty($thumbnail)) {
                  if (isset($thumbnail['_links']['content']['href'])) {
                    $item['picture'] = [
                      'name' => $thumbnail['name'],
                      'uri' => $thumbnail['_links']['content']['href']
                    ];
                  }
                }
              }

              if(isset($bitstream['_links']['content']['href'])) {
                $item['attachment'] = [
                  'name' => $bitstream['name'],
                  'uri' => $bitstream['_links']['content']['href']
                ];
              }
            }
          }
        }
      }
    }

    return $item;
  }


}
