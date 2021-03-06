<?php

namespace App\Services\CentreonRestApi;


use App\Model\Eloquent\centreon\Host;
use App\Model\Eloquent\centreon\Service;

class CentreonInternalRestApiService {

    protected $client;

    protected $url;
    protected $urlSuffix = '?action=action&object=centreon_clapi';
    protected $username;
    protected $password;
    protected $authToken;

    public function __construct ($url, $username, $password) {
        $this->client = new \GuzzleHttp\Client();

        $this->url = $url;
        $this->username = $username;
        $this->password = $password;

        $this->authenticate();
    }


    // -------------------------------------------------------------------
    // HELPERS

    protected function request ($url, $method, $params) {
        $httpStatusCode = null;
        $response = null;
        try {
            $res = $this->client->request($method, $url, $params);
            $httpStatusCode = $res->getStatusCode();
            $response = $res->getBody()->getContents();
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $rawRequest = \GuzzleHttp\Psr7\str($e->getRequest());
            if ($e->hasResponse())
                $response = \GuzzleHttp\Psr7\str($e->getResponse());
            // TODO: log
            echo $response . '<br/>';
        }

        return [$httpStatusCode, $response];
    }

    protected function postFormRequest ($url, $contentMap, $headers=[]) {
        $params = [ 'form_params' => $contentMap ];
        if (! empty($headers))
            $params['headers'] = $headers;

        return $this->request($url, 'POST', $params);
    }

    protected function postJsonRequest ($url, $contentMap, $headers=[]) {
        $params = [ \GuzzleHttp\RequestOptions::JSON => $contentMap ];
        if (! empty($headers))
            $params['headers'] = $headers;

        return $this->request($url, 'POST', $params);
    }

    protected function postGetRequest ($url, $headers=[]) {
        $params = [];
        if (! empty($headers))
            $params['headers'] = $headers;

        return $this->request($url, 'GET', $params);
    }


    // -------------------------------------------------------------------
    // GENERIC

    public function authenticate () {
        $url = $this->url . '?action=authenticate';

        $contentMap = [
            "username" => $this->username,
            "password" => $this->password,
        ];

        list($resultCode, $response) = $this->postFormRequest($url, $contentMap);
        if ($resultCode === 200) {
            $resp = json_decode($response, true);
            $this->authToken = $resp['authToken'];
            return true;
        }
        return false;
    }


    // -------------------------------------------------------------------
    // CENTENGINE EXTERNAL CMD

    public function acknowledgeHost ($hostName, $pollerId, $author, $comment='', $isSticky='2', $doNotify='0', $isPersistent='0', $timestamp=null) {
        $url = $this->url . '?object=centreon_monitoring_externalcmd&action=send';

        $headers = [
            'Content-Type' => 'application/json',
            'centreon-auth-token' => $this->authToken,
        ];

        if ($timestamp === null)
            $timestamp = time();

        $contentMap = [
            'commands' => [
                [
                    'poller_id' => $pollerId,
                    'timestamp' => $timestamp,
                    'command' => implode(';', ['ACKNOWLEDGE_HOST_PROBLEM', $hostName, $isSticky, $doNotify, $isPersistent, $author, $comment]),
                ]
            ]
        ];

        list($resultCode, $response) = $this->postJsonRequest($url, $contentMap, $headers);
        if ($resultCode === 200) {
            return json_decode($response, true);
        } else {
            return null;
        }
    }

    public function unacknowledgeHost ($hostName, $pollerId, $timestamp=null) {
        $url = $this->url . '?object=centreon_monitoring_externalcmd&action=send';

        $headers = [
            'Content-Type' => 'application/json',
            'centreon-auth-token' => $this->authToken,
        ];

        if ($timestamp === null)
            $timestamp = time();

        $contentMap = [
            'commands' => [
                [
                    'poller_id' => $pollerId,
                    'timestamp' => $timestamp,
                    'command' => implode(';', ['REMOVE_HOST_ACKNOWLEDGEMENT', $hostName]),
                ],
            ]
        ];

        list($resultCode, $response) = $this->postJsonRequest($url, $contentMap, $headers);

        if ($resultCode === 200) {
            return json_decode($response, true);
        } else {
            return null;
        }
    }

    public function acknowledgeService ($hostName, $serviceDescription, $pollerId, $author, $comment='', $isSticky='2', $doNotify='0', $isPersistent='0', $timestamp=null) {
        $url = $this->url . '?object=centreon_monitoring_externalcmd&action=send';

        $headers = [
            'Content-Type' => 'application/json',
            'centreon-auth-token' => $this->authToken,
        ];

        if ($timestamp === null)
            $timestamp = time();

        $contentMap = [
            'commands' => [
                [
                    'poller_id' => $pollerId,
                    'timestamp' => $timestamp,
                    'command' => implode(';', ['ACKNOWLEDGE_SVC_PROBLEM', $hostName, $serviceDescription, $isSticky, $doNotify, $isPersistent, $author, $comment]),
                ]
            ]
        ];

        list($resultCode, $response) = $this->postJsonRequest($url, $contentMap, $headers);
        if ($resultCode === 200) {
            return json_decode($response, true);
        } else {
            return null;
        }
    }

    public function unacknowledgeService ($hostName, $serviceDescription, $pollerId, $timestamp=null) {
        $url = $this->url . '?object=centreon_monitoring_externalcmd&action=send';

        $headers = [
            'Content-Type' => 'application/json',
            'centreon-auth-token' => $this->authToken,
        ];

        if ($timestamp === null)
            $timestamp = time();

        $contentMap = [
            'commands' => [
                [
                    'poller_id' => $pollerId,
                    'timestamp' => $timestamp,
                    'command' => implode(';', ['REMOVE_SVC_ACKNOWLEDGEMENT', $hostName, $serviceDescription]),
                ],
            ]
        ];

        list($resultCode, $response) = $this->postJsonRequest($url, $contentMap, $headers);

        if ($resultCode === 200) {
            return json_decode($response, true);
        } else {
            return null;
        }
    }

    // -------------------------------------------------------------------
    // METRICS

    public function metricsDataByService ($hostServiceIds, $from, $to) {
        $url = $this->url . '?object=centreon_metric&action=metricsDataByService';

        $url .= '&ids=' . $hostServiceIds
            . '&start=' . $from
            . '&end='   . $to;

        $headers = [
            'Content-Type' => 'application/json',
            'centreon-auth-token' => $this->authToken,
        ];

        list($resultCode, $response) = $this->postGetRequest($url, $headers);
        if ($resultCode === 200) {
            return json_decode($response, true);
        } else {
            return null;
        }
    }
}