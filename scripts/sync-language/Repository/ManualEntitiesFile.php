<?php

declare(strict_types=1);

namespace DocExtensions\SyncLanguage\Repository;

use DocExtensions\SyncLanguage\Cli\Console;
use DocExtensions\SyncLanguage\SyncException;

final readonly class ManualEntitiesFile
{
    public function __construct(
        private Paths $paths,
        private Console $console,
        private bool $check,
    ) {}

    /**
     * @param array<string, string> $needed entity name => target relative to &url.php.manual;
     * @return int number of entities added
     */
    public function addMissing(array $needed): int
    {
        $path = $this->paths->entitiesFile();
        $content = @file_get_contents($path);
        if ($content === false) {
            throw new SyncException('cannot read ' . Paths::ENTITIES_FILE);
        }

        $lines = explode("\n", $content);

        // The entity block: contiguous "<entity ...>...</entity>" lines, kept in file order.
        $first = $last = null;
        foreach ($lines as $i => $line) {
            if (str_starts_with($line, '<entity ')) {
                $first ??= $i;
                $last = $i;
            }
        }

        if ($first === null) {
            throw new SyncException(Paths::ENTITIES_FILE . ': no <entity> lines found');
        }

        $existing = [];
        for ($i = $first; $i <= $last; $i++) {
            if (!preg_match('/^<entity name="([^"]+)">(.*)<\/entity>$/', $lines[$i], $m)) {
                throw new SyncException(Paths::ENTITIES_FILE . ': unexpected line ' . ($i + 1) . ' inside the entity block');
            }

            $existing[$m[1]] = $lines[$i];
        }

        $missing = [];
        foreach ($needed as $name => $target) {
            $line = sprintf('<entity name="%s">&url.php.manual;%s</entity>', $name, $target);
            if (isset($existing[$name])) {
                if ($existing[$name] !== $line) {
                    throw new SyncException(sprintf(
                        '%s: %s is defined with a different target than the generated pages expect',
                        Paths::ENTITIES_FILE,
                        $name,
                    ));
                }

                continue;
            }

            $missing[$name] = $line;
            $this->console->stdout(($this->check ? 'Would add entity: ' : 'Entity added: ') . $name . PHP_EOL);
        }

        if ($missing === []) {
            return 0;
        }

        $block = self::insertAlphabetically(array_values($existing), $missing);
        if (!$this->check) {
            $new = array_merge(array_slice($lines, 0, $first), $block, array_slice($lines, $last + 1));
            file_put_contents($path, implode("\n", $new));
        }

        return count($missing);
    }

    /**
     * @param list<string> $block existing entity lines, left in their order
     * @param array<string, string> $missing name => line
     * @return list<string>
     */
    private static function insertAlphabetically(array $block, array $missing): array
    {
        ksort($missing, SORT_STRING);
        foreach ($missing as $name => $line) {
            $position = count($block);
            foreach ($block as $i => $existingLine) {
                preg_match('/^<entity name="([^"]+)"/', $existingLine, $m);
                if (strcmp($m[1], $name) > 0) {
                    $position = $i;
                    break;
                }
            }

            array_splice($block, $position, 0, [$line]);
        }

        return $block;
    }
}
