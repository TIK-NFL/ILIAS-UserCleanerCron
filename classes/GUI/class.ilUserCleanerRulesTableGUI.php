<?php
declare(strict_types=1);

use ILIAS\HTTP\Services as HTTPServices;
use ILIAS\Refinery\Factory as RefineryFactory;
use ILIAS\UI\Factory as UIFactory;
use ILIAS\UI\Renderer as UIRenderer;

/**
 * @ilCtrl_isCalledBy ilUserCleanerRulesTableGUI: ilUserCleanerConfigGUI
 * @ilCtrl_Calls ilUserCleanerRulesTableGUI:
 */
final class ilUserCleanerRulesTableGUI
{
    private ilCtrlInterface $ctrl;
    private ilGlobalTemplateInterface $template;
    private ilToolbarGUI $toolbar;
    private ilLanguage $language;
    private HTTPServices $http;
    private RefineryFactory $refinery;
    private UIFactory $uiFactory;
    private UIRenderer $uiRenderer;
    private ilUserCleanerRuleSetRepository $ruleSets;
    private ilUserCleanerRuleSetRuleRepository $memberships;
    private ilUserCleanerRuleRepository $rules;
    private ilUserCleanerRoleTargetRepository $targets;
    private ilUserCleanerLDAPSourceRepository $ldapSources;
    private ilUserCleanerAuthModeRepository $authModes;
    private ilPlugin $pluginObject;

    public function __construct()
    {
        global $DIC;

        $this->ctrl = $DIC->ctrl();
        $this->template = $DIC->ui()->mainTemplate();
        $this->toolbar = $DIC->toolbar();
        $this->language = $DIC->language();
        $this->http = $DIC->http();
        $this->refinery = $DIC->refinery();
        $this->uiFactory = $DIC->ui()->factory();
        $this->uiRenderer = $DIC->ui()->renderer();
        $this->ruleSets = new ilUserCleanerRuleSetRepository();
        $this->memberships = new ilUserCleanerRuleSetRuleRepository();
        $this->rules = new ilUserCleanerRuleRepository();
        $this->targets = new ilUserCleanerRoleTargetRepository();
        $this->ldapSources = new ilUserCleanerLDAPSourceRepository();
        $this->authModes = new ilUserCleanerAuthModeRepository();
    }

    public function setPluginObject(ilPlugin $plugin_object): void
    {
        $this->pluginObject = $plugin_object;
    }

    public function executeCommand(): void
    {
        ilUserCleanerGUIHelper::preserveComponentParameters([
            ilUserCleanerConfigGUI::class,
            self::class,
            ilObjComponentSettingsGUI::class,
        ]);
        $this->preserveRuleSetId();

        switch ($this->ctrl->getCmd(ilUserCleanerGUIConstants::CMD_SHOW)) {
            case ilUserCleanerGUIConstants::CMD_SAVE_RULE_SET:
                $this->saveRuleSet();
                return;
            case ilUserCleanerGUIConstants::CMD_EDIT_RULE_SET:
                $this->editRuleSet();
                return;
            case ilUserCleanerGUIConstants::CMD_MANAGE_RULE_SET_RULES:
                $this->manageRuleSetRules();
                return;
            case ilUserCleanerGUIConstants::CMD_UPDATE_RULE_SET:
                $this->updateRuleSet();
                return;
            case ilUserCleanerGUIConstants::CMD_DELETE_RULE_SET:
                $this->deleteRuleSet();
                return;
            case ilUserCleanerGUIConstants::CMD_CONFIRM_DELETE_RULE_SET:
                $this->confirmDeleteRuleSet();
                return;
            case ilUserCleanerGUIConstants::CMD_ADD_RULE:
                $this->addRule();
                return;
            case ilUserCleanerGUIConstants::CMD_DELETE_RULE_SET_RULES:
                $this->deleteRuleSetRules();
                return;
            default:
                $this->show();
        }
    }

    public function show(): void
    {
        $sets = $this->ruleSets->getAll();
        $modal = $this->getCreateRuleSetModal();
        $this->toolbar->addComponent(
            $this->uiFactory->button()->primary(
                $this->pluginObject->txt('rule_sets_add'),
                $modal->getShowSignal()
            )
        );
        $this->template->setContent($this->uiRenderer->render([
            $modal,
            $this->getRuleSetsTable($sets)->getComponent()->withRequest($this->http->request()),
        ]));
    }

