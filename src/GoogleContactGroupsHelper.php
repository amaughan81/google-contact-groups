<?php

namespace amaughan81;

class GoogleContactGroupsHelper {

    protected $params = [];
    protected $query = "https://www.google.com/m8/feeds/groups/default/full";
    protected $headers = [];

    public function setMaxResults($value) {
        $value = (int)$value;
        if ($value != 0) {
            $this->params['max-results'] = $value;
        } else {
            unset($this->params['max-results']);
        }
        return $this;
    }

    public function getQuery() {
        if(count($this->params) > 0) {
            $this->query .= '?';
            foreach($this->params as $key=>$value) {
                $this->query .= $key.'='.$value.'&';
            }
            $this->query = rtrim($this->query,'&');
        }
        return $this->query;
    }

    public function setFeedType($type) {
        $types = ['atom','rss','json'];
        if(in_array($type, $types)) {
            $this->params['alt'] = $type;
        }
    }

    public function setMajorProtocolVersion($value) {
        $value = floatval($value);
        $this->headers['GData-Version'] = $value;
    }

}