<?php

namespace Canva\Model;

/** An OAuth token: what calls the API, what renews it, and until when it lasts. */
final class Token
{
    public function __construct(
        public readonly string $accessToken,
        public readonly ?string $refreshToken,
        public readonly \DateTimeImmutable $expiresAt,
        public readonly string $scope = '',
    ) {
    }

    /** @param array{access_token: string, refresh_token?: string, expires_in?: int, scope?: string} $data the token endpoint's answer */
    public static function fromResponse(array $data, ?\DateTimeImmutable $now = null): self
    {
        $now ??= new \DateTimeImmutable();

        return new self($data['access_token'], $data['refresh_token'] ?? null, $now->modify(sprintf('+%d seconds', (int) ($data['expires_in'] ?? 3600))), (string) ($data['scope'] ?? ''));
    }

    /** @return array{access_token: string, refresh_token: ?string, expires_at: string, scope: string} */
    public function toArray(): array
    {
        return ['access_token' => $this->accessToken, 'refresh_token' => $this->refreshToken, 'expires_at' => $this->expiresAt->format(\DateTimeInterface::ATOM), 'scope' => $this->scope];
    }

    /** @param array{access_token: string, refresh_token?: ?string, expires_at: string, scope?: string} $data */
    public static function fromArray(array $data): self
    {
        return new self($data['access_token'], $data['refresh_token'] ?? null, new \DateTimeImmutable($data['expires_at']), (string) ($data['scope'] ?? ''));
    }

    /** Expired, or about to (a minute's margin). */
    public function isExpired(?\DateTimeImmutable $now = null): bool
    {
        return ($now ?? new \DateTimeImmutable())->modify('+60 seconds') >= $this->expiresAt;
    }
}