    private function saveRuleSet(): void
    {
        $modal = $this->getCreateRuleSetModal()->withRequest($this->http->request());
        $data = $modal->getData();
        if ($data === null) {
            $sets = $this->ruleSets->getAll();
            $modal = $modal->withOnLoad($modal->getShowSignal());
            $this->template->setContent($this->uiRenderer->render([
                $modal,
                $this->getRuleSetsTable($sets)->getComponent()->withRequest($this->http->request()),
            ]));
            return;
        }

        $rule_set = $this->ruleSets->insert(
            (string) $data[ilUserCleanerGUIConstants::FIELD_RULE_SET_TITLE],
            (string) $data[ilUserCleanerGUIConstants::FIELD_RULE_SET_DESCRIPTION],
            (int) $data[ilUserCleanerGUIConstants::FIELD_ROLE_ID],
            (int) $data[ilUserCleanerGUIConstants::FIELD_AUTH_ID],
            (bool) $data[ilUserCleanerGUIConstants::FIELD_RULE_SET_ENABLED]
        );
        $this->setSavedMessage();
        $this->redirectToRuleSetRules($rule_set->id);
    }

    private function editRuleSet(): void
    {
        $rule_set = $this->requireRuleSet();
        $this->ctrl->setParameter($this, ilUserCleanerGUIConstants::PARAM_RULE_SET_ID, $rule_set->id);
        $this->template->setContent($this->getRuleSetForm($rule_set)->getHTML());
    }

    private function manageRuleSetRules(): void
    {
        $rule_set = $this->requireRuleSet();
        $this->ctrl->setParameter($this, ilUserCleanerGUIConstants::PARAM_RULE_SET_ID, $rule_set->id);
        $content = $this->getRuleForm($rule_set->id)->getHTML();
        $content .= $this->uiRenderer->render(
            $this->getRulesTable($rule_set->id)->getComponent()->withRequest($this->http->request())
        );
        $this->template->setContent($content);
    }

    private function updateRuleSet(): void
    {
        $current = $this->requireRuleSet();
        $form = $this->getRuleSetForm($current);
        if (!$form->checkInput()) {
            $form->setValuesByPost();
            $this->template->setContent($form->getHTML());
            return;
        }
        $this->ruleSets->update(new ilUserCleanerRuleSet(
            $current->id,
            (string) $form->getInput(ilUserCleanerGUIConstants::FIELD_RULE_SET_TITLE),
            (string) $form->getInput(ilUserCleanerGUIConstants::FIELD_RULE_SET_DESCRIPTION),
            (int) $form->getInput(ilUserCleanerGUIConstants::FIELD_ROLE_ID),
            (int) $form->getInput(ilUserCleanerGUIConstants::FIELD_AUTH_ID),
            (bool) $form->getInput(ilUserCleanerGUIConstants::FIELD_RULE_SET_ENABLED)
        ));
        $this->setSavedMessage();
        $this->redirectToRuleSet($current->id);
    }

    private function confirmDeleteRuleSet(): void
    {
        $rule_set = $this->requireRuleSet();
        $confirmation = new ilConfirmationGUI();
        $confirmation->setFormAction(
            $this->getFormAction(ilUserCleanerGUIConstants::CMD_DELETE_RULE_SET, $rule_set->id)
        );
        $confirmation->setHeaderText($this->pluginObject->txt('rule_sets_confirm_delete'));
        $confirmation->setConfirm($this->language->txt('delete'), ilUserCleanerGUIConstants::CMD_DELETE_RULE_SET);
        $confirmation->setCancel($this->language->txt('cancel'), ilUserCleanerGUIConstants::CMD_SHOW);
        $confirmation->addItem(
            ilUserCleanerGUIConstants::PARAM_RULE_SET_ID,
            (string) $rule_set->id,
            $rule_set->title
        );
        $this->template->setContent($confirmation->getHTML());
    }

