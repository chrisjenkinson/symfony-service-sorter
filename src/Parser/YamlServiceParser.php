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

        $classifiedComments = $this->classifyComments($chunks);
        $groups = $this->buildGroups($chunks, $classifiedComments);

        return new ParsedFile(
            preamble: $preamble,
            servicesHeader: $servicesHeader,
            chunks: $chunks,
            remainder: $remainder,
            classifiedComments: $classifiedComments,
            groups: $groups,
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

        $blankLinesBeforeComment = 0;
        $blankLinesAfterComment = 0;

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
                $blankLinesBeforeComment = 0;
                foreach ($pendingLines as $pl) {
                    if (rtrim($pl) === '') {
                        $blankLinesBeforeComment++;
                    }
                }

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
                $blankLinesAfterComment = 0;
                foreach ($pendingLines as $pl) {
                    if (rtrim($pl) === '') {
                        $blankLinesAfterComment++;
                    }
                }

                if ($currentKey !== null) {
                    $currentLines = array_merge($currentLines, $pendingLines);
                    $pendingLines = [];
                    $chunks[] = new ServiceChunk($currentKey, $currentLines);
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

    /**
     * @param list<ServiceChunk> $chunks
     * @return list<ClassifiedComment>
     */
    private function classifyComments(array $chunks): array
    {
        $classifiedComments = [];
        $prevKey = null;
        $prevTrailingBlanks = 0;

        for ($i = 0; $i < count($chunks); $i++) {
            $chunk = $chunks[$i];
            $comment = $this->extractLeadingComment($chunk->lines);

            if ($comment === null) {
                $prevTrailingBlanks = $this->countTrailingBlankLines($chunk->lines);
                $prevKey = $chunk->key;
                continue;
            }

            $blankBefore = $this->countBlankLinesBeforeComment($chunk->lines, $comment);
            if ($blankBefore === 0 && $i > 0) {
                $blankBefore = $prevTrailingBlanks;
            }

            $blankAfter = $this->countBlankLinesAfterComment($chunk->lines, $comment);
            if ($i < count($chunks) - 1) {
                $nextChunk = $chunks[$i + 1];
                if ($this->extractLeadingComment($nextChunk->lines) === null) {
                    $blankAfter = $this->countTrailingBlankLines($chunk->lines);
                }
            }

            $classifiedComments[] = new ClassifiedComment(
                type: $this->classifyByBlankCounts($blankBefore, $blankAfter),
                line: $comment,
                prevServiceKey: $prevKey,
                nextServiceKey: $chunk->key,
                blankLinesBefore: $blankBefore,
                blankLinesAfter: $blankAfter,
            );

            if ($this->classifyByBlankCounts($blankBefore, $blankAfter) === CommentType::Ambiguous) {
                throw new AmbiguousCommentException($prevKey ?? '', $chunk->key);
            }

            $prevTrailingBlanks = $this->countTrailingBlankLines($chunk->lines);
            $prevKey = $chunk->key;
        }

        return $classifiedComments;
    }

    /**
     * @param list<string> $lines
     */
    private function countTrailingBlankLines(array $lines): int
    {
        $blankCount = 0;
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            if (rtrim($lines[$i]) === '') {
                $blankCount++;
            } else {
                break;
            }
        }
        return $blankCount;
    }

    /**
     * @param list<string> $lines
     */
    private function extractLeadingComment(array $lines): ?string
    {
        foreach ($lines as $line) {
            $trimmed = ltrim($line, " \t");
            if (str_starts_with($trimmed, '#')) {
                return rtrim($line);
            }
            if (rtrim($line) !== '') {
                break;
            }
        }
        return null;
    }

    /**
     * @param list<string> $lines
     */
    private function countBlankLinesBeforeComment(array $lines, string $comment): int
    {
        $blankCount = 0;
        foreach ($lines as $line) {
            $trimmed = ltrim($line, " \t");
            if (str_starts_with($trimmed, '#')) {
                break;
            }
            if (rtrim($line) === '') {
                $blankCount++;
            }
        }
        return $blankCount;
    }

    /**
     * @param list<string> $lines
     */
    private function countBlankLinesAfterComment(array $lines, string $comment): int
    {
        $blankCount = 0;
        $foundComment = false;

        foreach ($lines as $line) {
            if (!$foundComment) {
                if (rtrim($line) === rtrim($comment)) {
                    $foundComment = true;
                }
                continue;
            }

            $trimmed = ltrim($line, " \t");
            if ($trimmed === '' || $trimmed === "\n") {
                $blankCount++;
            } else {
                break;
            }
        }

        return $blankCount;
    }

    private function classifyByBlankCounts(int $blankBefore, int $blankAfter): CommentType
    {
        if ($blankBefore >= 1 && $blankAfter >= 1) {
            return CommentType::Boundary;
        }
        if ($blankBefore >= 1 && $blankAfter === 0) {
            return CommentType::ImmediatelyBefore;
        }
        if ($blankBefore === 0 && $blankAfter >= 1) {
            return CommentType::ImmediatelyAfter;
        }
        return CommentType::Ambiguous;
    }

    /**
     * @param list<ServiceChunk> $chunks
     * @param list<ClassifiedComment> $classifiedComments
     * @return list<ServiceGroup>
     */
    private function buildGroups(array $chunks, array $classifiedComments): array
    {
        $boundaryMap = [];
        foreach ($classifiedComments as $cc) {
            if ($cc->type === CommentType::Boundary) {
                $boundaryMap[$cc->nextServiceKey] = $cc;
            }
        }

        $groups = [];
        $currentGroupChunks = [];
        $currentBoundary = null;

        foreach ($chunks as $chunk) {
            if (isset($boundaryMap[$chunk->key])) {
                if ($currentGroupChunks !== []) {
                    $groups[] = new ServiceGroup($currentBoundary, $currentGroupChunks);
                }
                $currentBoundary = $boundaryMap[$chunk->key];
                $currentGroupChunks = [];
            }
            $currentGroupChunks[] = $chunk;
        }

        if ($currentGroupChunks !== []) {
            $groups[] = new ServiceGroup($currentBoundary, $currentGroupChunks);
        }

        usort($groups, function (ServiceGroup $a, ServiceGroup $b): int {
            $aFirstKey = isset($a->chunks[0]) ? $a->chunks[0]->key : '';
            $bFirstKey = isset($b->chunks[0]) ? $b->chunks[0]->key : '';
            return $aFirstKey <=> $bFirstKey;
        });

        return $groups;
    }
}
