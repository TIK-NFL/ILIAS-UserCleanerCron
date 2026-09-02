<?php
declare(strict_types=1);

$ilias_root = getenv('ILIAS_ROOT');
if (!is_string($ilias_root) || $ilias_root === '') {
    $ilias_root = dirname(__DIR__, 9);
}
$autoload = $ilias_root . '/vendor/composer/vendor/autoload.php';
if (!is_file($autoload)) {
    throw new RuntimeException(sprintf(
        'ILIAS Composer autoloader not found at %s. Set ILIAS_ROOT to the ILIAS installation.',
        $autoload
    ));
}
require_once $autoload;

defined('ANONYMOUS_USER_ID') || define('ANONYMOUS_USER_ID', 13);
defined('SYSTEM_USER_ID') || define('SYSTEM_USER_ID', 6);

require_once __DIR__ . '/../classes/Evaluation/class.ilUserCleanerDecision.php';
require_once __DIR__ . '/../classes/Settings/enum.ilUserCleanerRetentionUnit.php';
require_once __DIR__ . '/../classes/Settings/class.ilUserCleanerRetention.php';
require_once __DIR__ . '/../classes/GUI/class.ilUserCleanerGUIConstants.php';
require_once __DIR__ . '/../classes/Rule/enum.ilUserCleanerRuleSource.php';
require_once __DIR__ . '/../classes/Rule/class.ilUserCleanerRule.php';
require_once __DIR__ . '/../classes/Rule/class.ilUserCleanerRuleSet.php';
require_once __DIR__ . '/../classes/Evaluation/class.ilUserCleanerEvaluationUser.php';
require_once __DIR__ . '/../classes/Evaluation/class.ilUserCleanerEvaluationResult.php';
require_once __DIR__ . '/../classes/Evaluation/interface.ilUserCleanerEvaluationData.php';
require_once __DIR__ . '/../classes/LDAP/interface.ilUserCleanerLDAPAccountLookup.php';
require_once __DIR__ . '/../classes/Evaluation/class.ilUserCleanerEvaluator.php';
