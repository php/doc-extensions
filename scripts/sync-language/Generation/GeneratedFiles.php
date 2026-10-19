<?php

declare(strict_types=1);

namespace DocExtensions\SyncLanguage\Generation;

use DocExtensions\SyncLanguage\SyncException;

final class GeneratedFiles
{
    /** @var array<string, string> path relative to the repo root => content */
    public array $files = [];

    /** @var array<string, string> entity name => target relative to &url.php.manual; */
    public array $entities = [];

    /** @var array<string, true> lowercased names that need a versions.xml entry */
    public array $versionNames = [];

    /** @var array<string, true> doc-en directories whose versions.xml is consulted */
    public array $versionDirs = [];

    public function addFile(string $relative, string $content): void
    {
        if (preg_match('/[ \t]+$/m', $content)) {
            throw new SyncException('generated ' . $relative . ' would contain trailing whitespace');
        }

        $this->files[$relative] = $content;
    }

    public function linkEntity(string $id, ?string $target = null): string
    {
        $this->entities['url.php.manual.' . $id] = $target ?? ($id . '.php');

        return '&url.php.manual.' . $id . ';';
    }

    public function requireVersionEntry(string $name): void
    {
        $this->versionNames[strtolower($name)] = true;
    }

    public function useVersionsFile(string $docEnDir): void
    {
        $this->versionDirs[$docEnDir] = true;
    }
}
