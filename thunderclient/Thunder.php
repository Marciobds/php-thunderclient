<?php

/*
 * This file is part of the Thunderpush package.
 *
 * (c) Krzysztof Jagiełło <https://github.com/kjagiello>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

class Thunder
{
	const API_VERSION = '1.0.0';
	const API_URL = '/api/%s/%s/%s/';

	protected $apikey;
	protected $apisecret;
	protected $host;
	protected $params;

	/**
	 * @var $client Guzzle\Http\Client Guzzle client
	 */
	protected $client;

	public function __construct($apikey, $apisecret, $host, $port = 80, $https = false)
	{
		$this->apikey = $apikey;
		$this->apisecret = $apisecret;

		$proto = $https === true ? 'https' : 'http';

		$this->host = $proto . '://' . $host . ':' . $port;

		$this->client = new Client(['verify' => false,'timeout' => 1]);
		$this->params = [
			'headers' => [
				'Host' => $this->host,
				'Content-Type' => 'application/json',
				'X-Thunder-Secret-Key' => $this->apisecret,
			],
			'timeout' => 20,
            		'connect_timeout' => 20,
            		'body' => ''
		];
	}

	protected function make_url($command)
	{
		$arguments = array_slice(func_get_args(), 1);

		$url = sprintf(self::API_URL, self::API_VERSION, $this->apikey,
			$command);

		if ($arguments) {
			$url .= implode('/', $arguments) . '/';
		}

		return $this->host . $url;
	}

	protected function make_request($method, $url, $data = null)
	{
		$url = call_user_func_array(array($this, 'make_url'), $url);

		$return = array(
			'data' => array(),
			'status' => 500
		);

		try {
			switch ($method) {
				case 'GET':
					$response = $this->client->get($url, $this->params);
					break;
				case 'POST':
					$this->params['body'] = json_encode($data);
					$response = $this->client->post($url, $this->params);
					break;
				case 'DELETE':
					$response = $this->client->delete($url);
					break;
				default:
					throw new \UnsupportedMethodException(
						'Unsupported request method: ' . $method
					);
					return;
			}

			$return['data'] = json_decode($response->getBody(), true);
			$return['data'] = empty($return['data']) ? array() : $return['data'];
			$return['status'] = $response->getStatusCode();

		} catch(\RequestException $e) {
			$return['status'] = $e->getStatusCode();
			$return['exception'] = $e;
		} catch(\Exception $e) {
			$return['exception'] = $e;
		}

		return $return;
	}

	protected function build_response($response, $field = null)
	{
		if ($response['status'] == 200) {
			return $response['data'][$field];
		} else if (is_null($field) && $response['status'] == 204) {
			return true;
		} else {
			return null;
		}
	}

	public function get_user_count()
	{
		$response = $this->make_request('GET', array('users'));
		return $this->build_response($response, 'count');
	}

	public function get_users_in_channel($channel)
	{
		$response = $this->make_request('GET', array('channels', $channel));
		return $this->build_response($response, 'users');
	}

	public function send_message_to_user($userid, $message)
	{
		$response = $this->make_request('POST', array('users', $userid), $message);
		return $this->build_response($response, 'count');
	}

	public function send_message_to_channel($channel, $message)
	{
		$response = $this->make_request('POST', array('channels', $channel), $message);
		return $this->build_response($response, 'count');
	}

	public function is_user_online($userid)
	{
		$response = $this->make_request('GET', array('users', $userid));
		return $this->build_response($response, 'online');
	}

	public function disconnect_user($userid)
	{
		$response = $this->make_request('DELETE', array('users', $userid));
		return $this->build_response($response);
	}
}

/** Exceptions **/

class UnsupportedMethodException extends \Exception {}
