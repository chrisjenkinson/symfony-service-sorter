<?php

declare(strict_types=1);

namespace App\Parser\Region;

use App\Parser\AmbiguousCommentException;
use App\Parser\ClassifiedComment;
use App\Parser\CommentType;
use App\Parser\Extraction\ChunkDescription;
use App\Parser\ServiceChunk;
use App\Parser\ServiceGroup;

final class ServiceRegionAnalyzer
{
    public function __construct(
        private readonly ServiceBlockLineClassifier $lineClassifier,
        private readonly ServiceRegionDetector $regionDetector,
    ) {
    }

    /**
     * @param list<string> $blockLines
     * @param list<ServiceChunk> $chunks
     * @param list<ChunkDescription> $descriptions
     * @return array{classifiedComments: list<ClassifiedComment>, groups: list<ServiceGroup>}
     */
    public function analyze(
        string $servicesHeader,
        array $blockLines,
        string $blockIndent,
        array $chunks,
        array $descriptions,
    ): array {
        $regions = $this->detectServiceRegions($servicesHeader, $blockLines, $blockIndent, $descriptions);
        $classifiedComments = $this->classifyComments($regions, $descriptions);

        return [
            'classifiedComments' => $classifiedComments,
            'groups' => $this->buildGroups($chunks, $classifiedComments),
        ];
    }

    /**
     * @param list<ServiceRegion> $regions
     * @param list<ChunkDescription> $descriptions
     * @return list<ClassifiedComment>
     */
    private function classifyComments(array $regions, array $descriptions): array
    {
        $classifiedComments = [];
        $regionByServiceIndex = $this->indexRegionsByServiceLine($regions);

        foreach ($descriptions as $i => $description) {
            $comment = $description->firstLeadingComment;
            if ($comment === null) {
                continue;
            }

            $region = $regionByServiceIndex[$description->keyLineIndex] ?? null;
            $isBoundary = $region !== null
                && $region->startsAfterBoundary
                && $region->startingBoundaryCommentIndex !== null
                && in_array($region->startingBoundaryCommentIndex, $description->leadingCommentLineIndices, true);

            $classifiedComments[] = new ClassifiedComment(
                type: $isBoundary
                    ? CommentType::Boundary
                    : ($i === 0 ? CommentType::ImmediatelyAfter : CommentType::ImmediatelyBefore),
                line: $comment,
                prevServiceKey: $i > 0 ? $descriptions[$i - 1]->chunk->key : null,
                nextServiceKey: $description->chunk->key,
                blankLinesBefore: $description->blankLinesBefore,
                blankLinesAfter: $description->blankLinesAfter,
            );
        }

        return $classifiedComments;
    }

    /**
     * @param list<string> $blockLines
     * @return list<LineType>
     */
    private function classifyBlockLines(array $blockLines, string $blockIndent): array
    {
        return array_map(
            function (string $line) use ($blockIndent): LineType {
                $classified = $this->lineClassifier->classify(rtrim($line, "\n"));
                if ($classified !== LineType::Comment) {
                    return $classified;
                }

                $trimmed = ltrim($line, " \t");
                $lineIndent = substr($line, 0, strlen($line) - strlen($trimmed));

                return ($lineIndent === $blockIndent || $lineIndent === '')
                    ? LineType::Comment
                    : LineType::Service;
            },
            $blockLines,
        );
    }

    /**
     * @param list<string> $blockLines
     * @param list<ChunkDescription> $descriptions
     * @return list<ServiceRegion>
     */
    private function detectServiceRegions(
        string $servicesHeader,
        array $blockLines,
        string $blockIndent,
        array $descriptions,
    ): array {
        $lineTypes = $this->classifyBlockLines([$servicesHeader, ...$blockLines], $blockIndent);
        $serviceKeysByDetectorIndex = $this->indexServiceKeysByDetectorLine($descriptions);

        try {
            $regions = $this->normalizeDetectedRegions($this->regionDetector->detect($lineTypes));
        } catch (AmbiguousCommentException $e) {
            throw new AmbiguousCommentException(
                $serviceKeysByDetectorIndex[(int) $e->prevServiceKey] ?? $e->prevServiceKey,
                $serviceKeysByDetectorIndex[(int) $e->nextServiceKey] ?? $e->nextServiceKey,
            );
        }

        return $regions;
    }

    /**
     * @param list<ChunkDescription> $descriptions
     * @return array<int, string>
     */
    private function indexServiceKeysByDetectorLine(array $descriptions): array
    {
        $indexed = [];

        foreach ($descriptions as $description) {
            foreach ($description->serviceKeyByDetectorLine as $lineIndex => $serviceKey) {
                $indexed[$lineIndex + 1] = $serviceKey;
            }
        }

        return $indexed;
    }

    /**
     * @param list<ServiceRegion> $regions
     * @return list<ServiceRegion>
     */
    private function normalizeDetectedRegions(array $regions): array
    {
        $normalized = [];

        foreach ($regions as $region) {
            $normalizedRegion = new ServiceRegion();
            $normalizedRegion->startsAfterBoundary = $region->startsAfterBoundary;
            $normalizedRegion->serviceIndices = array_values(array_map(
                fn (int $index): int => $index - 1,
                array_filter($region->serviceIndices, fn (int $index): bool => $index > 0),
            ));
            $normalizedRegion->boundaryCommentIndex = $region->boundaryCommentIndex !== null
                ? $region->boundaryCommentIndex - 1
                : null;
            $normalizedRegion->startingBoundaryCommentIndex = $region->startingBoundaryCommentIndex !== null
                ? $region->startingBoundaryCommentIndex - 1
                : null;

            $normalized[] = $normalizedRegion;
        }

        return $normalized;
    }

    /**
     * @param list<ServiceRegion> $regions
     * @return array<int, ServiceRegion>
     */
    private function indexRegionsByServiceLine(array $regions): array
    {
        $indexed = [];
        foreach ($regions as $region) {
            foreach ($region->serviceIndices as $serviceIndex) {
                $indexed[$serviceIndex] = $region;
            }
        }

        return $indexed;
    }

    /**
     * @param list<ServiceChunk> $chunks
     * @param list<ClassifiedComment> $classifiedComments
     * @return list<ServiceGroup>
     */
    private function buildGroups(array $chunks, array $classifiedComments): array
    {
        $boundaryMap = [];
        foreach ($classifiedComments as $classifiedComment) {
            if ($classifiedComment->type === CommentType::Boundary) {
                $boundaryMap[$classifiedComment->nextServiceKey] = $classifiedComment;
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
