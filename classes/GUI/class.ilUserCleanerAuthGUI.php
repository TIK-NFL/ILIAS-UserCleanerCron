<?php
declare(strict_types=1);

/**
 * @ilCtrl_isCalledBy ilUserCleanerAuthGUI: ilUserCleanerConfigGUI
 * @ilCtrl_Calls ilUserCleanerAuthGUI:
 */
final class ilUserCleanerAuthGUI
{
    private ilGlobalTemplateInterface $template;
    private ilUserCleanerLDAPSourceRepository $ldapSources;
    private ilPlugin $pluginObject;

    public function __construct()
    {
        global $DIC;

        $this->template = $DIC->ui()->mainTemplate();
        $this->ldapSources = new ilUserCleanerLDAPSourceRepository();
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

        $this->show();
    }

    private function show(): void
    {
        $form = new ilPropertyFormGUI();
        $form->setTitle($this->pluginObject->txt('auth_form_title'));

        $ldap = new ilNonEditableValueGUI($this->pluginObject->txt('auth_ldap_existing_sources'));
        $ldap->setValue($this->getLDAPSourceSummary());
        $ldap->setInfo($this->pluginObject->txt('auth_ldap_existing_sources_info'));
        $form->addItem($ldap);

        $rest = new ilNonEditableValueGUI($this->pluginObject->txt('auth_rest_section_title'));
        $rest->setValue($this->pluginObject->txt('auth_rest_not_implemented'));
        $form->addItem($rest);

        $this->template->setContent($form->getHTML());
    }

    private function getLDAPSourceSummary(): string
    {
        $labels = [];
        foreach ($this->ldapSources->getAll() as $source) {
            $usages = [];
            if ($source->authentication) {
                $usages[] = $this->pluginObject->txt('rule_source_ldap_authentication');
            }
            if ($source->isDataSource()) {
                $usages[] = $this->pluginObject->txt('rule_source_ldap_data_source');
            }
            $labels[] = sprintf(
                '%s (#%d) — %s',
                $source->name,
                $source->id,
                implode(' / ', $usages)
            );
        }

        return $labels === []
            ? $this->pluginObject->txt('auth_ldap_no_existing_sources')
            : implode(', ', $labels);
    }
}