    private function deleteRuleSet(): void
    {
        $rule_set = $this->requireRuleSet();
        $rule_ids = array_map(
            static fn(ilUserCleanerRuleSetRule $membership): int => $membership->ruleId,
            $this->memberships->getByRuleSetId($rule_set->id)
        );
        $this->ruleSets->delete($rule_set->id);
        foreach (array_unique($rule_ids) as $rule_id) {
            $this->rules->deleteIfUnused($rule_id);
        }
        $this->template->setOnScreenMessage('success', $this->pluginObject->txt('rule_sets_deleted'), true);
        $this->ctrl->redirect($this, ilUserCleanerGUIConstants::CMD_SHOW);
    }

    private function addRule(): void
    {
        $rule_set = $this->requireRuleSet();
        $form = $this->getRuleForm($rule_set->id);
        if (!$form->checkInput()) {
            $form->setValuesByPost();
            $content = $form->getHTML();
            $content .= $this->uiRenderer->render(
                $this->getRulesTable($rule_set->id)->getComponent()->withRequest($this->http->request())
            );
            $this->template->setContent($content);
            return;
        }

        $parameter_id = (int) $form->getInput(ilUserCleanerGUIConstants::FIELD_PARAMETER_ID);
        $type = $this->rules->getType($parameter_id);
        $source_config_id = trim((string) $form->getInput(
            $this->getRuleFieldName(ilUserCleanerGUIConstants::FIELD_SOURCE_CONFIG_ID, $parameter_id)
        ));
        $source_config_id = $this->validateSourceConfiguration($type, $source_config_id, $rule_set->id);
        $rule = $this->rules->insert(
            $parameter_id,
            $type->valueRequired
                ? (string) $form->getInput(
                    $this->getRuleFieldName(ilUserCleanerGUIConstants::FIELD_SYMBOL, $parameter_id)
                )
                : '=',
            $type->valueRequired
                ? (int) $form->getInput(
                    $this->getRuleFieldName(ilUserCleanerGUIConstants::FIELD_VALUE, $parameter_id)
                )
                : 1,
            $source_config_id
        );
        $this->memberships->insert(
            $rule_set->id,
            $rule->id
        );
        $this->setSavedMessage();
        $this->redirectToRuleSetRules($rule_set->id);
    }

    private function deleteRuleSetRules(): void
    {
        $rule_set = $this->requireRuleSet();
        $query = $this->http->wrapper()->query();
        if ($query->has(ilUserCleanerGUIConstants::PARAM_RULE_SET_RULE_IDS)) {
            $ids = $query->retrieve(
                ilUserCleanerGUIConstants::PARAM_RULE_SET_RULE_IDS,
                $this->refinery->custom()->transformation(static fn($value): array => is_array($value)
                    ? $value
                    : array_filter(explode(',', (string) $value)))
            );
            $owned = [];
            foreach ($this->memberships->getByRuleSetId($rule_set->id) as $membership) {
                $owned[$membership->id] = $membership->ruleId;
            }
            foreach ($ids as $id) {
                if (isset($owned[(int) $id])) {
                    $this->memberships->delete((int) $id);
                    $this->rules->deleteIfUnused($owned[(int) $id]);
                }
            }
        }
        $this->setSavedMessage();
        $this->redirectToRuleSetRules($rule_set->id);
    }

