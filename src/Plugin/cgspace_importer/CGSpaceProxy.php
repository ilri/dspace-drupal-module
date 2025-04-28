<?php

namespace Drupal\cgspace_importer\Plugin\cgspace_importer;

use Drupal\Core\StringTranslation\StringTranslationTrait;

Class CGSpaceProxy extends CGSpaceProxyBase {

  use StringTranslationTrait;

  public function getCommunities(): array {
    $result = array();

    try {
      $communities = $this->getData($this->endpoint . '/server/api/core/communities/search/top?size=1000');

      $xml = new \SimpleXMLElement($communities);

      foreach ($xml->_embedded->communities as $community) {
        $result[(string)$community->uuid] = (string)$community->name . ' <strong>(' . (string)$community->archivedItemsCount . ')</strong>';
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

      $xml = new \SimpleXMLElement($communities);

      foreach ($xml->_embedded->subcommunities as $community) {
        if(!empty($community))
          $result[(string)$community->uuid] = (string)$community->name . ' <strong>(' . (string)$community->archivedItemsCount . ')</strong>';
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

      $xml = new \SimpleXMLElement($collections);
      //extract communities result array
      foreach ($xml->_embedded->collections as $collection) {
        $result[(string)$collection->uuid] = (string)$collection->name . ' <strong>(' . $collection->archivedItemsCount . ')</strong> ' . $this->formatPlural((string)$collection->archivedItemsCount, t('item'), t('items'));
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

      $xml = new \SimpleXMLElement($community);

      $result = (string)$xml->name;
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

    $xml = new \SimpleXMLElement($collection);

    return (string) $xml->archivedItemsCount;
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
  private function getPagedItems($collection, $number_items, $offset=0, $result = []) {
    $items = $this->getData("$this->endpoint/server/api/core/collections/$collection/mappedItems?size=100&offset=$offset");
    $xml = new \SimpleXMLElement($items);

    foreach ($xml->_embedded->mappedItems as $item) {
      $result[] = (string)$item->uuid;
    }

    if($offset+100 < $number_items) {
    //if($xml->item->count() === 100) {
      return $this->getPagedItems($collection, $number_items, $offset+100, $result);
    }

    return $result;

  }

  public function getItem($item): string {
    // remove XML header
    return $this->getData("$this->endpoint/server/api/core/items/$item?embed=*");
  }


}
