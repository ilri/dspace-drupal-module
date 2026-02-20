<?php

namespace Drupal\cgspace_importer;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

Class CGSpaceProxy extends CGSpaceProxyBase {

  use StringTranslationTrait;

  /**
   * Calls the CGSpace discover API making the query for just 1 item
   * for the passed collection UUID and returns the total amount of items
   *
   * @param string $collection_uuid
   * The collection UUID
   * @param string $searchQuery
   * The search query for CGSpace discover API
   * @return int
   * the total amount of items returned by the CGSpace API
   */
  public function countCollectionItems(string $collection_uuid, string $searchQuery = ''): int {

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

      $result = $this->getJsonData($url->toString());

      if(isset($result['_embedded']['searchResult']['page']['totalElements'])) {
        return $result['_embedded']['searchResult']['page']['totalElements'];
      }


    }
    catch(\Exception $ex) {
      $this->logger->error(
        t("Error getting number of items for collection @collection: @message", [
            "@collection" => $collection_uuid,
            "@message" => $ex->getMessage(),
          ]
        )
      );
    }

    return 0;
  }

  /**
   * Get the list of communities from CGSpace API
   * and returns it as an array indexed by UUID ready for creating FormApi Checkboxes
   *
   * @return array
   * an array of communities by uuids => name
   */
  public function getCommunities(): array {
    $result = array();

    try {
      $communities = $this->getJsonData($this->endpoint . '/server/api/core/communities/search/top?size=100');

      foreach ($communities["_embedded"]["communities"] as $community) {
        $result[(string)$community["uuid"]] = (string)$community["name"] . ' <strong>(' . (string)$community["archivedItemsCount"] . ')</strong>';
      }
    }
    catch(\Exception $ex) {
      $this->logger->error(
        t("Error getting communities: @message", [
            "@message" => $ex->getMessage(),
          ]
        )
      );
    }

    return $result;
  }

  /**
   * Recursive function to get subcommunities from CGSpace API
   *
   * @param string $community
   * the parent community UUID
   * @return array
   * the subcommunities array indexed by UUID
   */
  public function getSubCommunities(string $community): array {
    $result = [];

    try {
      $communities = $this->getJsonData("$this->endpoint/server/api/core/communities/$community/subcommunities?size=100");

      foreach ($communities["_embedded"]["subcommunities"] as $subCommunity) {
        if(!empty($subCommunity))
          $result[(string)$subCommunity["uuid"]] = (string)$subCommunity["name"] . ' <strong>(' . (string)$subCommunity["archivedItemsCount"] . ')</strong>';
      }
    }
    catch(\Exception $ex) {
      $this->logger->error(
        t("Error getting sub-communities for community @community: @message", [
            "@community" => $community,
            "@message" => $ex->getMessage(),
          ]
        )
      );
    }

    return $result;
  }

  /**
   * Get the list of collections for a passed community from CGSpace API
   * and returns it as an indexed by UUID array ready for FormAPI checkboxes
   *
   * @param string $community
   * The community UUID
   * @return array
   * The array of collections indexed by UUID ready for FormAPI Checkboxes
   */
  public function getCollections(string $community): array {

    $result = [];

    try {
      $collections = $this->getJsonData("$this->endpoint/server/api/core/communities/$community/collections?size=1000");

      //extract communities result array
      foreach ($collections["_embedded"]["collections"] as $collection) {
        $result[(string)$collection["uuid"]] = (string)$collection["name"] . ' <strong>(' . $collection["archivedItemsCount"] . ')</strong> ' . $this->formatPlural((string)$collection["archivedItemsCount"], t('item'), t('items'));
      }
    }
    catch(\Exception $ex) {
      $this->logger->error(
        t("Error getting collections for community @community: @message", [
            "@community" => $community,
            "@message" => $ex->getMessage(),
          ]
        )
      );
    }

    return $result;
  }

  /**
   * Get the community name from UUID using the CGSpace API if it's not available in cache
   *
   * @param string $community
   * the CGSpace Community UUID
   * @return string
   * The CGSpace Community name
   */
  public function getCommunityName(string $community): string {


    $result = \Drupal::config("cgspace_importer.settings.communities")->get($community);

    if(is_null($result)) {

      try {
        $community = $this->getJsonData($this->endpoint . '/server/api/core/communities/' . $community);


        $result = (string)$community["name"];
      } catch (\Exception $ex) {
        $this->logger->error(
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

  /**
   * Build the query for CGSpace discover API from passed parameters
   * and returns the Json result
   *
   * @param $collection
   * The optional collection UUID user for scope
   * @param $searchQuery
   * The optional API URL parameter query
   * @param $page
   * The optional page index number for the query
   * @param $result
   * The result array with returned data from API
   * @return array
   * The result array with returned data from API
   */
  public function getPagedItemsByQuery(string $collection='', string $searchQuery='', int $page=0, array $result=[]):array {

    $query = [
      "query" => [
        "dsoType" => "item",
        "size" => \Drupal::config('cgspace_importer.settings.general')->get('page_size'),
        "page" => $page
      ]
    ];
    if(!empty($collection)) {
      $query["query"] += ["scope" => $collection];
    }
    if(!empty($searchQuery)) {
      $query["query"] += ["query" => $searchQuery];
    }

    $url = Url::fromUri("$this->endpoint/server/api/discover/search/objects", $query);

    $items = $this->getJsonData($url->toString());

    foreach ($items['_embedded']['searchResult']['_embedded']['objects'] as $item) {
      if(isset($item['_embedded']['indexableObject']['uuid'])) {
        $result[] = $item['_embedded']['indexableObject']['uuid'];
      }
    }

    return $result;
  }

  /**
   * Load the item with UUID passed as argument from CGSpace API
   *
   * @param string $item
   * the item UUID to load
   * @return array
   * The full data structure returned from the CGSpace API in a PHP array
   */
  public function getItem(string $item):array
  {
    $result = [];
    try {
      $result = $this->getJsonData("$this->endpoint/server/api/core/items/$item?embed=bundles/bitstreams,owningCollection,mappedCollections/parentCommunity");
      $result = $this->getItemBitstreams($result);
      $result = $this->getItemCollectionsAndCommunities($result);
    }
    catch (\Exception $ex) {
      $this->logger->error(
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
   * Load the list of items from endpoint sitemap
   *
   * @return array
   * The array of items loaded from the sitemap index
   */
  public function getItemsFromSitemap():array {
    $result = [];

    try {
      $sitemap_index = $this->getXMLData("$this->endpoint/sitemap_index.xml");

      foreach($sitemap_index as $sitemap_page) {

        $sitemap = $this->getXMLData((string) $sitemap_page->loc);
        foreach($sitemap as $sitemap_item) {
          if (preg_match('#/items/([0-9a-f\-]+)$#i', (string) $sitemap_item->loc, $matches)) {
            $result[] = $matches[1];
          }
        }
      }
    } catch (\Exception $ex) {
      $this->logger->error(
        t("Error getting items @from sitemap: @message", [
            "@message" => $ex->getMessage(),
          ]
        )
      );
    }
    return $result;
  }

  /**
   * Searches for bitstream Thumbnail and PDF and add them to item as root elements to avoid not supported jsonpath expressions no migrate importer
   *
   * @param array $item
   * the item associative array
   * @return array
   * the item associative array with picture and attachment root elements
   */
  private function getItemBitstreams(array $item): array {

    try {
      if (isset($item['_embedded']['bundles']['_embedded']['bundles'])) {
        foreach ($item['_embedded']['bundles']['_embedded']['bundles'] as $bundle) {
          if (isset($bundle['_embedded']['bitstreams']['_embedded']['bitstreams'])) {
            foreach ($bundle['_embedded']['bitstreams']['_embedded']['bitstreams'] as $bitstream) {
              if (isset($bitstream['bundleName']) ) { //&& ($bitstream['bundleName'] === 'ORIGINAL')) {
                if (isset($bitstream['_links']['thumbnail']['href'])) {

                  $thumbnail = $this->getDataBitstream($bitstream['_links']['thumbnail']['href']);
                  if (!empty($thumbnail)) {
                    if (isset($thumbnail['_links']['content']['href'])) {
                      $item['picture'] = [
                        'name' => $thumbnail['name'],
                        'uri' => $thumbnail['_links']['content']['href']
                      ];
                    }
                  }
                }

                if (isset($bitstream['_links']['content']['href'])) {
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
    } catch (\Exception $ex) {
      print $ex->getMessage();
    }
    return $item;
  }

  private function getItemCollectionsAndCommunities(array $item): array {
    $item['collections'] = [];
    $item['communities'] = [];
    try {
      if(isset($item['_embedded']['owningCollection'])) {
        $item['collections'][] = $item['_embedded']['owningCollection']['name'];
        if(isset($item['_embedded']['owningCollection']['_embedded']['parentCommunity'])) {
          $item['communities'][] = $item['_embedded']['owningCollection']['_embedded']['parentCommunity']['name'];
        }
      }
      if (isset($item['_embedded']['mappedCollections']['_embedded']['mappedCollections'])) {
        foreach ($item['_embedded']['mappedCollections']['_embedded']['mappedCollections'] as $collection) {
          if (isset($collection['name'])) {
            if(!in_array($collection['name'], $item['collections'])) {
              $item['collections'][] = $collection['name'];
            }
            if(isset($collection['_embedded']['parentCommunity'])) {
              if(!in_array($collection['_embedded']['parentCommunity']['name'], $item['communities'])) {
                $item['communities'][] = $collection['_embedded']['parentCommunity']['name'];
              }
            }
          }
        }
      }
    } catch (\Exception $ex) {
      print $ex->getMessage();
    }
    return $item;
  }

}
