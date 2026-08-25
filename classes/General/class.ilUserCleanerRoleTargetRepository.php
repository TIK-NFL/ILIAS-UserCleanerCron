<?php
declare(strict_types=1);

final class ilUserCleanerRoleTargetRepository extends ilUserCleanerDatabaseRepository
{
    /** @return ilUserCleanerRoleTarget[] */
    public function getAllGlobalRoles(): array
    {
        return array_map(
            ilUserCleanerRoleTarget::fromRow(...),
            $this->fetchAll(
                'SELECT od.obj_id role_id, od.title, od.description ' .
                'FROM object_data od ' .
                'JOIN rbac_fa fa ON fa.rol_id = od.obj_id ' .
                'WHERE od.type = %s AND fa.parent = %s AND fa.assign = %s ' .
                'ORDER BY od.title, od.obj_id',
                ['text', 'integer', 'text'],
                ['role', ROLE_FOLDER_ID, 'y']
            )
        );
    }

    public function getById(int $role_id): ?ilUserCleanerRoleTarget
    {
        foreach ($this->getAllGlobalRoles() as $role) {
            if ($role->roleId === $role_id) {
                return $role;
            }
        }

        return null;
    }
}
