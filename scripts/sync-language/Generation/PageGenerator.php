<?php

declare(strict_types=1);

namespace DocExtensions\SyncLanguage\Generation;

use DocExtensions\SyncLanguage\Input\DocEnCheckout;
use DocExtensions\SyncLanguage\Input\Manifest;
use DocExtensions\SyncLanguage\SyncException;

final readonly class PageGenerator
{
    public function __construct(private DocEnCheckout $docEn) {}

    public function generate(Manifest $manifest): GeneratedFiles
    {
        $output = new GeneratedFiles();
        $classes = $manifest->classes();

        $this->generateTypes($manifest->types, $output);
        foreach ($classes as $id => $entry) {
            $this->generateClass($id, $entry, $classes, $output);
        }

        $this->generatePart(
            'language/predefined/interfaces.xml',
            'reserved.interfaces',
            'Predefined Interfaces and Classes',
            'Summaries of the interfaces and classes of PHP and its bundled extensions that third-party extensions extend, implement or accept.',
            array_keys($manifest->interfaces),
            $output,
        );
        $this->generatePart(
            'language/predefined/exceptions.xml',
            'reserved.exceptions',
            'Predefined Exceptions',
            'Summaries of the exception and error classes of PHP and its bundled extensions that third-party extensions extend or throw.',
            array_keys($manifest->exceptions),
            $output,
        );
        $this->generateConstants($manifest->constants, $output);
        $this->generateVersions($output);

        return $output;
    }

    private function generateTypes(array $types, GeneratedFiles $output): void
    {
        foreach ($types as $id => $entry) {
            $sourceRelative = 'language/types/' . $id . '.xml';
            $source = $this->docEn->readFile($sourceRelative);
            $title = XmlText::firstMatch('/^ <title>(.*?)<\/title>$/m', $source, 'title of ' . $sourceRelative);
            $summary = XmlText::wrapText($entry['summary'], 2);
            $link = $output->linkEntity('language.types.' . $id);
            $this->addPage($sourceRelative, $sourceRelative, 'type', [
                'ID' => $id,
                'TITLE' => $title,
                'SUMMARY' => $summary,
                'LINK' => $link,
            ], $output);
        }

        $refs = implode("\n", array_map(static fn(string $id): string => ' &language.types.' . $id . ';', array_keys($types)));
        $link = $output->linkEntity('language.types');
        $this->addPage('language/types.xml', null, 'types', [
            'LINK' => $link,
            'TYPES' => $refs,
        ], $output);
    }

    private function generateClass(string $id, array $entry, array $classes, GeneratedFiles $output): void
    {
        $sourceRelative = $entry['source'];
        $mode = $entry['mode'];
        $source = $this->docEn->readFile($sourceRelative);
        $what = sprintf('class %s (%s)', $id, $sourceRelative);

        $role = XmlText::firstMatch(
            '/<reference xml:id="class\.' . preg_quote($id, '/') . '" role="([a-z]+)"/',
            $source,
            sprintf('<reference xml:id="class.%s"> in %s', $id, $sourceRelative),
        );
        $title = XmlText::firstMatch('/^ <title>(.*?)<\/title>$/m', $source, 'title of ' . $what);
        $titleabbrev = XmlText::firstMatch('/^ <titleabbrev>(.*?)<\/titleabbrev>$/m', $source, 'titleabbrev of ' . $what);
        $synopsis = XmlText::extractBlock($source, 'classsynopsis', 3, $what);
        $kind = XmlText::firstMatch('/<classsynopsis class="(interface|class)">/', $synopsis, 'classsynopsis kind of ' . $what);
        $output->requireVersionEntry($titleabbrev);
        $output->useVersionsFile(dirname($sourceRelative));

        if ($mode === 'header') {
            preg_match_all('/^( *)<(oo(?:class|interface|exception))>.*?^\1<\/\2>/ms', $synopsis, $mm, PREG_SET_ORDER);
            if ($mm === []) {
                throw new SyncException($what . ': no ooclass/oointerface/ooexception in the synopsis');
            }

            $parts = array_map(static fn(array $m): string => XmlText::reindent($m[0], strlen($m[1]), 4), $mm);
            $synopsis = '   <classsynopsis class="' . $kind . '">' . "\n" . implode("\n", $parts) . "\n" . '   </classsynopsis>';
        } else {
            // Ids on individual synopsis entries would be duplicated on every page including them.
            $synopsis = preg_replace('/ xml:id="class\.[a-z-]+\.\.[a-z-]+"/', '', $synopsis);
            XmlText::assertKnownXPointers($synopsis, $classes, $what);
        }

        XmlText::assertNoForeignReferences($synopsis, $id . '.props.', $what);

        $props = '';
        if ($mode === 'full' && preg_match('/^( *)<section xml:id="' . preg_quote($id, '/') . '\.props">.*?^\1<\/section>/ms', $source, $pm)) {
            $props = XmlText::reindent($pm[0], strlen($pm[1]), 2);
            $props = preg_replace('/<para>([^<\n]*)<\/para>/', '<simpara>$1</simpara>', $props);
            $props = "\n\n" . $props;
        }

        $methodRefs = '';
        if ($mode === 'full') {
            $methodDir = substr($sourceRelative, 0, -4);
            if (!$this->docEn->hasDirectory($methodDir)) {
                throw new SyncException(sprintf('%s: mode "full" needs the method directory doc-en %s', $what, $methodDir));
            }

            foreach ($this->methodsInDocEnOrder($id, $source, $this->docEn->xmlBaseNames($methodDir)) as $method) {
                $this->generateMethodStub($id, $method, $methodDir . '/' . $method . '.xml', $output);
                $methodRefs .= "\n" . sprintf(' &language.predefined.%s.%s;', $id, $method);
            }

            $methodRefs .= "\n";
        }

        $synopsisTitle = $kind === 'interface' ? '&reftitle.interfacesynopsis;' : '&reftitle.classsynopsis;';
        $summary = XmlText::wrapText($entry['summary'], 4);
        $link = $output->linkEntity('class.' . $id);

        $this->addPage('language/predefined/' . $id . '.xml', $sourceRelative, 'class', [
            'CLASS_ID' => $id,
            'ROLE' => $role,
            'TITLE' => $title,
            'CLASS_NAME' => $titleabbrev,
            'SUMMARY' => $summary,
            'LINK' => $link,
            'SYNOPSIS_TITLE' => $synopsisTitle,
            'SYNOPSIS' => $synopsis,
            'PROPERTIES' => $props,
            'METHODS' => $methodRefs,
        ], $output);
    }

    /**
     * @param list<string> $methods
     * @return list<string>
     */
    private function methodsInDocEnOrder(string $id, string $source, array $methods): array
    {
        preg_match_all('/^ *&[a-z.-]+\.' . preg_quote($id, '/') . '\.([a-z]+);$/m', $source, $m);
        $order = array_flip($m[1]);
        usort(
            $methods,
            static fn(string $a, string $b): int => ($order[$a] ?? PHP_INT_MAX) <=> ($order[$b] ?? PHP_INT_MAX) ?: strcmp($a, $b),
        );

        return $methods;
    }

    private function generateMethodStub(string $classId, string $method, string $sourceRelative, GeneratedFiles $output): void
    {
        $source = $this->docEn->readFile($sourceRelative);
        $what = sprintf('method %s::%s (%s)', $classId, $method, $sourceRelative);
        $refnamediv = XmlText::extractBlock($source, 'refnamediv', 1, $what);
        $synopsis = XmlText::extractBlock($source, 'methodsynopsis|constructorsynopsis', 2, $what);
        XmlText::assertNoForeignReferences($synopsis, '', $what);
        $refname = XmlText::firstMatch('/<refname>(.*?)<\/refname>/', $refnamediv, 'refname of ' . $what);
        $refid = $classId . '.' . $method;
        $link = $output->linkEntity($refid);
        $output->requireVersionEntry($refname);

        $this->addPage(sprintf('language/predefined/%s/%s.xml', $classId, $method), $sourceRelative, 'method', [
            'METHOD_ID' => $refid,
            'REFNAMEDIV' => $refnamediv,
            'SYNOPSIS' => $synopsis,
            'LINK' => $link,
            'METHOD_NAME' => $refname,
        ], $output);
    }

    /** @param list<string> $members */
    private function generatePart(string $file, string $id, string $title, string $intro, array $members, GeneratedFiles $output): void
    {
        $link = $output->linkEntity($id);
        $refs = implode("\n", array_map(static fn(string $m): string => ' &language.predefined.' . $m . ';', $members));
        $intro = XmlText::wrapText($intro . ' The full documentation is in the PHP manual:', 3);
        $this->addPage($file, null, 'part', [
            'ID' => $id,
            'TITLE' => $title,
            'INTRO' => $intro,
            'LINK' => $link,
            'CLASSES' => $refs,
        ], $output);
    }

    private function generateConstants(array $constants, GeneratedFiles $output): void
    {
        $entries = [];
        foreach ($constants as $name => $entry) {
            $cid = 'constant.' . str_replace('_', '-', strtolower($name));
            $sourceRelative = $entry['source'];
            $source = $this->docEn->readFile($sourceRelative);
            $what = sprintf('constant %s (%s)', $name, $sourceRelative);
            $varlistentry = XmlText::firstMatch(
                '/<varlistentry xml:id="' . preg_quote($cid, '/') . '">(.*?)<\/varlistentry>/s',
                $source,
                sprintf('xml:id="%s" in %s', $cid, $sourceRelative),
            );
            $type = XmlText::firstMatch('/\(<type>([a-z]+)<\/type>\)/', $varlistentry, '(<type>) of ' . $what);
            $defaultPage = XmlText::firstMatch('/xml:id="([^"]+)"/', $source, 'first xml:id of ' . $sourceRelative);
            $page = $entry['page'] ?? $defaultPage;
            if (isset($entry['page']) && $entry['page'] === $defaultPage) {
                throw new SyncException(sprintf('%s: "page" equals the derived default "%s"; remove it', $what, $defaultPage));
            }

            $link = $output->linkEntity($cid, $page . '.php#' . $cid);
            $summary = XmlText::wrapText($entry['summary'], 5);
            $entries[] = rtrim(PageTemplate::render('constant', [
                'CONSTANT_ID' => $cid,
                'CONSTANT_NAME' => $name,
                'TYPE' => $type,
                'SUMMARY' => $summary,
                'LINK' => $link,
            ]), "\n");
        }

        $link = $output->linkEntity('reserved.constants');
        $this->addPage('language/predefined/constants.xml', null, 'constants', [
            'LINK' => $link,
            'CONSTANTS' => implode("\n", $entries),
        ], $output);
    }

    private function generateVersions(GeneratedFiles $output): void
    {
        $lines = [];
        foreach (array_keys($output->versionDirs) as $dir) {
            foreach (explode("\n", $this->docEn->readFile($dir . '/versions.xml')) as $line) {
                if (preg_match('/<function name="([^"]+)"/', $line, $m) && isset($output->versionNames[strtolower($m[1])])) {
                    $lines[strtolower($m[1])] = ' ' . trim($line);
                }
            }
        }

        $missing = array_diff_key($output->versionNames, $lines);
        if ($missing !== []) {
            throw new SyncException('no versions.xml entry in doc-en for: ' . implode(', ', array_keys($missing)));
        }

        ksort($lines, SORT_STRING);
        $this->addPage('language/predefined/versions.xml', null, 'versions', [
            'VERSIONS' => implode("\n", $lines),
        ], $output);
    }

    /** @param array<string, string> $values */
    private function addPage(string $file, ?string $sourceRelative, string $template, array $values, GeneratedFiles $output): void
    {
        $output->addFile($file, PageTemplate::render($template, ['BANNER' => XmlText::banner($sourceRelative)] + $values));
    }
}
