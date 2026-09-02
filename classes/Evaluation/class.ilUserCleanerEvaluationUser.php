<?php
declare(strict_types=1);

final class ilUserCleanerEvaluationUser
{
    public function __construct(
        public readonly int $id,
        public readonly string $authMode,
        public readonly ?string $lastLogin,
        public readonly string $createDate,
        public readonly string $externalAccount,
        public readonly string $login
    ) {
    }

    public static function fromUser(ilObjUser $user, ?string $account_create_date = null): self
    {
        return new self(
            $user->getId(),
            (string) $user->getAuthMode(),
            $user->getLastLogin(),
            $account_create_date ?? $user->getCreateDate(),
            $user->getExternalAccount(),
            $user->getLogin()
        );
    }
}
