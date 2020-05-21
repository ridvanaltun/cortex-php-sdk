<?php

declare(strict_types=1);

namespace ridvanaltun\Cortex;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\Yaml\Yaml;
use ridvanaltun\Cortex\Operation;
use ridvanaltun\Cortex\Exceptions\RuleExist;
use ridvanaltun\Cortex\Exceptions\RuleNotExist;
use ridvanaltun\Cortex\Exceptions\TemplateExist;
use ridvanaltun\Cortex\Exceptions\TemplateNotExist;
use ridvanaltun\Cortex\Exceptions\UnknownRuleFormatVersion;
use ridvanaltun\Cortex\Exceptions\AlertmanagerConfigNotOk;
use ridvanaltun\Cortex\Exceptions\AlertmanagerConfigNotExist;

class Configs
{
    /**
     * Operation
     *
     * @var Operation
     */
    private $op;

    /**
     * Consumer Id
     *
     * @var string
     */
    public $consumerId;

    function __construct(Client $client, string $consumerId)
    {
        $this->op         = new Operation($client);
        $this->consumerId = $consumerId;
    }

    /**
     * Get current Alertmanager config
     *
     * @see https://github.com/cortexproject/cortex/blob/master/docs/apis.md#manage-alertmanager
     */
    private function getAlertingConfigs()
    {
        try {

            $response = $this->op->request('GET', '/api/prom/configs/alertmanager', [
                'headers' => [
                    'X-Scope-OrgID' => $this->consumerId,
                ]
            ]);

            return $response['config'];

        } catch (ClientException $e)
        {
            $isNotFound = $e->getResponse()->getStatusCode() === 404;

            if ($isNotFound)
            {
                throw new AlertmanagerConfigNotExist('Alertmanager config not exist.');
            }
            else
            {
                throw new Exception($e->getMessage(), $e->getCode());
            }
        }
    }

    /**
     * Get alertmanager config
     */
    public function getAlertmanagerConfig()
    {
        $config = $this->getAlertingConfigs();
        $alertmanagerConfig = $config['alertmanager_config'];

        if (is_null($alertmanagerConfig))
        {
            return null;
        }
        else
        {
            return Yaml::parse($alertmanagerConfig);
        }
    }

    /**
     * List tenant rules
     */
    public function getRuleFiles()
    {
        $config = $this->getAlertingConfigs();
        $rules = $config['rules_files'];

        if (is_null($rules))
        {
            return [];
        }
        else
        {
            $rulesArray = [];
            foreach ($rules as $key => $value)
            {
                $rulesArray["$key"] = Yaml::parse($value);
            }

            return $rulesArray;
        }
    }

    /**
     * List tenant templates
     */
    public function getTemplateFiles()
    {
        $config = $this->getAlertingConfigs();
        $templates = $config['template_files'];

        if (is_null($templates))
        {
            return [];
        }
        else
        {
            $templateArray = [];
            foreach ($templates as $key => $value)
            {
                $templateArray["$key"] = Yaml::parse($value);
            }

            return $templateArray;
        }
    }

    /**
     * Change rule format version for tenant
     */
    public function changeRuleFormatVersion(string $formatVersion = '2')
    {
        $config = $this->getAlertingConfigs();
        $config['rule_format_version'] = $formatVersion;

        try {
            $this->op->request('POST', '/api/prom/configs/alertmanager', [
                'headers' => [
                    'X-Scope-OrgID' => $this->consumerId,
                ],
                'json' => $config,
            ]);
        }
        catch (ClientException $e)
        {
            $body = $e->getResponse()->getBody()->getContents();
            $isUnknownRuleFormat = strpos($body, 'unknown rule format version') === 0;

            if ($isUnknownRuleFormat)
            {
                throw new UnknownRuleFormatVersion('Unknown rule format version');
            }
            else
            {
                throw new Exception($e->getMessage(), $e->getCode());
            }
        }
    }

    /**
     * Add a rule to tenant
     */
    public function addRuleFile(string $fileName, array $rules)
    {
        $ruleConfig = [
            'groups' => [
                [
                    'name' => $fileName,
                    'rules' => $rules,
                ]
            ]
        ];

        $config = $this->getAlertingConfigs();
        $ruleFiles = (array) $config['rules_files'];
        $ruleFileName = $fileName . '.yaml';

        if (is_null($ruleFiles))
        {
            $ruleFiles = [];
        }
        else
        {
            if (array_key_exists($ruleFileName, $ruleFiles))
            {
                throw new RuleExist('This rule already exist');
            }
        }

        $newRuleFiles = array_merge($ruleFiles, [
            "$ruleFileName" => Yaml::dump($ruleConfig, 8, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK),
        ]);

        $config['rules_files'] = $newRuleFiles;

        $this->op->request('POST', '/api/prom/configs/alertmanager', [
            'headers' => [
                'X-Scope-OrgID' => $this->consumerId,
            ],
            'json' => $config,
        ]);
    }

    /**
     * Add or replace rule file for tenant
     */
    public function applyRuleFile(string $fileName, array $rules)
    {
        $ruleConfig = [
            'groups' => [
                [
                    'name' => $fileName,
                    'rules' => $rules,
                ]
            ]
        ];

        $config = $this->getAlertingConfigs();
        $ruleFiles = (array) $config['rules_files'];
        $ruleFileName = $fileName . '.yaml';

        if (is_null($ruleFiles))
        {
            $ruleFiles = [];
        }

        $newRuleFiles = array_merge($ruleFiles, [
            "$ruleFileName" => Yaml::dump($ruleConfig, 8, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK),
        ]);

        $config['rules_files'] = $newRuleFiles;

        $this->op->request('POST', '/api/prom/configs/alertmanager', [
            'headers' => [
                'X-Scope-OrgID' => $this->consumerId,
            ],
            'json' => $config,
        ]);
    }