    private function getRuleSetForm(?ilUserCleanerRuleSet $rule_set = null): ilPropertyFormGUI
    {
        $form = new ilPropertyFormGUI();
        $form->setTitle($this->pluginObject->txt($rule_set === null ? 'rule_sets_create_title' : 'rule_sets_edit_title'));
        $command = $rule_set === null
            ? ilUserCleanerGUIConstants::CMD_SAVE_RULE_SET
            : ilUserCleanerGUIConstants::CMD_UPDATE_RULE_SET;
        $form->setFormAction($this->getFormAction($command, $rule_set?->id));

        $title = new ilTextInputGUI($this->pluginObject->txt('rule_sets_field_title'), ilUserCleanerGUIConstants::FIELD_RULE_SET_TITLE);
        $title->setRequired(true);
        $title->setMaxLength(255);
        $form->addItem($title);

        $description = new ilTextAreaInputGUI($this->pluginObject->txt('rule_sets_field_description'), ilUserCleanerGUIConstants::FIELD_RULE_SET_DESCRIPTION);
        $form->addItem($description);

        $role = new ilSelectInputGUI($this->pluginObject->txt('rule_sets_field_role'), ilUserCleanerGUIConstants::FIELD_ROLE_ID);
        $role->setOptions($this->getRoleOptions());
        $role->setRequired(true);
        $form->addItem($role);

        $auth = new ilSelectInputGUI(
            $this->pluginObject->txt('rule_sets_field_auth_mode'),
            ilUserCleanerGUIConstants::FIELD_AUTH_ID
        );
        $auth->setOptions($this->getAuthOptions());
        $auth->setRequired(true);
        $form->addItem($auth);

        $enabled = new ilCheckboxInputGUI($this->pluginObject->txt('rule_sets_field_enabled'), ilUserCleanerGUIConstants::FIELD_RULE_SET_ENABLED);
        $form->addItem($enabled);

        if ($rule_set !== null) {
            $form->setValuesByArray([
                ilUserCleanerGUIConstants::FIELD_RULE_SET_TITLE => $rule_set->title,
                ilUserCleanerGUIConstants::FIELD_RULE_SET_DESCRIPTION => $rule_set->description,
                ilUserCleanerGUIConstants::FIELD_ROLE_ID => $rule_set->roleId,
                ilUserCleanerGUIConstants::FIELD_AUTH_ID => $rule_set->authId,
                ilUserCleanerGUIConstants::FIELD_RULE_SET_ENABLED => $rule_set->enabled,
            ]);
            $form->addCommandButton($command, $this->language->txt('save'));
            $form->addCommandButton(ilUserCleanerGUIConstants::CMD_SHOW, $this->language->txt('back'));
        } else {
            $enabled->setChecked(true);
            $form->addCommandButton($command, $this->language->txt('add'));
        }

        return $form;
    }

    private function getCreateRuleSetModal(): ILIAS\UI\Component\Modal\RoundTrip
    {
        $field = $this->uiFactory->input()->field();
        return $this->uiFactory->modal()->roundtrip(
            $this->pluginObject->txt('rule_sets_create_title'),
            null,
            [
                ilUserCleanerGUIConstants::FIELD_RULE_SET_TITLE => $field->text(
                    $this->pluginObject->txt('rule_sets_field_title')
                )->withRequired(true),
                ilUserCleanerGUIConstants::FIELD_RULE_SET_DESCRIPTION => $field->textarea(
                    $this->pluginObject->txt('rule_sets_field_description')
                ),
                ilUserCleanerGUIConstants::FIELD_ROLE_ID => $field->select(
                    $this->pluginObject->txt('rule_sets_field_role'),
                    $this->getRoleOptions()
                )->withRequired(true),
                ilUserCleanerGUIConstants::FIELD_AUTH_ID => $field->select(
                    $this->pluginObject->txt('rule_sets_field_auth_mode'),
                    $this->getAuthOptions()
                )->withRequired(true),
                ilUserCleanerGUIConstants::FIELD_RULE_SET_ENABLED => $field->checkbox(
                    $this->pluginObject->txt('rule_sets_field_enabled')
                )->withValue(true),
            ],
            $this->getFormAction(ilUserCleanerGUIConstants::CMD_SAVE_RULE_SET)
        );
    }

