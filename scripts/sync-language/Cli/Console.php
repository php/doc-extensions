<?php

declare(strict_types=1);

namespace DocExtensions\SyncLanguage\Cli;

final readonly class Console
{
    public function stdout(string $text): void
    {
        fwrite(STDOUT, $text);
    }

    public function stderr(string $text): void
    {
        fwrite(STDERR, $text);
    }

    public function reportError(string $message): void
    {
        if (getenv('GITHUB_ACTIONS') !== false) {
            $this->stderr('::error::' . str_replace("\n", ' ', $message) . PHP_EOL);
            
            return;
        }

        $this->stderr('Error: ' . $message . PHP_EOL);
    }
}
