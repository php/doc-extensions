<?php

declare(strict_types=1);

namespace DocExtensions\SyncLanguage\Generation;

use DocExtensions\SyncLanguage\SyncException;

/**
 * The generated files are filled-in copies of templates/*.tpl
 * in the format of doc-base/scripts/docgen.
 */
final class PageTemplate
{
    private const string DIR = __DIR__ . '/templates';

    /** @var array<string, string> */
    private static array $loaded = [];

    /** @param array<string, string> $values */
    public static function render(string $name, array $values = []): string
    {
        $template = self::load($name);

        preg_match_all('/{([A-Z_]+)}/', $template, $matches);

        $placeholders = array_unique($matches[1]);
        $missing = array_diff($placeholders, array_keys($values));
        $unused = array_diff(array_keys($values), $placeholders);

        if ($missing !== []) {
            throw new SyncException(sprintf('template %s: no value for %s', $name, implode(', ', $missing)));
        }

        if ($unused !== []) {
            throw new SyncException(sprintf('template %s: no placeholder for %s', $name, implode(', ', $unused)));
        }

        $replacements = [];
        foreach ($values as $key => $value) {
            $replacements['{' . $key . '}'] = $value;
        }

        return strtr($template, $replacements);
    }

    private static function load(string $name): string
    {
        if (!isset(self::$loaded[$name])) {
            $file = self::DIR . '/' . $name . '.tpl';
            $content = @file_get_contents($file);
            if ($content === false) {
                throw new SyncException('template file missing: ' . $file);
            }

            self::$loaded[$name] = $content;
        }

        return self::$loaded[$name];
    }
}
