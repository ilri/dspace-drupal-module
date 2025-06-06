<?php

namespace Drupal\cgspace_importer\Plugin\cgspace_importer;

use Drupal\Core\StringTranslation\StringTranslationTrait;

Class CGSpaceProxy extends CGSpaceProxyBase {

  use StringTranslationTrait;

  public function getCommunities(): array {
    $result = array();

    try {
      $communities = $this->getData($this->endpoint . '/server/api/core/communities/search/top?size=1000');

      foreach ($communities["_embedded"]["communities"] as $community) {
        $result[(string)$community["uuid"]] = (string)$community["name"] . ' <strong>(' . (string)$community["archivedItemsCount"] . ')</strong>';
      }
    }
    catch(\Exception $ex) {
      print $ex->getMessage();
    }

    return $result;
  }

  public function getSubCommunities($community): array {
    $result = array();

    try {
      $communities = $this->getData("$this->endpoint/server/api/core/communities/$community/subcommunities?size=1000");

      foreach ($communities["_embedded"]["subcommunities"] as $community) {
        if(!empty($community))
          $result[(string)$community["uuid"]] = (string)$community["name"] . ' <strong>(' . (string)$community["archivedItemsCount"] . ')</strong>';
      }
    }
    catch(\Exception $ex) {
      print $ex->getMessage();
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
      print $ex->getMessage();
    }

    return $result;
  }

  public function getCommunityName($community): string {

    $result = '';

    try {
      $community = $this->getData($this->endpoint . '/server/api/core/communities/' . $community);


      $result = (string) $community["name"];
    }
    catch(\Exception $ex) {
      print $ex->getMessage();
    }

    return $result;

  }

  /**
   * @throws \Exception
   */
  public function getCollectionNumberItems($collection): string {
    $collection = $this->getData("$this->endpoint/server/api/core/collections/$collection");

    return (string) $collection["archivedItemsCount"];
  }

  public function getItems($collection, $number_items): array {

    print "Listing items for collection $collection\n";

    $result = array();

    try {
      $items = $this->getPagedItems($collection, $number_items);
      $result = array_unique($items);
    }
    catch(\Exception $ex) {
      print $ex->getMessage();
    }

    return $result;
  }

  /**
   * @throws \Exception
   */
  private function getPagedItems($collection, $number_items, $page=0, $result = []) {
    $size = 100;
    $items = $this->getData("$this->endpoint/server/api/discover/search/objects?dsoType=item&scope=$collection&size=$size&page=$page");

    foreach ($items['_embedded']['searchResult']['_embedded']['objects'] as $item) {
      $result[] = $item['_embedded']['indexableObject']['uuid'];
    }

    if(count($result) < $number_items && count($items['_embedded']['searchResult']['_embedded']['objects']) === $size) {
      return $this->getPagedItems($collection, $number_items, $page+1, $result);
    }

    return $result;

  }

  public function getItem($item): array {
    // remove XML header
    $item = $this->getData("$this->endpoint/server/api/core/items/$item?embed=bundles/bitstreams,mappedCollections/parentCommunity");
    return $this->getItemBitstreams($item);
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
