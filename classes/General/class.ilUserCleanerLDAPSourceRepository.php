<?php
declare(strict_types=1);

final class ilUserCleanerLDAPSourceRepository extends ilUserCleanerDatabaseRepository
{
    /** @return ilUserCleanerLDAPSource[] */
    public function getAll(): array
    {
        if (!$this->database->tableExists('ldap_server_settings')) {
            return [];
        }

        return array_map(
            ilUserCleanerLDAPSource::fromRow(...),
            $this->fetchAll(
                'SELECT server_id, name, active, authentication, authentication_type ' .
                'FROM ldap_server_settings ORDER BY name, server_id'
            )
        );
    }

    public function getById(int $id): ?ilUserCleanerLDAPSource
    {
        if (!$this->database->tableExists('ldap_server_settings')) {
            return null;
        }
        $row = $this->fetchRow(
            'SELECT server_id, name, active, authentication, authentication_type ' .
            'FROM ldap_server_settings WHERE server_id = %s',
            ['integer'],
            [$id]
        );

        return $row === null ? null : ilUserCleanerLDAPSource::fromRow($row);
    }
}
