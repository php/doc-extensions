<?php

declare(strict_types=1);

namespace DocExtensions\SyncLanguage\Input;

use DocExtensions\SyncLanguage\Repository\Paths;
use DocExtensions\SyncLanguage\SyncException;

final readonly class DocEnCheckout
{
    public function __construct(public string $path) {}

    public function readFile(string $relative): string
    {
        $content = @file_get_contents($this->path . '/' . $relative);
        if ($content === false) {
            throw new SyncException('doc-en file missing: ' . $relative);
        }

        return $content;
    }

    public function hasDirectory(string $relative): bool
    {
        return is_dir($this->path . '/' . $relative);
    }

    /** @return list<string> sorted, without the .xml extension */
    public function xmlBaseNames(string $relative): array
    {
        $names = array_map(
            static fn(string $file): string => basename($file, '.xml'),
            glob(sprintf('%s/%s/*.xml', $this->path, $relative)) ?: [],
        );
        sort($names, SORT_STRING);

        return $names;
    }

    public function headCommit(): string
    {
        $output = [];
        $status = 1;
        exec('git -C ' . escapeshellarg($this->path) . ' rev-parse HEAD 2>/dev/null', $output, $status);
        if ($status !== 0 || !isset($output[0]) || !preg_match('/^[0-9a-f]{40}$/', $output[0])) {
            throw new SyncException(sprintf(
                'doc-en at %s is not a git checkout; cannot record its revision in %s',
                $this->path,
                Paths::LOCK,
            ));
        }

        return $output[0];
    }
}
