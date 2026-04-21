<?php

declare(strict_types=1);

namespace App\Parser\Extraction;

final class ServicesBlockExtractor
{
    /**
     * @return array{
     *   preamble: list<string>,
     *   servicesHeader: string,
     *   blockLines: list<string>,
     *   blockIndent: ?string,
     *   emptyBlockRemainder: list<string>
     * }
     */
    public function extract(string $content): array
    {
        $lines = $this->splitLines($content);
        $servicesLineIndex = $this->findServicesLine($lines);

        if ($servicesLineIndex === null) {
            return [
                'preamble' => $lines,
                'servicesHeader' => '',
                'blockLines' => [],
                'blockIndent' => null,
                'emptyBlockRemainder' => [],
            ];
        }

        $blockLines = array_slice($lines, $servicesLineIndex + 1);
        $blockIndent = $this->detectBlockIndent($blockLines);

        return [
            'preamble' => array_slice($lines, 0, $servicesLineIndex),
            'servicesHeader' => $lines[$servicesLineIndex],
            'blockLines' => $blockLines,
            'blockIndent' => $blockIndent,
            'emptyBlockRemainder' => $blockIndent === null ? $blockLines : [],
        ];
    }

    /**
     * @return list<string>
     */
    private function splitLines(string $content): array
    {
        if ($content === '') {
            return [];
        }

        $lines = [];
        $remaining = $content;
        while ($remaining !== '') {
            $pos = strpos($remaining, "\n");
            if ($pos === false) {
                $lines[] = $remaining . "\n";
                break;
            }

            $lines[] = substr($remaining, 0, $pos + 1);
            $remaining = substr($remaining, $pos + 1);
        }

        return $lines;
    }

    /**
     * @param list<string> $lines
     */
    private function findServicesLine(array $lines): ?int
    {
        foreach ($lines as $i => $line) {
            if (preg_match('/^services:\s*$/', rtrim($line))) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param list<string> $lines
     */
    private function detectBlockIndent(array $lines): ?string
    {
        foreach ($lines as $line) {
            $trimmed = ltrim($line, " \t");
            if ($trimmed === '' || $trimmed === "\n" || str_starts_with($trimmed, '#')) {
                continue;
            }

            $indent = substr($line, 0, strlen($line) - strlen($trimmed));

            if ($indent === '') {
                return null;
            }

            return $indent;
        }

        return null;
    }
}
