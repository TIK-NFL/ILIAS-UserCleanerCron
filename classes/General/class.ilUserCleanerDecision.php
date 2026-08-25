<?php
declare(strict_types=1);

final class ilUserCleanerDecision
{
    /** @param bool[] $rule_results */
    public static function matchesRuleSet(array $rule_results): bool
    {
        return $rule_results !== [] && !in_array(false, $rule_results, true);
    }

    /** @param bool[] $rule_set_results */
    public static function matchesAnyRuleSet(array $rule_set_results): bool
    {
        return in_array(true, $rule_set_results, true);
    }

    public static function matchesAuthMode(string $user_mode, string $configured_mode): bool
    {
        return $user_mode === $configured_mode
            || ($configured_mode === 'ldap' && str_starts_with($user_mode, 'ldap_'));
    }

    public static function isProtected(
        int $user_id,
        int $actor_id,
        bool $is_administrator,
        bool $is_excluded
    ): bool {
        return $user_id === ANONYMOUS_USER_ID
            || $user_id === SYSTEM_USER_ID
            || $user_id === $actor_id
            || $is_administrator
            || $is_excluded;
    }

    public static function ageInDays(?string $last_login, DateTimeImmutable $now): int
    {
        if ($last_login === null || trim($last_login) === '') {
            return PHP_INT_MAX;
        }
        $login = new DateTimeImmutable($last_login);
        return $login > $now ? 0 : (int) $login->diff($now)->format('%a');
    }

    public static function compare(int $actual, string $symbol, int $expected): bool
    {
        return match ($symbol) {
            '=' => $actual === $expected,
            '!=' => $actual !== $expected,
            '<' => $actual < $expected,
            '<=' => $actual <= $expected,
            '>' => $actual > $expected,
            '>=' => $actual >= $expected,
            default => false,
        };
    }
}
