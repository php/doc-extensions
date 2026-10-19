<?php

declare(strict_types=1);

namespace DocExtensions\SyncLanguage\Cli;

use DocExtensions\SyncLanguage\Generation\PageGenerator;
use DocExtensions\SyncLanguage\Input\DocEnCheckout;
use DocExtensions\SyncLanguage\Input\Manifest;
use DocExtensions\SyncLanguage\Repository\LanguageDirectory;
use DocExtensions\SyncLanguage\Repository\ManualEntitiesFile;
use DocExtensions\SyncLanguage\Repository\Paths;
use DocExtensions\SyncLanguage\Repository\SyncLock;
use DocExtensions\SyncLanguage\SyncException;

final readonly class Application
{
    private const string USAGE = <<<'TXT'
Usage: php scripts/sync-language.php [--doc-en=PATH] [--check] [--help]

Regenerates language/ from language/manifest.json and a php/doc-en checkout.
  --doc-en=PATH  doc-en checkout (default: ../doc-en or ../en next to this repo)
  --check        write nothing; exit 1 if language/ is out of date

TXT;

    /** @param list<string> $arguments */
    private function __construct(
        private array $arguments,
        private Paths $paths,
        private Console $console,
    ) {}

    /** @param list<string> $argv */
    public static function withArguments(array $argv, string $repoRoot): self
    {
        array_shift($argv);

        return new self($argv, new Paths($repoRoot), new Console());
    }

    public function run(): int
    {
        try {
            return $this->sync(
                $this->parseOptions()
            );
        } catch (SyncException $e) {
            $this->console->reportError($e->getMessage());

            return 1;
        }
    }

    private function parseOptions(): Options
    {
        $docEn = null;
        $check = false;
        foreach ($this->arguments as $argument) {
            if ($argument === '--help' || $argument === '-h') {
                $this->console->stdout(self::USAGE);
                exit(0);
            }

            if ($argument === '--check') {
                $check = true;
                continue;
            }

            if (str_starts_with($argument, '--doc-en=')) {
                $docEn = substr($argument, strlen('--doc-en='));
                continue;
            }

            $this->console->stderr('Unknown option: ' . $argument . PHP_EOL . PHP_EOL . self::USAGE);
            exit(1);
        }

        return new Options($this->resolveDocEn($docEn), $check);
    }

    private function resolveDocEn(?string $docEn): string
    {
        if ($docEn === null) {
            foreach ($this->paths->docEnCandidates() as $candidate) {
                if (is_dir($candidate)) {
                    $docEn = $candidate;
                    break;
                }
            }

            if ($docEn === null) {
                throw new SyncException('no doc-en checkout found next to this repository; pass --doc-en=PATH');
            }
        }

        $real = realpath($docEn);
        if ($real === false || !is_dir($real)) {
            throw new SyncException('doc-en directory not found: ' . $docEn);
        }

        return $real;
    }

    private function sync(Options $options): int
    {
        $docEn = new DocEnCheckout($options->docEn);

        $this->console->stdout('Reading ' . Paths::MANIFEST . ' ... ');
        $manifest = Manifest::load($this->paths->manifest());
        $this->console->stdout(sprintf(
            'done: %d types, %d interfaces, %d exceptions, %d constants.' . PHP_EOL,
            count($manifest->types),
            count($manifest->interfaces),
            count($manifest->exceptions),
            count($manifest->constants),
        ));

        $this->console->stdout('Generating pages from doc-en (' . $options->docEn . ') ... ');
        $output = (new PageGenerator($docEn))->generate($manifest);
        $this->console->stdout('done: ' . count($output->files) . ' files.' . PHP_EOL);

        $languageDirectory = new LanguageDirectory($this->paths, $this->console, $options->check);
        $files = $languageDirectory->write($output);
        $entities = (new ManualEntitiesFile($this->paths, $this->console, $options->check))->addMissing($output->entities);
        $changes = $files->written + $files->deleted + $entities;

        if ($options->check) {
            if ($changes === 0) {
                $this->console->stdout(Paths::LANGUAGE_DIR . '/ is in sync with ' . Paths::MANIFEST . '.' . PHP_EOL);

                return 0;
            }

            $this->console->stdout(sprintf(
                '%s/ is out of date (%d changes); run: php %s' . PHP_EOL,
                Paths::LANGUAGE_DIR,
                $changes,
                Paths::SCRIPT,
            ));

            return 1;
        }

        if ($changes > 0) {
            (new SyncLock($this->paths, $this->console))->write($docEn);
        }

        $this->console->stdout(sprintf(
            'Summary: %d written, %d deleted, %d entities added.' . PHP_EOL,
            $files->written,
            $files->deleted,
            $entities,
        ));

        return 0;
    }
}
