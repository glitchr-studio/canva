<?php

namespace Canva;

use Canva\Model\Token;

/** The token in a JSON file: the harness's storage, and the bundle's default until the application names its own. */
final class FileTokenStorage implements TokenStorageInterface
{
    public function __construct(private readonly string $path)
    {
    }

    public function load(): ?Token
    {
        if (!is_file($this->path)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($this->path), true);

        return \is_array($data) && isset($data['access_token']) ? Token::fromArray($data) : null;
    }

    public function save(Token $token): void
    {
        if (!is_dir(\dirname($this->path))) {
            mkdir(\dirname($this->path), 0770, true);
        }
        file_put_contents($this->path, json_encode($token->toArray(), \JSON_PRETTY_PRINT), \LOCK_EX);
        chmod($this->path, 0600);
    }

    public function clear(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }
}
