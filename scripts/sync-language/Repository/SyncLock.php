<?php

declare(strict_types=1);

namespace DocExtensions\SyncLanguage\Repository;

use DocExtensions\SyncLanguage\Cli\Console;
use DocExtensions\SyncLanguage\Input\DocEnCheckout;

final readonly class SyncLock
{
    public function __construct(
        private Paths $paths,
        private Console $console,
    ) {}

    public function write(DocEnCheckout $docEn): void
    {
        $commit = $docEn->headCommit();
        $lock = json_encode(['doc-en' => $commit, 'synced' => gmdate('Y-m-d')], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        file_put_contents($this->paths->lock(), $lock);
        $this->console->stdout('Updated ' . Paths::LOCK . ' (doc-en ' . substr($commit, 0, 12) . ').' . PHP_EOL);
    }
}
