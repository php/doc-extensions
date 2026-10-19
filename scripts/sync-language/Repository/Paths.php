<?php

declare(strict_types=1);

namespace DocExtensions\SyncLanguage\Repository;

final readonly class Paths
{
    public const string SCRIPT = 'scripts/sync-language.php';
    public const string MANIFEST = 'language/manifest.json';
    public const string LOCK = 'language/sync.lock';
    public const string LANGUAGE_DIR = 'language';
    public const string ENTITIES_FILE = 'entities/entities.manual.ent';

    public function __construct(private string $repoRoot)
    {}

    public function absolute(string $relative): string
    {
        return $this->repoRoot . '/' . $relative;
    }

    public function manifest(): string
    {
        return $this->absolute(self::MANIFEST);
    }

    public function lock(): string
    {
        return $this->absolute(self::LOCK);
    }

    public function languageDir(): string
    {
        return $this->absolute(self::LANGUAGE_DIR);
    }

    public function entitiesFile(): string
    {
        return $this->absolute(self::ENTITIES_FILE);
    }

    public function docEnCandidates(): array
    {
        return [
            $this->repoRoot . '/../doc-en',
            $this->repoRoot . '/../en'
        ];
    }
}
