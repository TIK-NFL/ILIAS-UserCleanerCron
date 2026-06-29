<?php
declare(strict_types=1);

use ILIAS\HTTP\Services as HTTPServices;

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 ********************************************************************
 */

/**
 * @author Ulf Bischoff <ulf.bischoff@tik.uni-stuttgart.de>
 * @ilCtrl_isCalledBy ilUserCleanerAuthGUI: ilUserCleanerConfigGUI
 * @ilCtrl_Calls ilUserCleanerAuthGUI:
 */
class ilUserCleanerAuthGUI
{
    private const COMPONENT_PARAMETERS = ['ctype', 'cname', 'slot_id', 'plugin_id', 'pname'];
    private const CMD_SHOW = 'show';
    private const CMD_SAVE = 'save';

    private const CONFIG_KEYS = [
        'rest_status_url_template',
        'rest_auth_header',
        'rest_connection_test_login',
        'rest_active_json_path',
        'rest_active_value',
        'ldap_host',
        'ldap_port',
        'ldap_bind_dn',
        'ldap_bind_password',
        'ldap_status_attribute',
        'ldap_active_value',
    ];

    private ilCtrlInterface $ctrl;
    private ilGlobalTemplateInterface $tpl;
    private ilLanguage $lng;
    private HTTPServices $http;
    private ilUserDBConnector $dbConnector;
    private ilPlugin $pluginObject;

    public function __construct()
    {
        global $DIC;

        $this->ctrl = $DIC->ctrl();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->lng = $DIC->language();
        $this->http = $DIC->http();
        $this->dbConnector = new ilUserDBConnector();
    }

    public function setPluginObject(ilPlugin $plugin_object): void
    {
        $this->pluginObject = $plugin_object;
    }

    public function executeCommand(): void
    {
        $this->preserveComponentParameters();

        $cmd = $this->ctrl->getCmd(self::CMD_SHOW);
        switch ($cmd) {
            case self::CMD_SAVE:
                $this->save();
                return;
            case self::CMD_SHOW:
            default:
                $this->show();
        }
    }

    private function preserveComponentParameters(): void
    {
        foreach ([
            ilUserCleanerConfigGUI::class,
            self::class,
            ilObjComponentSettingsGUI::class,
        ] as $class) {
            foreach (self::COMPONENT_PARAMETERS as $parameter) {
                $this->preserveComponentParameter($class, $parameter);
            }
        }
    }

    private function preserveComponentParameter(string $class, string $parameter): void
    {
        $query = $this->http->request()->getQueryParams();
        if (!isset($query[$parameter]) || !is_string($query[$parameter])) {
            return;
        }

        $this->ctrl->setParameterByClass(
            $class,
            $parameter,
            ilUtil::stripSlashes($query[$parameter])
        );
    }

    public function show(): void
    {
        $this->tpl->setContent($this->getForm()->getHTML());
    }

    private function save(): void
    {
        $form = $this->getForm();
        if (!$form->checkInput()) {
            $form->setValuesByPost();
            $this->tpl->setContent($form->getHTML());
            return;
        }

        foreach (self::CONFIG_KEYS as $key) {
            $this->dbConnector->setConfigValue($key, (string) $form->getInput($key));
        }

        $test_result = $this->testConnections($this->getConfigFromForm($form));
        $message_type = $test_result['has_failure'] ? 'failure' : 'info';
        $messages = array_merge([$this->lng->txt('settings_saved')], $test_result['messages']);

        $this->tpl->setOnScreenMessage($message_type, implode('<br>', $messages), true);
        $this->ctrl->redirect($this, self::CMD_SHOW);
    }

