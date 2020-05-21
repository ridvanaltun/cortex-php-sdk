# PHP Cortex SDK

> Manage [Cortex](https://github.com/cortexproject/cortex).

# Table of Contents

- [PHP Cortex SDK](#php-cortex-sdk)
- [Table of Contents](#table-of-contents)
- [Installation](#installation)
- [Usage](#usage)

# Installation

```bash
$ composer require ridvanaltun/cortex
```

# Usage

```php
use ridvanaltun\Cortex\Cortex;
use ridvanaltun\Cortex\Exceptions\RuleExist;
use ridvanaltun\Cortex\Exceptions\RuleNotExist;
use ridvanaltun\Cortex\Exceptions\TemplateExist;
use ridvanaltun\Cortex\Exceptions\TemplateNotExist;
use ridvanaltun\Cortex\Exceptions\UnknownRuleFormatVersion;
use ridvanaltun\Cortex\Exceptions\AlertmanagerConfigNotOk;
use ridvanaltun\Cortex\Exceptions\AlertmanagerConfigNotExist;

$host = 'http://localhost:9002';
$username = 'username';
$password = 'password';

$cortex = new Cortex($host, $username, $password, [
    'timeout' => 5,
    'verify'  => false, // don't verify ssl
]);

$consumer = 'consumer';
$configsService = $cortex->configsService($consumer);

try {

    # list all alertmanager configs (all tenants)
    $allConfigs = $cortex->listAllAlertmanagerConfigs();
    var_dump($allConfigs);

    # list tenant rules
    $rules = $configsService->getRuleFiles();
    var_dump($rules);

    # add a rule file for tenant
    $configsService->addRuleFile('filename', [
      [
        'alert' => 'alertname1',
        'for' =>'10m',
        'expr' => 'up == 0',
      ],
      [
        'alert' => 'alertname2',
        'for' =>'15m',
        'expr' => 'up == 1',
        'labels' => [
          'severity' => 'page',
        ],
        'annotations' => [
          'summary' => 'High request latency',
        ],
      ]
    ]);

    # add or replace rule file for tenant
    $configsService->applyRuleFile('filename', [
      [
        'alert' => 'alertname4',
        'for' =>'10m',
        'expr' => 'up == 0',
      ],
      [
        'alert' => 'alertname2',
        'for' =>'15m',
        'expr' => 'up == 1',
        'labels' => [],
        'annotations' => [],
      ]
    ]);

    # remove rule for tenant
    $configsService->removeRuleFile('rulename');

    # remove all rules for tenant
    $configsService->removeAllRuleFiles();

    # change rule format version for tenant, can be set 1 or 2
    $configsService->changeRuleFormatVersion('2');

    # list tenant templates
    $templates = $configsService->getTemplateFiles();
    var_dump($templates);

    # add a template file for tenant
    $template = '{{ define "slack.myorg.text" }}https://internal.myorg.net/wiki/alerts/{{ .GroupLabels.app }}/{{ . GroupLabels.alertname }}{{ end}}';
    $configsService->addTemplateFile('filename', $template);

    # add or replace template file for tenant
    $template = '{{ define "slack.myorg.text" }}https://internal.myorg.net/wiki/alerts/{{ . GroupLabels.app }}/{{ .GroupLabels.alertname }}{{ end}}';
    $configsService->applyTemplateFile('filename', $template);

    # remove template for tenant
    $configsService->removTemplateFile('templatename');

    # remove all templates for tenant
    $configsService->removeAllTemplateFiles();

    # get tenant alertmanager config
    $alertmanager = $configsService->getAlertmanagerConfig();
    var_dump($alertmanager);

    # remove tenant alertmanager config
    $configsService->removeAlertmanagerConfig();

    # replace tenant alertmanager config
    $configsService->replaceAlertmanagerConfig([
      'global' => [
        'smtp_smarthost' => 'localhost:25',
        'smtp_from' => 'alertmanager@example.org',
        'smtp_auth_username' => 'alertmanager',
        'smtp_auth_password' => 'password',
      ],
      'route' => [
        'receiver' => 'team-X-mails',
      ],
      'receivers' => [
        [
          'name' => 'team-X-mails',
          'email_configs' => [
            [
              'to' => 'abc@domain.com',
            ],
          ],
        ],
      ],
    ]);

    # remove alertmanager config for tenant
    $configsService->removeAlertmanagerConfig();

    # validate alertmanager config
    $cortex->validateAlertmanagerConfig([
      'global' => [
        'smtp_smarthost' => 'localhost:25',
        'smtp_from' => 'alertmanager@example.org',
        'smtp_auth_username' => 'alertmanager',
        'smtp_auth_password' => 'password',
      ],
      'route' => [
        'receiver' => 'team-X-mails',
      ],
      'receivers' => [
        [
          'name' => 'team-X-mails',
          'email_configs' => [
            [
              'to' => 'abc@domain.com',
            ],
          ],
        ],
      ],
    ]);

    # deactive alerting for tenant
    $configsService->deactivate();

    # activate alerting for tenant
    $configsService->activate();

} catch (AlertmanagerConfigNotOk $e) {
    echo "Provided config not ok.";
} catch (RuleExist $e) {
    echo "Rule already exist.";
} catch (RuleNotExist $e) {
    echo "Rule not exist.";
} catch (TemplateExist $e) {
    echo "Template already exist.";
} catch (TemplateNotExist $e) {
    echo "Template not exist.";
} catch (AlertmanagerConfigNotExist $e) {
    echo "Alertmanager config not exist.";
} catch (UnknownRuleFormatVersion $e) {
    echo "Unknown rule format version.";
}
```