    private function getRuleForm(int $rule_set_id): ilPropertyFormGUI
    {
        $form = new ilPropertyFormGUI();
        $form->setTitle($this->pluginObject->txt('rule_sets_add_rule_title'));
        $form->setFormAction($this->getFormAction(ilUserCleanerGUIConstants::CMD_ADD_RULE, $rule_set_id));

        $parameter = new ilRadioGroupInputGUI(
            $this->pluginObject->txt('rules_table_input_parameter'),
            ilUserCleanerGUIConstants::FIELD_PARAMETER_ID
        );
        $parameter->setRequired(true);
        foreach ($this->rules->getTypes() as $type) {
            $option = new ilRadioOption(
                $this->pluginObject->txt('parameter_' . $type->key),
                (string) $type->id
            );
            if ($type->source === ilUserCleanerRuleSource::REST) {
                $option->setDisabled(true);
                $option->setInfo($this->pluginObject->txt('auth_rest_not_implemented'));
                $parameter->addOption($option);
                continue;
            }
            if ($type->valueRequired) {
                $symbol = new ilSelectInputGUI(
                    $this->pluginObject->txt('rules_table_input_symbol'),
                    $this->getRuleFieldName(ilUserCleanerGUIConstants::FIELD_SYMBOL, $type->id)
                );
                $symbol->setOptions(ilUserCleanerGUIConstants::RULE_SYMBOLS);
                $symbol->setRequired(true);
                $option->addSubItem($symbol);

                $value = new ilNumberInputGUI(
                    $this->pluginObject->txt('rules_table_input_value'),
                    $this->getRuleFieldName(ilUserCleanerGUIConstants::FIELD_VALUE, $type->id)
                );
                $value->setDecimals(0);
                $value->setRequired(true);
                $value->setValue('1');
                $option->addSubItem($value);
            } elseif ($type->source !== ilUserCleanerRuleSource::LOCAL_DATABASE) {
                $source = new ilSelectInputGUI(
                    $this->pluginObject->txt('rule_sets_source_configuration'),
                    $this->getRuleFieldName(ilUserCleanerGUIConstants::FIELD_SOURCE_CONFIG_ID, $type->id)
                );
                $source->setOptions($this->getSourceConfigurationOptions($type->source));
                $source->setRequired($type->configurationRequired);
                $option->addSubItem($source);
            }
            $parameter->addOption($option);
        }
        $form->addItem($parameter);
        $form->addCommandButton(ilUserCleanerGUIConstants::CMD_ADD_RULE, $this->language->txt('save'));
        $form->addCommandButton(ilUserCleanerGUIConstants::CMD_SHOW, $this->language->txt('back'));
        return $form;
    }

    private function getRuleSetsTable(array $sets): ilUserCleanerRuleSetsKitchenSinkTable
    {
        $roles = $this->getRoleOptions();
        $auth_modes = $this->getAuthOptions();
        $records = [];
        foreach ($sets as $set) {
            $memberships = $this->memberships->getByRuleSetId($set->id);
            $records[] = [
                'rule_set_id' => $set->id,
                'title' => $set->title,
                'role' => $roles[$set->roleId] ?? ('#' . $set->roleId),
                'auth_mode' => $auth_modes[$set->authId] ?? ('#' . $set->authId),
                'rules' => count($memberships),
                'condition' => $this->buildCondition($memberships),
                'sources' => $this->buildSources($memberships),
                'enabled' => $this->language->txt($set->enabled ? 'yes' : 'no'),
                'actions' => $this->uiFactory->listing()->unordered([
                    $this->uiFactory->link()->standard(
                        $this->language->txt('edit'),
                        $this->getRuleSetLink($set->id, ilUserCleanerGUIConstants::CMD_EDIT_RULE_SET)
                    ),
                    $this->uiFactory->link()->standard(
                        $this->pluginObject->txt('rule_sets_manage_rules'),
                        $this->getRuleSetLink($set->id, ilUserCleanerGUIConstants::CMD_MANAGE_RULE_SET_RULES)
                    ),
                    $this->uiFactory->link()->standard(
                        $this->language->txt('delete'),
                        $this->getRuleSetLink($set->id, ilUserCleanerGUIConstants::CMD_CONFIRM_DELETE_RULE_SET)
                    ),
                ]),
            ];
        }
        return new ilUserCleanerRuleSetsKitchenSinkTable(
            $this->uiFactory,
            $this->pluginObject->txt('rule_sets_table_title'),
            [
                'title' => $this->pluginObject->txt('rule_sets_field_title'),
                'role' => $this->pluginObject->txt('rule_sets_field_role'),
                'auth_mode' => $this->pluginObject->txt('rule_sets_column_auth_mode'),
                'rules' => $this->pluginObject->txt('rule_sets_column_rules'),
                'condition' => $this->pluginObject->txt('rule_sets_column_condition'),
                'sources' => $this->pluginObject->txt('rule_sets_column_sources'),
                'enabled' => $this->pluginObject->txt('rule_sets_field_enabled'),
                'actions' => $this->language->txt('actions'),
            ],
            $records
        );
    }