    /**
     * Remove a rule from tenant
     */
    public function removeRuleFile(string $ruleName)
    {
        $config = $this->getAlertingConfigs();
        $ruleFiles = (array) $config['rules_files'];
        $ruleFileName = $ruleName . '.yaml';

        if (is_null($ruleFiles))
        {
            throw new RuleNotExist('Rule not exist.');
        }
        else
        {
            if (!array_key_exists($ruleFileName, $ruleFiles))
            {
                throw new RuleNotExist('Rule not exist.');
            }
        }

        unset($config['rules_files'][$ruleFileName]);

        $this->op->request('POST', '/api/prom/configs/alertmanager', [
            'headers' => [
                'X-Scope-OrgID' => $this->consumerId,
            ],
            'json' => $config,
        ]);
    }

    /**
     * Remove all rules from tenant
     */
    public function removeAllRuleFiles()
    {
        $config = $this->getAlertingConfigs();
        $config['rules_files'] = null;

        $this->op->request('POST', '/api/prom/configs/alertmanager', [
            'headers' => [
                'X-Scope-OrgID' => $this->consumerId,
            ],
            'json' => $config,
        ]);
    }

    /**
     * Add a template to tenant
     */
    public function addTemplateFile(string $fileName, string $template)
    {
        $config = $this->getAlertingConfigs();
        $templateFiles = (array) $config['template_files'];
        $templateFileName = $fileName . '.tmpl';

        if (is_null($templateFiles))
        {
            $templateFiles = [];
        }
        else
        {
            if (array_key_exists($templateFileName, $templateFiles))
            {
                throw new TemplateExist('This template already exist');
            }
        }

        $newTemplateFiles = array_merge($templateFiles, [
            "$templateFileName" => $template,
        ]);

        $config['template_files'] = $newTemplateFiles;

        $this->op->request('POST', '/api/prom/configs/alertmanager', [
            'headers' => [
                'X-Scope-OrgID' => $this->consumerId,
            ],
            'json' => $config,
        ]);
    }

    /**
     * Add or replace template file for tenant
     */
    public function applyTemplateFile(string $fileName, string $template)
    {
        $config = $this->getAlertingConfigs();
        $templateFiles = (array) $config['template_files'];
        $templateFileName = $fileName . '.tmpl';

        if (is_null($templateFiles))
        {
            $templateFiles = [];
        }

        $newTemplateFiles = array_merge($templateFiles, [
            "$templateFileName" => $template,
        ]);

        $config['template_files'] = $newTemplateFiles;

        $this->op->request('POST', '/api/prom/configs/alertmanager', [
            'headers' => [
                'X-Scope-OrgID' => $this->consumerId,
            ],
            'json' => $config,
        ]);
    }

    /**
     * Remove a template from tenant
     */
    public function removeTemplateFile(string $fileName)
    {
        $config = $this->getAlertingConfigs();
        $templateFiles = (array) $config['template_files'];
        $templateFileName = $fileName . '.tmpl';

        if (is_null($templateFiles))
        {
            throw new TemplateNotExist('Template not exist.');
        }
        else
        {
            if (!array_key_exists($templateFileName, $templateFiles))
            {
                throw new TemplateNotExist('Template not exist.');
            }
        }

        unset($config['template_files'][$templateFileName]);

        $this->op->request('POST', '/api/prom/configs/alertmanager', [
            'headers' => [
                'X-Scope-OrgID' => $this->consumerId,
            ],
            'json' => $config,
        ]);
    }

    /**
     * Remove all templates from tenant
     */
    public function removeAllTemplateFiles()
    {
        $config = $this->getAlertingConfigs();
        $config['template_files'] = null;

        $this->op->request('POST', '/api/prom/configs/alertmanager', [
            'headers' => [
                'X-Scope-OrgID' => $this->consumerId,
            ],
            'json' => $config,
        ]);
    }

    /**
     * Replace alertmanager config
     */
    public function replaceAlertmanagerConfig(array $alertmanagerConfig)
    {
        $config = $this->getAlertingConfigs();
        $config['alertmanager_config'] = Yaml::dump($alertmanagerConfig, 8, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);

        try {
            $this->op->request('POST', '/api/prom/configs/alertmanager', [
                'headers' => [
                    'X-Scope-OrgID' => $this->consumerId,
                ],
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

    /**
     * Remove tenant alertmanager config
     */
    public function removeAlertmanagerConfig()
    {
        $config = $this->getAlertingConfigs();
        $config['alertmanager_config'] = null;

        $this->op->request('POST', '/api/prom/configs/alertmanager', [
            'headers' => [
                'X-Scope-OrgID' => $this->consumerId,
            ],
            'json' => $config,
        ]);
    }

    /**
     * Disable configs for tenant
     *
     * @see https://github.com/cortexproject/cortex/blob/master/docs/apis.md#deactivaterestore-configs
     */
    public function deactivate()
    {
        $this->op->request('DELETE', '/api/prom/configs/deactivate', [
            'headers' => [
                'X-Scope-OrgID' => $this->consumerId,
            ]
        ]);
    }

    /**
     * Disable configs for tenant
     *
     * @see https://github.com/cortexproject/cortex/blob/master/docs/apis.md#deactivaterestore-configs
     */
    public function activate()
    {
        $this->op->request('POST', '/api/prom/configs/restore', [
            'headers' => [
                'X-Scope-OrgID' => $this->consumerId,
            ]
        ]);
    }
}
