<?php
// src/Wallabag/CoreBundle/Service/ApiRequester.php

namespace Wallabag\CoreBundle\Service;

class ApiRequester
{
    private $url;
    private $clientId;
    private $clientSecret;

    private $extraHeaders;

    public function __construct($oauth_url, $oauth_client, $oauth_secret)
    {
        $this->url       = $oauth_url;
        $this->clientId    = $oauth_client;
        $this->clientSecret    = $oauth_secret;
        $this->apiUrl = $this->url . '/api';
        $this->authUrl = $this->url . '/oauth/v2/';

        $this->extraHeaders = array();
    }

    /**
     * Appends query array onto URL
     *
     * @param string $url
     * @param array  $query
     *
     * @return string
     */
    protected function parseGet($url, $query)
    {
        $append = strpos($url, '?') === false ? '?' : '&';
        return $url . $append . http_build_query($query);
    }

    /**
     * Parses JSON as PHP object
     *
     * @param string $response
     *
     * @return object
     */
    protected function parseResponse($response)
    {
        return json_decode($response);
    }

    /**
     * Makes HTTP Request to the API
     *
     * @param string $url
     * @param array  $parameters
     *
     * @return mixed
     */
    protected function request($url, $parameters = array(), $request = false)
    {
        $this->lastRequest = $url;
        $this->lastRequestData = $parameters;
        $curl = curl_init($url);
        $curlOptions = array(
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_REFERER        => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,

            CURLOPT_PORT           => '8181',
        );

        /*foreach($this->extraHeaders as $key => $value)
        {
            $curlOptions[$key] = $value;
        }*/

        if (! empty($parameters) || ! empty($request)) {
            if (! empty($request)) {
                $curlOptions[ CURLOPT_CUSTOMREQUEST ] = $request;
                $parameters = http_build_query($parameters);
            } else {
                $curlOptions[ CURLOPT_POST ] = true;
            }
            $curlOptions[ CURLOPT_POSTFIELDS ] = $parameters;
        }
        curl_setopt_array($curl, $curlOptions);
        $response = curl_exec($curl);

//var_dump($data);
var_dump(curl_getinfo($curl));

        $error = curl_error($curl);
        $this->lastRequestInfo = curl_getinfo($curl);
        curl_close($curl);
        if (! $response) {
            return $error;
        } else {
            return $this->parseResponse($response);
        }
    }

    /**
     * Creates authentication URL for your app
     *
     * @param string $redirect
     * @param string $approvalPrompt
     * @param string $scope
     * @param string $state
     *
     * @link http://strava.github.io/api/v3/oauth/#get-authorize
     *
     * @return string
     */
    public function authenticationUrl($redirect, $approvalPrompt = 'auto', $scope = null, $state = null)
    {
        $parameters = array(
            'client_id'       => $this->clientId,
            'redirect_uri'    => $redirect,
            'response_type'   => 'code',
            'approval_prompt' => $approvalPrompt,
            'scope'           => $scope,
            'state'           => $state,
        );
        return $this->parseGet(
            $this->authUrl . 'authorize',
            $parameters
        );
    }
    /**
     * Authenticates token returned from API
     *
     * @param string $code
     *
     * @link http://strava.github.io/api/v3/oauth/#post-token
     *
     * @return string
     */
    public function tokenExchange($code)
    {
        $parameters = array(
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code'          => $code,
        );
        return $this->request(
            $this->authUrl . 'token',
            $parameters
        );
    }
    /**
     * Client Credentials grant type
     * 
     * @return string
     */
    public function clientAuthentification()
    {
        $parameters = array(
            'client_id'       => $this->clientId,
            'client_secret'   => $this->clientSecret,
            'grant_type'      => 'client_credentials',
        );
        /*return $this->request(
            $this->authUrl . 'token',
            $parameters,
            'GET'
        );*/
        return $this->request(
            $this->parseGet(
                $this->authUrl . 'token',
                $parameters
            ),
            array(),
            'GET'
        );
    }
    /**
     * Deauthorises application
     *
     * @link http://strava.github.io/api/v3/oauth/#deauthorize
     *
     * @return string
     */
    public function deauthorize()
    {
        return $this->request(
            $this->authUrl . 'deauthorize',
            $this->generateParameters(array())
        );
    }

    /**
     * Sets the access token used to authenticate API requests
     *
     * @param string $token
     */
    public function setAccessToken($token)
    {
        return $this->accessToken = $token;
    }

    /**
     * Sends GET request to specified API endpoint
     *
     * @param string $request
     * @param array  $parameters
     *
     * @example http://strava.github.io/api/v3/athlete/#koms
     *
     * @return string
     */
    public function get($request, $parameters = array())
    {
        $parameters = $this->generateParameters($parameters);
        $requestUrl = $this->parseGet($this->apiUrl . $request, $parameters);
        return $this->request($requestUrl);
    }

    /**
     * Sends PUT request to specified API endpoint
     *
     * @param string $request
     * @param array  $parameters
     *
     * @example http://strava.github.io/api/v3/athlete/#update
     *
     * @return string
     */
    public function put($request, $parameters = array())
    {
        return $this->request(
            $this->apiUrl . $request,
            $this->generateParameters($parameters),
            'PUT'
        );
    }

    /**
     * Sends POST request to specified API endpoint
     *
     * @param string $request
     * @param array  $parameters
     *
     * @example http://strava.github.io/api/v3/activities/#create
     *
     * @return string
     */
    public function post($request, $parameters = array())
    {
        return $this->request(
            $this->apiUrl . $request,
            $this->generateParameters($parameters)
        );
    }

    /**
     * Sends DELETE request to specified API endpoint
     *
     * @param string $request
     * @param array  $parameters
     *
     * @example http://strava.github.io/api/v3/activities/#delete
     *
     * @return string
     */
    public function delete($request, $parameters = array())
    {
        return $this->request(
            $this->apiUrl . $request,
            $this->generateParameters($parameters),
            'DELETE'
        );
    }

    /**
     * Adds access token to paramters sent to API
     *
     * @param  array $parameters
     *
     * @return array
     */
    protected function generateParameters($parameters)
    {
        return array_merge(
            $parameters,
            array( 'access_token' => $this->accessToken )
        );
    }

    /**
    * 
    * Set headers
    * 
    * @param array $headers
    * 
    * @return null
    */
    public function setHeaders(array $headers) {
        $this->extraHeaders = $headers;
    }


}