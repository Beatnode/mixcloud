<?php

namespace Beatnode\Mixcloud;

/**
 * Mixcloud API wrapper with support for authentication using OAuth 2
 *
 * @package   Mixcloud
 * @author    Stephen Radford <steve228uk@gmail.com>
 * @copyright 2013 Beatnode 
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @link      xxxx
 */

class Service {

	const CURLOPT_OAUTH_TOKEN = 124;

	private $domain = 'api.mixcloud.com';
	private $authUri = 'https://mixcloud.com/oauth/authorize';
	private $accessUri = 'https://www.mixcloud.com/oauth/access_token';

	private $clientId;
	private $clientSecret;
	private $callbackUrl;

	private $defaultCurlOptions = array(
		CURLOPT_HEADER => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => '',
	);

	private $lastHttpResponseBody;
	private $lastHttpResponseCode;
	private $lastHttpResponseHeaders;

	private $accessToken;

	public function __construct($clientId, $clientSecret, $callbackUrl=null)
	{
		$this->clientId = $clientId;
		$this->clientSecret = $clientSecret;
		$this->callbackUrl = $callbackUrl;
	}

	/**
	 * Build URL
	 * @return string
	 */	

	private function url($uri, array $params=array())
	{
		$uri = (substr($uri, 0, 1) === '/') ? $uri : '/'.$uri;
		$params = (!empty($params)) ? '?'.http_build_query($params) : null;
		return 'https://'.$this->domain.$uri.$params;
	}

    /**
     * Parse HTTP Headers
     * @return array
     */

    private function parseHttpHeaders($headers)
    {
        $headers = explode("\n", trim($headers));
        $parsedHeaders = array();

        foreach ($headers as $header) {
            if (!preg_match('/\:\s/', $header)) {
                continue;
            }

            list($key, $val) = explode(': ', $header, 2);
            $key = str_replace('-', '_', strtolower($key));
            $val = trim($val);

            $parsedHeaders[$key] = $val;
        }

        return $parsedHeaders;
    }

    /**
     * Validate HTTP response code
     * @return boolean
     */

    private function validResponseCode($code)
    {
        return (bool) preg_match('/^20[0-9]{1}$/', $code);
    }

    /**
     * Manually set access token
     */

    public function setAccessToken($token)
    {
    	$this->accessToken = $token;
    }

	/**
	 * Check to see if the user has set an access token, if no request one
	 */

	private function getAccessToken(array $params=array())
	{

		$url = $this->accessUri.'?'.http_build_query($params);

        $response = json_decode($this->request($url), true);

        if (array_key_exists('access_token', $response)) {
            $this->accessToken = $response['access_token'];
            return $response;
        } else {
            return false;
        }

	}

	/**
	 * Get URL for authorization
	 * @return string
	 */	

	public function getAuthorizationUri()
	{
		$params = array(
			'client_id' => $this->clientId,
			'redirect_uri' => $this->callbackUrl,
		);
		$query = http_build_query($params);
		return $this->authUri.'?'.$query;
	}

	/**
	 * Get the access token
	 * @return string
	 */

	public function accessToken($code)
	{
		$params = array(
			'client_id' => $this->clientId,
			'redirect_uri' => $this->callbackUrl,
			'client_secret' => $this->clientSecret,
			'code' => $code,
		);
		$this->getAccessToken($params);
		return $this->accessToken;
	}

	/**
	 * Run a GET request to the Mixcloud API
	 * @return Mixed
	 */

	public function get($uri)
	{
		$url = $this->url($uri, array('access_token' => $this->accessToken));
		return json_decode($this->request($url), true);
	}

	/**
	 * Get's the user's profile
	 * @return array
	 */

	public function getUser()
	{
		return $this->get('me/');
	}

	/**
	 * Actually makes the HTTP request with cURL
	 * @return Mixed
	 */

	private function request($url, array $curlOptions=array())
	{
		$ch = curl_init($url);
		$options = $this->defaultCurlOptions;
        $options += $curlOptions;

		if (array_key_exists(self::CURLOPT_OAUTH_TOKEN, $options)) {
			$includeAccessToken = $options[self::CURLOPT_OAUTH_TOKEN];
			unset($options[self::CURLOPT_OAUTH_TOKEN]);
		} else {
			$includeAccessToken = true;
		}

		curl_setopt_array($ch, $options);

		$data = curl_exec($ch);
		$info = curl_getinfo($ch);

		curl_close($ch);

		if (array_key_exists(CURLOPT_HEADER, $options) && $options[CURLOPT_HEADER]) {
			$this->lastHttpResponseHeaders = $this->parseHttpHeaders(
					substr($data, 0, $info['header_size'])
			);
			$this->lastHttpResponseBody = substr($data, $info['header_size']);
		} else {
			$this->lastHttpResponseHeaders = array();
			$this->lastHttpResponseBody = $data;
		}

		$this->lastHttpResponseCode = $info['http_code'];

		if ($this->validResponseCode($this->lastHttpResponseCode)) {
			return $this->lastHttpResponseBody;
		} else {
			throw new Exception\InvalidHttpResponseCodeException(
			null, 0, $this->lastHttpResponseBody, $this->lastHttpResponseCode
			);
		}
	}

}