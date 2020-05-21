<?php

declare(strict_types=1);

namespace ridvanaltun\Cortex;

use GuzzleHttp\Client;

/**
 * Request handler
 */
class Operation
{
	/**
	 * Guacamole API prefix
	 *
	 * @var string
	 */
	private $apiPrefix = '';

	/**
     * Guzzle HTTP client
     *
     * @var Client
     */
	private $client;

	/**
	 * Creates operation
	 *
	 * @param   Client  $client  Guzzle client
	 */
	function __construct(Client $client)
	{
		$this->client = $client;
	}

    private function send(string $method, string $endpoint = '', array $options = []) {
		$response = $this->client->request($method, $endpoint, $options);

		return json_decode($response->getBody()->getContents(), true) ?: (string) $response->getBody()->getContents();
	}

    public function request(string $method = 'GET', string $endpoint = '/', array $options = []) {
		$target = $this->apiPrefix . $endpoint;

		return $this->send($method, $target, $options);
   }
}