    private function getRulesTable(int $rule_set_id): ilUserCleanerRuleSetRulesKitchenSinkTable
    {
        $records = [];
        foreach ($this->memberships->getByRuleSetId($rule_set_id) as $membership) {
            $rule = $this->rules->getById($membership->ruleId);
            if ($rule === null) {
                continue;
            }
            $records[] = [
                'membership_id' => $membership->id,
                'sequence' => $membership->sequence,
                'rule' => $this->formatRule($rule),
                'source' => $this->getSourceLabel($rule),
            ];
        }
        return new ilUserCleanerRuleSetRulesKitchenSinkTable(
            $this->ctrl,
            $this->language,
            $this->uiFactory,
            $this->http,
            $this->pluginObject->txt('rule_sets_rules_table_title'),
            [
                'sequence' => $this->pluginObject->txt('rule_sets_column_sequence'),
                'rule' => $this->pluginObject->txt('rule_sets_column_rule'),
                'source' => $this->pluginObject->txt('rule_sets_column_source'),
            ],
            $records
        );
    }

    private function buildCondition(array $memberships): string
    {
        $conditions = [];
        foreach ($memberships as $membership) {
            $rule = $this->rules->getById($membership->ruleId);
            if ($rule !== null) {
                $conditions[] = $this->formatRule($rule);
            }
        }
        return $conditions === [] ? '—' : implode(' AND ', $conditions);
    }

    private function buildSources(array $memberships): string
    {
        $sources = [];
        foreach ($memberships as $membership) {
            $rule = $this->rules->getById($membership->ruleId);
            if ($rule !== null) {
                $source = $rule->source;
                $sources[$source->value] = $this->pluginObject->txt('rule_source_' . $source->value);
            }
        }
        return $sources === [] ? '—' : implode(' / ', $sources);
    }

    private function formatRule(ilUserCleanerRule $rule): string
    {
        $label_key = 'parameter_' . $rule->parameter;
        $label = $this->pluginObject->txt($label_key);
        return $rule->valueRequired
            ? $label . ' ' . $rule->symbol . ' ' . $rule->value
            : $label;
    }

    private function getSourceLabel(ilUserCleanerRule $rule): string
    {
        $label = $this->pluginObject->txt('rule_source_' . $rule->source->value);
        if ($rule->configurationRequired && ($rule->sourceConfigId === null || $rule->sourceConfigId === '')) {
            $label .= ' (' . $this->pluginObject->txt('rule_source_not_configured') . ')';
        } elseif ($rule->sourceConfigId !== null && $rule->sourceConfigId !== '') {
            $label .= ': ' . $this->getSourceConfigurationLabel($rule->sourceConfigId);
        }
        return $label;
    }

    private function getRoleOptions(): array
    {
        $options = [];
        foreach ($this->targets->getAllGlobalRoles() as $role) {
            $options[$role->roleId] = $role->title;
        }
        return $options;
    }

    private function getAuthOptions(): array
    {
        $options = [];
        foreach ($this->authModes->getAll() as $mode) {
            $key = (string) $mode['auth_mode'];
            $options[(int) $mode['auth_id']] = $this->pluginObject->txt('auth_mode_' . $key);
        }
        return $options;
    }

    private function getSourceConfigurationOptions(ilUserCleanerRuleSource $rule_source): array
    {
        $options = [];
        if ($rule_source === ilUserCleanerRuleSource::LDAP) {
            foreach ($this->ldapSources->getAll() as $source) {
                $usage = [];
                if ($source->authentication) {
                    $usage[] = $this->pluginObject->txt('rule_source_ldap_authentication');
                }
                if ($source->isDataSource()) {
                    $usage[] = $this->pluginObject->txt('rule_source_ldap_data_source');
                }
                $status = $this->pluginObject->txt(
                    $source->active ? 'rule_source_active' : 'rule_source_inactive'
                );
                $options['ldap:' . $source->id] = sprintf(
                    '%s (#%d) — %s — %s',
                    $source->name,
                    $source->id,
                    implode(' / ', $usage),
                    $status
                );
            }
        }
        if ($options === []) {
            $options[''] = $this->pluginObject->txt('rule_source_not_configured');
        }
        return $options;
    }

