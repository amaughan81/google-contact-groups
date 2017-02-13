<?php

namespace amaughan81;

use amaughan81\GoogleAuth;

class GoogleContactGroups extends GoogleContactGroupsHelper
{

    protected $client;
    protected $httpClient;
    protected $query = "https://www.google.com/m8/feeds/groups/default/full";

    public function __construct($user = null)
    {
        $this->client = GoogleAuth::getClient($user);
        $this->httpClient = $this->client->authorize();
    }

    /**
     * Retrieve all contact groups
     *
     * @return array
     */
    public function getAllGroups()
    {
        $this->setMaxResults(10000);

        $response = $this->httpClient->get($this->getQuery());

        $dom = new \DOMDocument();
        $xml = simplexml_load_string($response->getBody());
        $xml->registerXPathNamespace('gd', 'http://schemas.google.com/g/2005');

        $groups = [];

        foreach($xml->entry as $key => $entry) {
            $id = "";
            foreach($entry->link as $link) {
                if($link->attributes()->rel == 'self') {
                    $id = (string)$link->attributes()->href;
                    break;
                }
            }

            if($id != "") {
                $groups[$id] = (string)$entry->title;
            }

        }
        asort($groups);
        return $groups;
    }

    /**
     * Get a group from the id
     *
     * @param $id
     * @return array
     */
    public function getGroup($id) {
        $response = $this->httpClient->get($id);
        $dom = new \DOMDocument();
        $xml = simplexml_load_string($response->getBody());
        $xml->registerXPathNamespace('gd', 'http://schemas.google.com/g/2005');

        $group = array(
            'id' => $id,
            'title' => (string)$xml->title
        );

        return $group;
    }

    /**
     * Search for
     *
     * @param array $terms
     * @return array
     */
    public function searchGroups($terms = []) {
        if(count($terms) > 0) {
            $this->params['q'] = '';
            if(is_array($terms)) {
                foreach ($terms as $term) {
                    $this->params['q'] .= $term . ' ';
                }
                $this->params['q'] = rtrim($this->params['q']);
            } else {
                $this->params['q'] = $terms;
            }
            // force version 3 if doing full text queries
            $this->params['v'] = '3.0';
        }

        return $this->getAllGroups();
    }

    /**
     * Create A Contact group
     *
     * @param $groupName
     * @return \SimpleXMLElement[]
     */
    public function createGroup($groupName) {
        $doc = new \DOMDocument ();
        $doc->formatOutput = true;
        $docEntry = $doc->createElement ( 'atom:entry' );
        $docEntry->setAttributeNS ( 'http://www.w3.org/2000/xmlns/', 'xmlns:atom', 'http://www.w3.org/2005/Atom' );
        $docEntry->setAttributeNS ( 'http://www.w3.org/2000/xmlns/', 'xmlns:gd', 'http://schemas.google.com/g/2005' );
        $docEntry->setAttributeNS ( 'http://www.w3.org/2000/xmlns/', 'xmlns:gContact', 'http://schemas.google.com/g/2008' );
        $grpName = $doc->createElement ( 'atom:title', htmlentities ( $groupName ) );
        $docEntry->appendChild ( $grpName );
        $doc->appendChild ( $docEntry );

        $this->headers['Content-type'] = 'application/atom+xml; charset=UTF-8; type=entry';
        $request = new \GuzzleHttp\Psr7\Request(
            'POST',
            $this->query,
            $this->headers,
            $doc->saveXML()
        );

        $response = $this->httpClient->send($request);

        $xml = simplexml_load_string($response->getBody());
        $xml->registerXPathNamespace('gd', 'http://schemas.google.com/g/2005');

        return $xml->id;
    }

    /**
     * Delete a Group
     *
     * @param $id
     */
    public function deleteGroup($id) {
        $this->headers['If-Match'] = '*';
        try {
            $request = new \GuzzleHttp\Psr7\Request(
                'DELETE',
                $id,
                $this->headers
            );

            $response = $this->httpClient->send($request);
        }
        catch(Exception $e) {

        }
    }

    /**
     * Update a Group
     *
     * @param $id
     * @param $groupName
     */
    public function updateGroup($id, $groupName) {
        $doc = new \DOMDocument ();
        $doc->formatOutput = true;

        $docEntry = $doc->createElement ( 'entry' );
        $docEntry->setAttribute('gd:etag', '*');
        $docEntry->setAttributeNS ( 'http://www.w3.org/2000/xmlns/', 'xmlns:gd', 'http://schemas.google.com/g/2005' );

        $catTag = $doc->createElement('category');
        $catTag->setAttribute('scheme', "http://schemas.google.com/g/2005#kind");
        $catTag->setAttribute('term', "http://schemas.google.com/g/2005#group");
        $docEntry->appendChild($catTag);

        $idTag = $doc->createElement('id', $id);
        $docEntry->appendChild($idTag);

        $docTitle = $doc->createElement ( 'title', $groupName );
        $docTitle->setAttribute('type','text');
        $docEntry->appendChild ( $docTitle );

        $linkTag1 = $doc->createElement('link');
        $linkTag1->setAttribute('rel', "self");
        $linkTag1->setAttribute('type', "application/atom+xml");
        $linkTag1->setAttribute('href', $id);
        $docEntry->appendChild($linkTag1);

        $linkTag2 = $doc->createElement('link');
        $linkTag2->setAttribute('rel', "edit");
        $linkTag2->setAttribute('type', "application/atom+xml");
        $linkTag2->setAttribute('href', $id);
        $docEntry->appendChild($linkTag2);

        $doc->appendChild ( $docEntry );

        $xml = $doc->saveXML();

        try {
            $this->headers['Content-type'] = 'application/atom+xml; charset=UTF-8; type=entry';
            $this->headers['If-Match'] = '*';
            $request = new \GuzzleHttp\Psr7\Request(
                'PUT',
                $id,
                $this->headers,
                $xml
            );

            $response = $this->httpClient->send($request);
            echo $response->getBody();
        }
        catch(Exception $e) {
            echo $e->getMessage();
        }
    }

