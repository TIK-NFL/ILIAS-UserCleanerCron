<?php
declare(strict_types=1);

/**
 * @ilCtrl_isCalledBy ilUserCleanerSettingsGUI: ilUserCleanerConfigGUI
 * @ilCtrl_Calls ilUserCleanerSettingsGUI:
 */
final class ilUserCleanerSettingsGUI
{
    private ilCtrlInterface $ctrl;
    private ilGlobalTemplateInterface $tpl;
    private ilLanguage $lng;
    private ilUserCleanerSettingsRepository $settings;
    private ilPlugin $pluginObject;

    public function __construct()
    {
        global $DIC;

        $this->ctrl = $DIC->ctrl();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->lng = $DIC->language();
        $this->settings = new ilUserCleanerSettingsRepository();
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

        if ($this->ctrl->getCmd(ilUserCleanerGUIConstants::CMD_SHOW) === ilUserCleanerGUIConstants::CMD_SAVE) {
            $this->save();
            return;
        }

        $this->show();
    }

    private function show(): void
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

        $cleanup_action = ilUserCleanerCleanupAction::tryFrom(
            (string) $form->getInput(ilUserCleanerGUIConstants::FIELD_CLEANUP_ACTION)
        );
        if ($cleanup_action === null) {
            $form->getItemByPostVar(ilUserCleanerGUIConstants::FIELD_CLEANUP_ACTION)?->setAlert(
                $this->lng->txt('msg_input_is_required')
            );
            $form->setValuesByPost();
            $this->tpl->setContent($form->getHTML());
            return;
        }
        $retention_value = trim((string) $form->getInput(
            ilUserCleanerGUIConstants::FIELD_PROTOCOL_RETENTION_VALUE
        ));
        $retention = null;
        if ($retention_value !== '') {
            $retention_unit = ilUserCleanerRetentionUnit::tryFrom((string) $form->getInput(
                ilUserCleanerGUIConstants::FIELD_PROTOCOL_RETENTION_UNIT
            ));
            if (!ctype_digit($retention_value) || (int) $retention_value <= 0 || $retention_unit === null) {
                $form->getItemByPostVar(ilUserCleanerGUIConstants::FIELD_PROTOCOL_RETENTION_VALUE)?->setAlert(
                    $this->lng->txt('msg_input_is_required')
                );
                $form->setValuesByPost();
                $this->tpl->setContent($form->getHTML());
                return;
            }
            $retention = new ilUserCleanerRetention(
                (int) $retention_value,
                $retention_unit
            );
        }

        $this->settings->saveDryRun(
            (bool) $form->getInput(ilUserCleanerGUIConstants::FIELD_DRY_RUN)
        );
        $this->settings->saveCleanupAction($cleanup_action);
        if ($retention === null) {
            $this->settings->deleteProtocolRetention();
        } else {
            $this->settings->saveProtocolRetention($retention);
        }
        $this->tpl->setOnScreenMessage('success', $this->lng->txt('settings_saved'), true);
        $this->ctrl->redirect($this, ilUserCleanerGUIConstants::CMD_SHOW);
    }

    private function getForm(): ilPropertyFormGUI
    {
        $form = new ilPropertyFormGUI();
        $form->setTitle($this->pluginObject->txt('settings_form_title'));
        $form->setFormAction($this->ctrl->getFormActionByClass(
            [ilObjComponentSettingsGUI::class, ilUserCleanerConfigGUI::class, self::class],
            ilUserCleanerGUIConstants::CMD_SAVE
        ));

        $dry_run = new ilCheckboxInputGUI(
            $this->pluginObject->txt('settings_dry_run'),
            ilUserCleanerGUIConstants::FIELD_DRY_RUN
        );
        $dry_run->setInfo($this->pluginObject->txt('settings_dry_run_info'));
        $dry_run->setChecked($this->settings->isDryRun());
        $form->addItem($dry_run);

        $cleanup_action = new ilRadioGroupInputGUI(
            $this->pluginObject->txt('settings_cleanup_action'),
            ilUserCleanerGUIConstants::FIELD_CLEANUP_ACTION
        );
        $cleanup_action->setRequired(true);
        $cleanup_action->setValue($this->settings->getCleanupAction()->value);

        $deactivate = new ilRadioOption(
            $this->pluginObject->txt('settings_cleanup_action_deactivate'),
            ilUserCleanerCleanupAction::DEACTIVATE->value
        );
        $deactivate->setInfo($this->pluginObject->txt('settings_cleanup_action_deactivate_info'));
        $cleanup_action->addOption($deactivate);

        $delete = new ilRadioOption(
            $this->pluginObject->txt('settings_cleanup_action_delete'),
            ilUserCleanerCleanupAction::DELETE->value
        );
        $delete->setInfo($this->pluginObject->txt('settings_cleanup_action_delete_info'));
        $cleanup_action->addOption($delete);
        $form->addItem($cleanup_action);

        $retention = $this->settings->getProtocolRetention();
        $retention_value = new ilNumberInputGUI(
            $this->pluginObject->txt('settings_protocol_retention_value'),
            ilUserCleanerGUIConstants::FIELD_PROTOCOL_RETENTION_VALUE
        );
        $retention_value->setMinValue(1);
        $retention_value->setDecimals(0);
        $retention_value->setInfo($this->pluginObject->txt('settings_protocol_retention_info'));
        $retention_value->setValue($retention === null ? '' : (string) $retention->value);
        $form->addItem($retention_value);

        $retention_unit = new ilSelectInputGUI(
            $this->pluginObject->txt('settings_protocol_retention_unit'),
            ilUserCleanerGUIConstants::FIELD_PROTOCOL_RETENTION_UNIT
        );
        $retention_unit->setOptions([
            ilUserCleanerRetentionUnit::DAYS->value => $this->pluginObject->txt('settings_retention_days'),
            ilUserCleanerRetentionUnit::MONTHS->value => $this->pluginObject->txt('settings_retention_months'),
        ]);
        $retention_unit->setValue(($retention?->unit ?? ilUserCleanerRetentionUnit::MONTHS)->value);
        $form->addItem($retention_unit);

        $form->addCommandButton(ilUserCleanerGUIConstants::CMD_SAVE, $this->lng->txt('save'));

        return $form;
    }
}
