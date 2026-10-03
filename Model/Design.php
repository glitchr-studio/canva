<?php

namespace Canva\Model;

/** One of the account's designs, as the API lists it. */
final class Design
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly ?string $thumbnailUrl,
        public readonly ?int $thumbnailWidth,
        public readonly ?int $thumbnailHeight,
        public readonly ?string $editUrl,
        public readonly ?string $viewUrl,
        public readonly ?int $pageCount,
        public readonly ?\DateTimeImmutable $createdAt,
        public readonly ?\DateTimeImmutable $updatedAt,
    ) {
    }

    /** @param array<string, mixed> $data an item of GET /v1/designs, or the "design" of GET /v1/designs/{id} */
    public static function fromArray(array $data): self
    {
        $at = static fn ($v) => \is_int($v) ? (new \DateTimeImmutable())->setTimestamp($v) : (\is_string($v) && '' !== $v ? new \DateTimeImmutable($v) : null);

        return new self(
            (string) $data['id'],
            (string) ($data['title'] ?? ''),
            $data['thumbnail']['url'] ?? null,
            isset($data['thumbnail']['width']) ? (int) $data['thumbnail']['width'] : null,
            isset($data['thumbnail']['height']) ? (int) $data['thumbnail']['height'] : null,
            $data['urls']['edit_url'] ?? null,
            $data['urls']['view_url'] ?? null,
            isset($data['page_count']) ? (int) $data['page_count'] : null,
            $at($data['created_at'] ?? null),
            $at($data['updated_at'] ?? null),
        );
    }
}
