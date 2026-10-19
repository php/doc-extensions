<?php

declare(strict_types=1);

namespace DocExtensions\SyncLanguage\Repository;

final readonly class FileChanges
{
    public function __construct(
        public int $written,
        public int $deleted,
    ) {}
}
