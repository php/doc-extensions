<?php

declare(strict_types=1);

namespace DocExtensions\SyncLanguage\Repository;

use DocExtensions\SyncLanguage\Cli\Console;
use DocExtensions\SyncLanguage\Generation\GeneratedFiles;

final readonly class LanguageDirectory
{
    public function __construct(
        private Paths $paths,
        private Console $console,
        private bool $check,
    ) {}

    public function write(GeneratedFiles $output): FileChanges
    {
        $written = 0;
        $deleted = 0;

        $files = $output->files;
        ksort($files, SORT_STRING);

        foreach ($files as $relative => $content) {
            $path = $this->paths->absolute($relative);
            if (is_file($path) && file_get_contents($path) === $content) {
                continue;
            }

            $written++;
            $this->console->stdout(($this->check ? 'Would write: ' : 'Written: ') . $relative . PHP_EOL);
            if (!$this->check) {
                if (!is_dir(dirname($path))) {
                    mkdir(dirname($path), 0777, true);
                }

                file_put_contents($path, $content);
            }
        }

        foreach ($this->existingLanguageFiles() as $relative) {
            if (isset($files[$relative])) {
                continue;
            }

            $deleted++;
            $this->console->stdout(($this->check ? 'Would delete: ' : 'Deleted: ') . $relative . PHP_EOL);
            if (!$this->check) {
                $this->deleteWithEmptyParents($relative);
            }
        }

        return new FileChanges($written, $deleted);
    }

    /** @return list<string> relative to the repo root */
    private function existingLanguageFiles(): array
    {
        $dir = $this->paths->languageDir();
        if (!is_dir($dir)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.xml')) {
                $files[] = Paths::LANGUAGE_DIR . '/' . substr($file->getPathname(), strlen($dir) + 1);
            }
        }

        sort($files, SORT_STRING);

        return $files;
    }

    private function deleteWithEmptyParents(string $relative): void
    {
        $path = $this->paths->absolute($relative);
        unlink($path);
        $dir = dirname($path);
        while ($dir !== $this->paths->languageDir() && is_dir($dir) && count(scandir($dir)) === 2) {
            rmdir($dir);
            $dir = dirname($dir);
        }
    }
}
