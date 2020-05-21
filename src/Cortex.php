<?php

declare(strict_types=1);

namespace ridvanaltun\Cortex;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use ridvanaltun\Cortex\Operation;
use ridvanaltun\Cortex\Configs;
use ridvanaltun\Cortex\Exceptions\AlertmanagerConfigNotOk;

class Cortex
{
    /**
     * Guzzle HTTP client
     *
     * @var Client
     */
    public $client;

    function __construct(string $host, string $username = '', string $password = '', array $options = [])
    {
        $opt = array_merge([
            'base_uri' => $host,
            'auth' => [
                $username,
                $password
            ]
        ], $options);

        $this->client  = new Client($opt);
        $this->op      = new Operation($this->client);
    }

    public function configsService(string $consumerId = null)
    {
        return new Configs($this->client, $consumerId);
    }

    /**
     * List all alert manager configs
     */
    public function listAllAlertmanagerConfigs()
    {
        $response = $this->op->request('GET', '/private/api/prom/configs/alertmanager');

        return $response['configs'];
    }

    /**
     * Validate alert manager config
     *
     * @see https://github.com/cortexproject/cortex/blob/master/docs/apis.md#manage-alertmanager
     */
    public function validateAlertmanagerConfig(array $config)
    {
        try
        {
            $this->op->request('POST', '/api/prom/configs/alertmanager/validate', [
                'json' => $config,
            ]);
        }
        catch (ClientException $e)
        {
            $isBadRequest = $e->getResponse()->getStatusCode() === 400;

            if ($isBadRequest)
            {
                throw new AlertmanagerConfigNotOk('Alertmanager config not ok.');
            }
            else
            {
                throw new Exception($e->getMessage(), $e->getCode());
            }
        }
    }
}
