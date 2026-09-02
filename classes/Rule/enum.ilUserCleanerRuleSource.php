<?php
declare(strict_types=1);

enum ilUserCleanerRuleSource: string
{
    case LOCAL_DATABASE = 'local_database';
    case LDAP = 'ldap';
    case REST = 'rest';
}
