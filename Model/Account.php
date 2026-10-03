<?php

namespace Canva\Model;

/** The connected account: its ids, and its display name when the profile scope was granted. */
final class Account
{
    public function __construct(
        public readonly string $userId,
        public readonly ?string $teamId,
        public readonly ?string $displayName = null,
    ) {
    }
}
