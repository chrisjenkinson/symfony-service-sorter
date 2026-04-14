<?php

declare(strict_types=1);

namespace App\Parser;

final class YamlServiceParser
{
    public function parse(string $content): ParsedFile
    {
        $lines = $this->splitLines($content);

        $servicesLineIndex = $this->findServicesLine($lines);

        if ($servicesLineIndex === null) {
            return new ParsedFile(
                preamble: $lines,
                servicesHeader: '',
                chunks: [],
                remainder: [],
            );
        }

        $preamble = array_slice($lines, 0, $servicesLineIndex);
        $servicesHeader = $lines[$servicesLineIndex];

        $blockLines = array_slice($lines, $servicesLineIndex + 1);
        $blockIndent = $this->detectBlockIndent($blockLines);

        if ($blockIndent === null) {
            [, $remainder] = $this->splitEmptyBlock($blockLines);
            return new ParsedFile(
                preamble: $preamble,
                servicesHeader: $servicesHeader,
                chunks: [],
                remainder: $remainder,
            );
        }

        [$chunks, $remainder] = $this->extractChunks($blockLines, $blockIndent);

        return new ParsedFile(
            preamble: $preamble,
            servicesHeader: $servicesHeader,
            chunks: $chunks,
            remainder: $remainder,
        );
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
                $lines[] = $remaining;
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
            return substr($line, 0, strlen($line) - strlen($trimmed));
        }
        return null;
    }

    /**
     * @param list<string> $lines
     * @return array{list<string>, list<string>}
     */
    private function splitEmptyBlock(array $lines): array
    {
        foreach ($lines as $i => $line) {
            $trimmed = ltrim($line, " \t");
            if ($trimmed !== '' && $trimmed !== "\n" && !str_starts_with($trimmed, '#')) {
                $indent = substr($line, 0, strlen($line) - strlen($trimmed));
                if ($indent === '') {
                    return [array_slice($lines, 0, $i), array_slice($lines, $i)];
                }
            }
        }
        return [$lines, []];
    }

    /**
     * @param list<string> $lines
     * @return array{list<ServiceChunk>, list<string>}
     */
    private function extractChunks(array $lines, string $blockIndent): array
    {
        $chunks = [];
        $remainder = [];
        $pendingLines = [];
        $currentKey = null;
        $currentLines = [];
        $inRemainder = false;

        foreach ($lines as $line) {
            if ($inRemainder) {
                $remainder[] = $line;
                continue;
            }

            $trimmed = ltrim($line, " \t");
            $lineIndent = substr($line, 0, strlen($line) - strlen($trimmed));
            $rtrimmedLine = rtrim($line);

            if ($rtrimmedLine === '') {
                $pendingLines[] = $line;
                continue;
            }

            if (str_starts_with($trimmed, '#')) {
                if ($lineIndent === $blockIndent || $lineIndent === '') {
                    if ($currentKey !== null) {
                        $currentLines = array_merge($currentLines, $pendingLines);
                        $pendingLines = [];
                        $chunks[] = new ServiceChunk($currentKey, $currentLines);
                        $currentKey = null;
                        $currentLines = [];
                    }
                    $pendingLines[] = $line;
                } else {
                    $currentLines = array_merge($currentLines, $pendingLines);
                    $pendingLines = [];
                    $currentLines[] = $line;
                }
                continue;
            }

            if ($lineIndent === '') {
                if ($currentKey !== null) {
                    $chunks[] = new ServiceChunk($currentKey, $currentLines);
                    $currentKey = null;
                    $currentLines = [];
                }
                $inRemainder = true;
                $remainder = array_merge($pendingLines, [$line]);
                $pendingLines = [];
                continue;
            }

            if ($lineIndent === $blockIndent) {
                if ($currentKey !== null) {
                    $chunks[] = new ServiceChunk($currentKey, $currentLines);
                    $currentKey = null;
                    $currentLines = [];
                }
                $currentKey = rtrim(rtrim($trimmed), ':');
                $currentLines = array_merge($pendingLines, [$line]);
                $pendingLines = [];
                continue;
            }

            $currentLines = array_merge($currentLines, $pendingLines);
            $pendingLines = [];
            $currentLines[] = $line;
        }

        if ($currentKey !== null) {
            $currentLines = array_merge($currentLines, $pendingLines);
            $chunks[] = new ServiceChunk($currentKey, $currentLines);
        } else {
            $remainder = array_merge($pendingLines, $remainder);
        }

        return [$chunks, $remainder];
    }
}
