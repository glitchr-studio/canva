<?php

namespace Canva;

use Canva\Model\Token;

/**
 * Where the connected account's token lives: the application decides (a
 * secured setting, a user's row, a file). One account per storage.
 */
interface TokenStorageInterface
{
    public function load(): ?Token;

    public function save(Token $token): void;

    public function clear(): void;
}