    private function getForm(): ilPropertyFormGUI
    {
        $form = new ilPropertyFormGUI();
        $form->setTitle($this->pluginObject->txt('auth_form_title'));
        $form->setFormAction($this->ctrl->getFormActionByClass(
            [ilObjComponentSettingsGUI::class, ilUserCleanerConfigGUI::class, self::class],
            self::CMD_SAVE
        ));

        $rest_header = new ilFormSectionHeaderGUI();
        $rest_header->setTitle($this->pluginObject->txt('auth_rest_section_title'));
        $form->addItem($rest_header);

        $rest_url = new ilTextInputGUI(
            $this->pluginObject->txt('auth_rest_url_template'),
            'rest_status_url_template'
        );
        $rest_url->setInfo($this->pluginObject->txt('auth_rest_url_template_info'));
        $rest_url->setValue($this->getConfigValue('rest_status_url_template'));
        $form->addItem($rest_url);

        $rest_auth_header = new ilTextInputGUI(
            $this->pluginObject->txt('auth_rest_auth_header'),
            'rest_auth_header'
        );
        $rest_auth_header->setInfo($this->pluginObject->txt('auth_rest_auth_header_info'));
        $rest_auth_header->setValue($this->getConfigValue('rest_auth_header'));
        $form->addItem($rest_auth_header);

        $rest_connection_test_login = new ilTextInputGUI(
            $this->pluginObject->txt('auth_rest_connection_test_login'),
            'rest_connection_test_login'
        );
        $rest_connection_test_login->setInfo($this->pluginObject->txt('auth_rest_connection_test_login_info'));
        $rest_connection_test_login->setValue($this->getConfigValue('rest_connection_test_login'));
        $form->addItem($rest_connection_test_login);

        $rest_active_json_path = new ilTextInputGUI(
            $this->pluginObject->txt('auth_rest_active_json_path'),
            'rest_active_json_path'
        );
        $rest_active_json_path->setInfo($this->pluginObject->txt('auth_rest_active_json_path_info'));
        $rest_active_json_path->setValue($this->getConfigValue('rest_active_json_path'));
        $form->addItem($rest_active_json_path);

        $rest_active_value = new ilTextInputGUI(
            $this->pluginObject->txt('auth_rest_active_value'),
            'rest_active_value'
        );
        $rest_active_value->setInfo($this->pluginObject->txt('auth_rest_active_value_info'));
        $rest_active_value->setValue($this->getConfigValue('rest_active_value'));
        $form->addItem($rest_active_value);

        $ldap_header = new ilFormSectionHeaderGUI();
        $ldap_header->setTitle($this->pluginObject->txt('auth_ldap_section_title'));
        $form->addItem($ldap_header);

        $ldap_host = new ilTextInputGUI(
            $this->pluginObject->txt('auth_ldap_host'),
            'ldap_host'
        );
        $ldap_host->setInfo($this->pluginObject->txt('auth_ldap_host_info'));
        $ldap_host->setValue($this->getConfigValue('ldap_host'));
        $form->addItem($ldap_host);

        $ldap_port = new ilNumberInputGUI(
            $this->pluginObject->txt('auth_ldap_port'),
            'ldap_port'
        );
        $ldap_port->setInfo($this->pluginObject->txt('auth_ldap_port_info'));
        $ldap_port->setValue($this->getConfigValue('ldap_port'));
        $form->addItem($ldap_port);

        $ldap_bind_dn = new ilTextInputGUI(
            $this->pluginObject->txt('auth_ldap_bind_dn'),
            'ldap_bind_dn'
        );
        $ldap_bind_dn->setInfo($this->pluginObject->txt('auth_ldap_bind_dn_info'));
        $ldap_bind_dn->setValue($this->getConfigValue('ldap_bind_dn'));
        $form->addItem($ldap_bind_dn);

        $ldap_bind_password = new ilPasswordInputGUI(
            $this->pluginObject->txt('auth_ldap_bind_password'),
            'ldap_bind_password'
        );
        $ldap_bind_password->setInfo($this->pluginObject->txt('auth_ldap_bind_password_info'));
        $ldap_bind_password->setValue($this->getConfigValue('ldap_bind_password'));
        $ldap_bind_password->setRetype(false);
        $form->addItem($ldap_bind_password);

        $ldap_status_attribute = new ilTextInputGUI(
            $this->pluginObject->txt('auth_ldap_status_attribute'),
            'ldap_status_attribute'
        );
        $ldap_status_attribute->setInfo($this->pluginObject->txt('auth_ldap_status_attribute_info'));
        $ldap_status_attribute->setValue($this->getConfigValue('ldap_status_attribute'));
        $form->addItem($ldap_status_attribute);

        $ldap_active_value = new ilTextInputGUI(
            $this->pluginObject->txt('auth_ldap_active_value'),
            'ldap_active_value'
        );
        $ldap_active_value->setInfo($this->pluginObject->txt('auth_ldap_active_value_info'));
        $ldap_active_value->setValue($this->getConfigValue('ldap_active_value'));
        $form->addItem($ldap_active_value);

        $form->addCommandButton(self::CMD_SAVE, $this->lng->txt('save'));

        return $form;
    }

