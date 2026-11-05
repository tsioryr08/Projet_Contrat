<?php

declare(strict_types=1);

namespace App\Services;

final class ContratChunk
{
    public function __construct(
        public readonly string $texte,
        public readonly ?int $page,
        public readonly ?string $article,
        public readonly int $index,
    ) {
    }

    public function longueur(): int
    {
        return mb_strlen($this->texte);
    }
}
