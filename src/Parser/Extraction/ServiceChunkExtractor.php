<?php

declare(strict_types=1);

namespace App\Parser\Extraction;

use App\Parser\ServiceChunk;

final class ServiceChunkExtractor
{
    /**
     * @param list<string> $lines
     * @return array{
     *   chunks: list<ServiceChunk>,
     *   remainder: list<string>,
     *   descriptions: list<ChunkDescription>
     * }
     */
    public function extract(array $lines, string $blockIndent): array
    {
        [$chunks, $remainder] = $this->extractChunks($lines, $blockIndent);

        return [
            'chunks' => $chunks,
            'remainder' => $remainder,
            'descriptions' => $this->describeChunks($chunks, $blockIndent),
        ];
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
     * @return list<ChunkDescription>
     */
    private function describeChunks(array $chunks, string $blockIndent): array
    {
        $descriptions = [];
        $offset = 0;

        foreach ($chunks as $chunk) {
            $leadingCommentLineIndices = [];
            $serviceKeyByDetectorLine = [];
            $keyLineIndex = $offset;

            foreach ($chunk->lines as $localIndex => $line) {
                $trimmed = ltrim($line, " \t");
                $lineIndent = substr($line, 0, strlen($line) - strlen($trimmed));
                $absoluteIndex = $offset + $localIndex;

                if (rtrim($line) === '') {
                    continue;
                }

                if (str_starts_with($trimmed, '#') && ($lineIndent === $blockIndent || $lineIndent === '')) {
                    $leadingCommentLineIndices[] = $absoluteIndex;
                    continue;
                }

                $keyLineIndex = $absoluteIndex;
                $serviceKeyByDetectorLine[$absoluteIndex] = $chunk->key;
            }

            if ($serviceKeyByDetectorLine === []) {
                $serviceKeyByDetectorLine[$keyLineIndex] = $chunk->key;
            }

            $firstLeadingComment = null;
            $leadingComments = $this->extractLeadingComments($chunk->lines);
            if ($leadingComments !== []) {
                $firstLeadingComment = $leadingComments[0];
            }

            $descriptions[] = new ChunkDescription(
                chunk: $chunk,
                keyLineIndex: $keyLineIndex,
                firstLeadingComment: $firstLeadingComment,
                leadingCommentLineIndices: $leadingCommentLineIndices,
                serviceKeyByDetectorLine: $serviceKeyByDetectorLine,
                blankLinesBefore: $firstLeadingComment !== null
                    ? $this->countBlankLinesBeforeComment($chunk->lines)
                    : 0,
                blankLinesAfter: $firstLeadingComment !== null
                    ? $this->countBlankLinesAfterCommentBlock($chunk->lines)
                    : 0,
            );

            $offset += count($chunk->lines);
        }

        return $descriptions;
    }

    /**
     * @param list<string> $lines
     * @return list<string>
     */
    private function extractLeadingComments(array $lines): array
    {
        $comments = [];

        foreach ($lines as $line) {
            $trimmed = ltrim($line, " \t");
            if (str_starts_with($trimmed, '#')) {
                $comments[] = rtrim($line);
                continue;
            }

            if (rtrim($line) !== '') {
                break;
            }
        }

        return $comments;
    }

    /**
     * @param list<string> $lines
     */
    private function countBlankLinesBeforeComment(array $lines): int
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
    private function countBlankLinesAfterCommentBlock(array $lines): int
    {
        $blankCount = 0;
        $inLeadingCommentBlock = false;

        foreach ($lines as $line) {
            $trimmed = ltrim($line, " \t");

            if (!$inLeadingCommentBlock) {
                if (str_starts_with($trimmed, '#')) {
                    $inLeadingCommentBlock = true;
                }
                continue;
            }

            if (str_starts_with($trimmed, '#')) {
                continue;
            }

            if ($trimmed === '' || $trimmed === "\n") {
                $blankCount++;
            } else {
                break;
            }
        }

        return $blankCount;
    }
}