    /**
     * Batch Create Groups
     *
     * @param $groups - must be an assoc array [[title=>'',info=>''], ...]
     */
    public function batchCreateGroups($groups) {
        $doc = new \DOMDocument();
        $doc->formatOutput = true;

        $feed = $this->batchHeader($doc);

        foreach($groups as $group) {
            $entry = $doc->createElement('entry');
            $batchId = $doc->createElement('batch:id', 'create');
            $batchOp = $doc->createElement('batch:operation');
            $batchOp->setAttribute('type','insert');
            $entry->appendChild($batchId);
            $entry->appendChild($batchOp);

            $cat = $doc->createElement('atom:category');
            $cat->setAttribute('scheme','http://schemas.google.com/g/2005#kind');
            $cat->setAttribute('term','http://schemas.google.com/contact/2008#group');
            $entry->appendChild($cat);

            $grpName = $doc->createElement ( 'atom:title', htmlentities ( $group['title'] ) );
            $grpName->setAttribute('type','text');
            $entry->appendChild ( $grpName );

            $extProperty = $doc->createElement('gd:extendedProperty');
            $extProperty->setAttribute('name', $group['title']);
            $info = $doc->createElement('info', (empty($group['info'])) ? htmlentities ( $group['title'] ) : $group['info']);
            $extProperty->appendChild($info);
            $entry->appendChild($extProperty);

            $feed->appendChild($entry);
        }

        $doc->appendChild($feed);

        $this->postBatchData($doc->saveXML());
    }

    /**
     * Batch Update Groups
     *
     * @param $groups - must be an assoc array [[id=>'',title=>'',info=>''], ...]
     */
    public function batchUpdateGroups($groups) {
        $doc = new \DOMDocument();
        $doc->formatOutput = true;

        $feed = $this->batchHeader($doc);

        foreach($groups as $group) {
            $entry = $doc->createElement('entry');
            $entry->setAttribute('gd:etag', '*');

            $batchId = $doc->createElement('batch:id', 'update');
            $batchOp = $doc->createElement('batch:operation');
            $batchOp->setAttribute('type','update');

            $idTag = $doc->createElement('id', $group['id']);
            $entry->appendChild($idTag);

            $entry->appendChild($batchId);
            $entry->appendChild($batchOp);

            $cat = $doc->createElement('atom:category');
            $cat->setAttribute('scheme','http://schemas.google.com/g/2005#kind');
            $cat->setAttribute('term','http://schemas.google.com/contact/2008#group');
            $entry->appendChild($cat);

            $grpName = $doc->createElement ( 'atom:title', htmlentities ( $group['title'] ) );
            $grpName->setAttribute('type','text');
            $entry->appendChild ( $grpName );

            $extProperty = $doc->createElement('gd:extendedProperty');
            $extProperty->setAttribute('name', $group['title']);
            $info = $doc->createElement('info', (empty($group['info'])) ? htmlentities ( $group['title'] ) : $group['info']);
            $extProperty->appendChild($info);
            $entry->appendChild($extProperty);

            $feed->appendChild($entry);
        }

        $doc->appendChild($feed);

        $this->postBatchData($doc->saveXML());
    }

    /**
     * Batch Delete Groups
     *
     * @param $groups [id1, id2, id3 ...]
     */
    public function batchDeleteGroups($groups) {
        $doc = new \DOMDocument();
        $doc->formatOutput = true;

        $feed = $this->batchHeader($doc);

        foreach($groups as $group) {
            $entry = $doc->createElement('entry');
            $entry->setAttribute('gd:etag', '*');

            $batchId = $doc->createElement('batch:id', 'delete');
            $batchOp = $doc->createElement('batch:operation');
            $batchOp->setAttribute('type','delete');

            $idTag = $doc->createElement('id', $group);
            $entry->appendChild($idTag);

            $entry->appendChild($batchId);
            $entry->appendChild($batchOp);

            $feed->appendChild($entry);
        }

        $doc->appendChild($feed);

        $this->postBatchData($doc->saveXML());
    }

    /**
     * Process Batch Request
     *
     * @param $xml
     */
    private function postBatchData($xml) {
        $request = new \GuzzleHttp\Psr7\Request(
            'POST',
            $this->query."/batch",
            ['Content-Type' => 'application/atom+xml; charset=UTF-8; type=entry'],
            $xml
        );

        $response = $this->httpClient->send($request);
    }


    /**
     * Create the header for batch Requests
     *
     * @param \DOMDocument $doc
     * @return \DOMElement
     */
    private function batchHeader(\DOMDocument $doc) {
        $feed = $doc->createElement('feed');
        $feed->setAttributeNS ( 'http://www.w3.org/2000/xmlns/', 'xmlns:atom', 'http://www.w3.org/2005/Atom' );
        $feed->setAttributeNS ( 'http://www.w3.org/2000/xmlns/', 'xmlns:gd', 'http://schemas.google.com/g/2005' );
        $feed->setAttributeNS('http://www.w3.org/2000/xmlns/', "xmlns:gContact", 'http://schemas.google.com/contact/2008');
        $feed->setAttributeNS('http://www.w3.org/2000/xmlns/',"xmlns:batch", 'http://schemas.google.com/gdata/batch' );

        return $feed;
    }

}