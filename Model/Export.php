<?php

namespace Canva\Model;

/** An export job: in progress, done (its file URLs, short-lived), or failed. */
final class Export
{
    public const IN_PROGRESS = 'in_progress';
    public const SUCCESS = 'success';
    public const FAILED = 'failed';

    /** @param list<string> $urls */
    public function __construct(
        public readonly string $id,
        public readonly string $status,
        public readonly array $urls = [],
        public readonly ?string $error = null,
    ) {
    }

    /** @param array<string, mixed> $data the "job" of POST /v1/exports or GET /v1/exports/{id} */
    public static function fromArray(array $data): self
    {
        return new self((string) $data['id'], (string) ($data['status'] ?? self::IN_PROGRESS), array_values(array_map('strval', $data['urls'] ?? [])), $data['error']['message'] ?? $data['error']['code'] ?? null);
    }

    public function isDone(): bool { return self::IN_PROGRESS !== $this->status; }
    public function isSuccess(): bool { return self::SUCCESS === $this->status; }
}
