<?php

namespace Drupal\cgspace_importer\Plugin\cgspace_importer;

use Drupal\Core\StringTranslation\StringTranslationTrait;

Class CGSpaceProxy extends CGSpaceProxyBase {

  use StringTranslationTrait;

  public function getCommunities() {
    $result = array();

    try {
      $communities = $this->getData($this->endpoint . '/rest/communities/top-communities?limit=1000');

      $xml = new \SimpleXMLElement($communities);

      foreach ($xml->children() as $community) {
        $result[(string)$community->UUID] = (string)$community->name . ' <strong>(' . (string)$community->countItems . ')</strong>';
      }
    }
    catch(\Exception $ex) {
      print $ex->getMessage();
    }

    return $result;
  }

  public function getSubCommunities($community) {
    $result = array();

    try {
      $communities = $this->getData("$this->endpoint/rest/communities/$community/communities?limit=1000");

      $xml = new \SimpleXMLElement($communities);

      foreach ($xml->children() as $community) {
        $result[(string)$community->UUID] = (string)$community->name . ' <strong>(' . (string)$community->countItems . ')</strong>';
      }
    }
    catch(\Exception $ex) {
      print $ex->getMessage();
    }

    return $result;
  }

  public function getCollections($community) {

    $result = array();

    try {
      $collections = $this->getData("$this->endpoint/rest/communities/$community/collections?limit=1000");

      $xml = new \SimpleXMLElement($collections);

      //extract communities result array
      foreach ($xml->children() as $collection) {
        $result[(string)$collection->UUID] = (string)$collection->name . ' <strong>(' . $collection->numberItems . ')</strong> ' . $this->formatPlural((string)$collection->numberItems, t('item'), t('items'));
      }
    }
    catch(\Exception $ex) {
      print $ex->getMessage();
    }

    return $result;
  }

  public function getCommunityName($community) {

    $result = '';

    try {
      $community = $this->getData($this->endpoint . '/rest/communities/' . $community);

      $xml = new \SimpleXMLElement($community);

      $result = (string)$xml->name;
    }
    catch(\Exception $ex) {
      print $ex->getMessage();
    }

    return $result;

  }

  public function getCollectionNumberItems($collection) {
    $collection = $this->getData("$this->endpoint/rest/collections/$collection");

    $xml = new \SimpleXMLElement($collection);

    return (string) $xml->numberItems;
  }

  public function getItems($collection, $number_items) {

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

  private function getPagedItems($collection, $number_items, $offset=0, $result = []) {
    $items = $this->getData("$this->endpoint/rest/collections/$collection/items?limit=100&offset=$offset");
    $xml = new \SimpleXMLElement($items);

    foreach ($xml->children() as $item) {
      $result[] = (string)$item->UUID;
    }

    if($offset+100 < $number_items) {
    //if($xml->item->count() === 100) {
      return $this->getPagedItems($collection, $number_items, $offset+100, $result);
    }

    return $result;

  }

  public function getItem($item) {
    $xml = $this->getData("$this->endpoint/rest/items/$item?expand=all");
    // remove XML header
    return $xml;
  }


}