    private function getConfigValue(string $key): string
    {
        return $this->dbConnector->getConfigValue($key) ?? '';
    }

    /**
     * @return array<string, string>
     */
    private function getConfigFromForm(ilPropertyFormGUI $form): array
    {
        $config = [];
        foreach (self::CONFIG_KEYS as $key) {
            $config[$key] = trim((string) $form->getInput($key));
        }

        return $config;
    }

    /**
     * @param array<string, string> $config
     * @return array{has_failure: bool, messages: string[]}
     */
    private function testConnections(array $config): array
    {
        $messages = [];
        $has_failure = false;

        foreach ([$this->testRestConnection($config), $this->testLdapConnection($config)] as $result) {
            $messages[] = $result['message'];
            $has_failure = $has_failure || $result['failed'];
        }

        return [
            'has_failure' => $has_failure,
            'messages' => $messages,
        ];
    }

    /**
     * @param array<string, string> $config
     * @return array{failed: bool, message: string}
     */
    private function testRestConnection(array $config): array
    {
        $url_template = $config['rest_status_url_template'] ?? '';
        if ($url_template === '') {
            return [
                'failed' => false,
                'message' => $this->pluginObject->txt('auth_connection_test_rest_skipped'),
            ];
        }

        $test_login = $config['rest_connection_test_login'] ?? '';
        if (strpos($url_template, '{login}') !== false && $test_login === '') {
            return [
                'failed' => true,
                'message' => $this->pluginObject->txt('auth_connection_test_rest_missing_login'),
            ];
        }

        if (!function_exists('curl_init')) {
            return [
                'failed' => true,
                'message' => $this->pluginObject->txt('auth_connection_test_rest_curl_missing'),
            ];
        }

        $url = str_replace('{login}', rawurlencode($test_login), $url_template);
        $curl = curl_init($url);
        if ($curl === false) {
            return [
                'failed' => true,
                'message' => $this->pluginObject->txt('auth_connection_test_rest_failed'),
            ];
        }

        $headers = [];
        if (($config['rest_auth_header'] ?? '') !== '') {
            $headers[] = 'Authorization: ' . $config['rest_auth_header'];
        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
        if ($headers !== []) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($curl);
        $http_code = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($response === false || $http_code < 200 || $http_code >= 400) {
            return [
                'failed' => true,
                'message' => sprintf(
                    $this->pluginObject->txt('auth_connection_test_rest_failed_with_status'),
                    $http_code > 0 ? (string) $http_code : $error
                ),
            ];
        }

        return [
            'failed' => false,
            'message' => sprintf($this->pluginObject->txt('auth_connection_test_rest_success'), $http_code),
        ];
    }

    /**
     * @param array<string, string> $config
     * @return array{failed: bool, message: string}
     */
    private function testLdapConnection(array $config): array
    {
        $host = $config['ldap_host'] ?? '';
        if ($host === '') {
            return [
                'failed' => false,
                'message' => $this->pluginObject->txt('auth_connection_test_ldap_skipped'),
            ];
        }

        if (!function_exists('ldap_connect')) {
            return [
                'failed' => true,
                'message' => $this->pluginObject->txt('auth_connection_test_ldap_extension_missing'),
            ];
        }

        $port = (int) ($config['ldap_port'] ?? 389);
        if ($port <= 0) {
            $port = 389;
        }

        $connection = @ldap_connect($host, $port);
        if ($connection === false) {
            return [
                'failed' => true,
                'message' => $this->pluginObject->txt('auth_connection_test_ldap_failed'),
            ];
        }

        @ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        @ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);

        $bind_dn = $config['ldap_bind_dn'] ?? '';
        $bind_password = $config['ldap_bind_password'] ?? '';
        $bind_result = $bind_dn !== ''
            ? @ldap_bind($connection, $bind_dn, $bind_password)
            : @ldap_bind($connection);

        if (function_exists('ldap_unbind')) {
            @ldap_unbind($connection);
        }

        if ($bind_result !== true) {
            return [
                'failed' => true,
                'message' => $this->pluginObject->txt('auth_connection_test_ldap_failed'),
            ];
        }

        return [
            'failed' => false,
            'message' => $this->pluginObject->txt('auth_connection_test_ldap_success'),
        ];
    }
}