    private function getRuleFieldName(string $field, int $parameter_id): string
    {
        return $field . '_' . $parameter_id;
    }

    private function getSourceConfigurationLabel(string $configuration_id): string
    {
        if (str_starts_with($configuration_id, 'ldap:')) {
            $source = $this->ldapSources->getById((int) substr($configuration_id, 5));
            if ($source !== null) {
                return $source->name . ' (#' . $source->id . ')';
            }
        }
        return $this->pluginObject->txt('rule_source_missing_configuration');
    }

    private function validateSourceConfiguration(
        ilUserCleanerRuleType $type,
        string $configuration_id,
        int $rule_set_id
    ): ?string {
        if ($type->source === ilUserCleanerRuleSource::LOCAL_DATABASE) {
            return null;
        }
        if (str_starts_with($configuration_id, 'ldap:')) {
            $source = $this->ldapSources->getById((int) substr($configuration_id, 5));
            if ($source !== null) {
                return $configuration_id;
            }
        }

        $this->template->setOnScreenMessage(
            'failure',
            $this->pluginObject->txt('rule_sets_ldap_source_required'),
            true
        );
        $this->redirectToRuleSetRules($rule_set_id);
    }

    private function requireRuleSet(): ilUserCleanerRuleSet
    {
        $id = $this->getRuleSetId();
        $rule_set = $this->ruleSets->getById($id);
        if ($rule_set === null) {
            throw new OutOfBoundsException('Unknown rule set: ' . $id);
        }
        return $rule_set;
    }

    private function getRuleSetId(): int
    {
        $query = $this->http->wrapper()->query();
        if ($query->has(ilUserCleanerGUIConstants::PARAM_RULE_SET_ID)) {
            return $query->retrieve(
                ilUserCleanerGUIConstants::PARAM_RULE_SET_ID,
                $this->refinery->kindlyTo()->int()
            );
        }
        $post = $this->http->wrapper()->post();
        return $post->has(ilUserCleanerGUIConstants::PARAM_RULE_SET_ID)
            ? $post->retrieve(ilUserCleanerGUIConstants::PARAM_RULE_SET_ID, $this->refinery->kindlyTo()->int())
            : 0;
    }

    private function preserveRuleSetId(): void
    {
        $id = $this->getRuleSetId();
        if ($id > 0) {
            $this->ctrl->setParameter($this, ilUserCleanerGUIConstants::PARAM_RULE_SET_ID, $id);
        }
    }

    private function getFormAction(string $command, ?int $rule_set_id = null): string
    {
        if ($rule_set_id !== null) {
            $this->ctrl->setParameter($this, ilUserCleanerGUIConstants::PARAM_RULE_SET_ID, $rule_set_id);
        }
        return $this->ctrl->getFormActionByClass(
            [ilObjComponentSettingsGUI::class, ilUserCleanerConfigGUI::class, self::class],
            $command
        );
    }

    private function getRuleSetLink(int $rule_set_id, string $command): string
    {
        $this->ctrl->setParameter($this, ilUserCleanerGUIConstants::PARAM_RULE_SET_ID, $rule_set_id);
        $link = $this->ctrl->getLinkTargetByClass(
            [ilObjComponentSettingsGUI::class, ilUserCleanerConfigGUI::class, self::class],
            $command
        );
        $this->ctrl->clearParameterByClass(
            self::class,
            ilUserCleanerGUIConstants::PARAM_RULE_SET_ID
        );
        return $link;
    }

    private function redirectToRuleSet(int $id): never
    {
        $this->ctrl->setParameter($this, ilUserCleanerGUIConstants::PARAM_RULE_SET_ID, $id);
        $this->ctrl->redirect($this, ilUserCleanerGUIConstants::CMD_EDIT_RULE_SET);
    }

    private function redirectToRuleSetRules(int $id): never
    {
        $this->ctrl->setParameter($this, ilUserCleanerGUIConstants::PARAM_RULE_SET_ID, $id);
        $this->ctrl->redirect($this, ilUserCleanerGUIConstants::CMD_MANAGE_RULE_SET_RULES);
    }

    private function setSavedMessage(): void
    {
        $this->template->setOnScreenMessage('success', $this->pluginObject->txt('rule_sets_saved'), true);
    }
}
