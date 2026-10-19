<?php

declare(strict_types=1);

namespace DocExtensions\SyncLanguage\Input;

use DocExtensions\SyncLanguage\Repository\Paths;
use DocExtensions\SyncLanguage\SyncException;

/**
 * language/manifest.json: the hand-written part of the sync. Everything that
 * can be derived from doc-en is not in here.
 *
 * Key order in the JSON objects is the output order of the generated pages.
 */
final readonly class Manifest
{
    public const array MODES = ['full', 'verbatim', 'header'];

    private const array SECTIONS = ['types', 'interfaces', 'exceptions', 'constants'];

    private function __construct(
        /** @var array<string, array{summary: string}> */
        public array $types,
        /** @var array<string, array{source: string, mode: string, summary: string}> */
        public array $interfaces,
        /** @var array<string, array{source: string, mode: string, summary: string}> */
        public array $exceptions,
        /** @var array<string, array{source: string, summary: string, page?: string}> */
        public array $constants,
    ) {}

    public function classes(): array
    {
        return $this->interfaces + $this->exceptions;
    }

    public static function load(string $path): self
    {
        $json = @file_get_contents($path);
        if ($json === false) {
            throw new SyncException('cannot read ' . Paths::MANIFEST);
        }

        try {
            $data = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new SyncException(Paths::MANIFEST . ': ' . $e->getMessage());
        }

        if (!is_array($data)) {
            throw self::invalidManifest('top level must be an object');
        }

        foreach (array_keys($data) as $key) {
            if (!in_array($key, self::SECTIONS, true)) {
                throw self::invalidManifest(sprintf('unknown section "%s"', $key));
            }
        }

        foreach (self::SECTIONS as $section) {
            if (!isset($data[$section]) || !is_array($data[$section])) {
                throw self::invalidManifest(sprintf('section "%s" must be an object', $section));
            }
        }

        foreach ($data['types'] as $id => $entry) {
            self::validateEntry('types.' . $id, $id, '/^[a-z-]+$/', $entry, ['summary'], []);
        }

        foreach (['interfaces', 'exceptions'] as $section) {
            foreach ($data[$section] as $id => $entry) {
                $where = $section . '.' . $id;
                self::validateEntry($where, $id, '/^[a-z-]+$/', $entry, ['source', 'mode', 'summary'], []);
                self::validateSource($where, $entry['source']);
                if (!in_array($entry['mode'], self::MODES, true)) {
                    throw self::invalidManifest(sprintf('%s: mode must be one of %s', $where, implode(', ', self::MODES)));
                }
            }
        }

        $duplicates = array_intersect_key($data['interfaces'], $data['exceptions']);
        if ($duplicates !== []) {
            throw self::invalidManifest('class listed in both interfaces and exceptions: ' . implode(', ', array_keys($duplicates)));
        }

        foreach ($data['constants'] as $name => $entry) {
            $where = 'constants.' . $name;
            self::validateEntry($where, $name, '/^[A-Z][A-Z0-9_]*$/', $entry, ['source', 'summary'], ['page']);
            self::validateSource($where, $entry['source']);
            if (isset($entry['page']) && (!is_string($entry['page']) || !preg_match('/^[a-z][a-z0-9.-]*$/', $entry['page']))) {
                throw self::invalidManifest($where . ': page must be a doc-en xml:id');
            }
        }

        return new self($data['types'], $data['interfaces'], $data['exceptions'], $data['constants']);
    }

    private static function validateEntry(
        string $where,
        string|int $key,
        string $keyPattern,
        mixed $entry,
        array $required,
        array $optional,
    ): void {
        if (!is_string($key) || !preg_match($keyPattern, $key)) {
            throw self::invalidManifest(sprintf('invalid key "%s" in %s', $key, $where));
        }

        if (!is_array($entry)) {
            throw self::invalidManifest($where . ' must be an object');
        }

        foreach (array_keys($entry) as $k) {
            if (!in_array($k, $required, true) && !in_array($k, $optional, true)) {
                throw self::invalidManifest(sprintf('%s: unknown key "%s"', $where, $k));
            }
        }

        foreach ($required as $k) {
            if (!isset($entry[$k]) || !is_string($entry[$k]) || trim($entry[$k]) === '') {
                throw self::invalidManifest(sprintf('%s: "%s" must be a non-empty string', $where, $k));
            }
        }
    }

    private static function validateSource(string $where, string $source): void
    {
        if (str_starts_with($source, '/') || str_contains($source, '..') || !str_ends_with($source, '.xml')) {
            throw self::invalidManifest($where . ': source must be a relative .xml path inside doc-en');
        }
    }

    private static function invalidManifest(string $message): SyncException
    {
        return new SyncException(Paths::MANIFEST . ': ' . $message);
    }
}
