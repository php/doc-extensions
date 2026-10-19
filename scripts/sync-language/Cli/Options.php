<?php

declare(strict_types=1);

namespace DocExtensions\SyncLanguage\Cli;

final readonly class Options
{
    public function __construct(
        public string $docEn,
        public bool $check,
    ) {}
}
