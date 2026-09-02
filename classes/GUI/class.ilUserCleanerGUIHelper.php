<?php
declare(strict_types=1);

final class ilUserCleanerGUIHelper
{
    public static function preserveComponentParameters(array $classes): void
    {
        global $DIC;

        $query = $DIC->http()->request()->getQueryParams();
        foreach ($classes as $class) {
            foreach (ilUserCleanerGUIConstants::COMPONENT_PARAMETERS as $parameter) {
                if (!isset($query[$parameter]) || !is_string($query[$parameter])) {
                    continue;
                }

                $DIC->ctrl()->setParameterByClass(
                    $class,
                    $parameter,
                    ilUtil::stripSlashes($query[$parameter])
                );
            }
        }
    }

    private function __construct()
    {
    }
}
