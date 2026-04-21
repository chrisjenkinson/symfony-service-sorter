<?php

declare(strict_types=1);

namespace App\Parser\Region;

use App\Parser\AmbiguousCommentException;

final class ServiceRegionDetector
{
    /**
     * @param list<LineType> $lineTypes
     * @return list<ServiceRegion>
     */
    public function detect(array $lineTypes): array
    {
        /** @var list<ServiceRegion> $regions */
        $regions = [];
        $currentRegion = null;
        $state = ServiceRegionState::Start;
        $commentOrigin = null;

        for ($i = 0; $i < count($lineTypes); $i++) {
            if ($state === ServiceRegionState::End) {
                break;
            }

            $type = $lineTypes[$i];
            $state = $this->transition($currentRegion, $regions, $state, $type, $i, $commentOrigin);
        }

        return $regions;
    }

    /**
     * @param list<ServiceRegion> &$regions
     */
    private function transition(
        ?ServiceRegion &$currentRegion,
        array &$regions,
        ServiceRegionState $state,
        LineType $type,
        int $index,
        ?ServiceRegionState &$commentOrigin,
    ): ServiceRegionState {
        return match ([$state, $type]) {
            // Start -> InServicesPreamble
            [ServiceRegionState::Start, LineType::ServicesHeader] => ServiceRegionState::InServicesPreamble,
            [ServiceRegionState::Start, LineType::Blank] => ServiceRegionState::Start,
            [ServiceRegionState::Start, LineType::Comment] => ServiceRegionState::Start,
            [ServiceRegionState::Start, LineType::Service] => ServiceRegionState::Start,
            [ServiceRegionState::Start, LineType::TopLevelSibling] => ServiceRegionState::Start,

            // InServicesPreamble -> InService
            [ServiceRegionState::InServicesPreamble, LineType::Service] => $this->addService($currentRegion, $regions, $index, ServiceRegionState::InService, $commentOrigin),
            [ServiceRegionState::InServicesPreamble, LineType::Blank] => ServiceRegionState::InServicesPreamble,
            [ServiceRegionState::InServicesPreamble, LineType::Comment] => $this->addComment($currentRegion, $regions, $index, ServiceRegionState::InComment, $commentOrigin, ServiceRegionState::InServicesPreamble),
            [ServiceRegionState::InServicesPreamble, LineType::ServicesHeader] => ServiceRegionState::InServicesPreamble,
            [ServiceRegionState::InServicesPreamble, LineType::TopLevelSibling] => ServiceRegionState::End,

            // InService -> InPostServiceGap
            [ServiceRegionState::InService, LineType::Service] => $this->addService($currentRegion, $regions, $index, ServiceRegionState::InService, $commentOrigin),
            [ServiceRegionState::InService, LineType::Blank] => ServiceRegionState::InPostServiceGap,
            [ServiceRegionState::InService, LineType::Comment] => $this->addComment($currentRegion, $regions, $index, ServiceRegionState::InComment, $commentOrigin, ServiceRegionState::InService),
            [ServiceRegionState::InService, LineType::ServicesHeader] => ServiceRegionState::InService,
            [ServiceRegionState::InService, LineType::TopLevelSibling] => ServiceRegionState::End,

            // InPostServiceGap -> InService
            [ServiceRegionState::InPostServiceGap, LineType::Service] => $this->addService($currentRegion, $regions, $index, ServiceRegionState::InService, $commentOrigin),
            [ServiceRegionState::InPostServiceGap, LineType::Blank] => ServiceRegionState::InPostServiceGap,
            [ServiceRegionState::InPostServiceGap, LineType::Comment] => $this->addComment($currentRegion, $regions, $index, ServiceRegionState::InComment, $commentOrigin, ServiceRegionState::InPostServiceGap),
            [ServiceRegionState::InPostServiceGap, LineType::ServicesHeader] => ServiceRegionState::InPostServiceGap,
            [ServiceRegionState::InPostServiceGap, LineType::TopLevelSibling] => ServiceRegionState::End,

            // InComment -> InComment
            [ServiceRegionState::InComment, LineType::Comment] => $this->appendComment($currentRegion, $regions, $index, $commentOrigin),
            [ServiceRegionState::InComment, LineType::Blank] => ServiceRegionState::InBoundaryGap,
            [ServiceRegionState::InComment, LineType::Service] => $this->resolveCommentBlock($currentRegion, $regions, $index, $commentOrigin),
            [ServiceRegionState::InComment, LineType::ServicesHeader] => ServiceRegionState::InComment,
            [ServiceRegionState::InComment, LineType::TopLevelSibling] => ServiceRegionState::End,

            // InBoundaryGap -> InService (NEW region - blank means boundary ended)
            [ServiceRegionState::InBoundaryGap, LineType::Service] => $this->startNewRegionAfterBoundary($currentRegion, $regions, $index, $commentOrigin),
            [ServiceRegionState::InBoundaryGap, LineType::Blank] => ServiceRegionState::InBoundaryGap,
            [ServiceRegionState::InBoundaryGap, LineType::Comment] => $this->addComment($currentRegion, $regions, $index, ServiceRegionState::InComment, $commentOrigin, ServiceRegionState::InBoundaryGap),
            [ServiceRegionState::InBoundaryGap, LineType::ServicesHeader] => ServiceRegionState::InBoundaryGap,
            [ServiceRegionState::InBoundaryGap, LineType::TopLevelSibling] => ServiceRegionState::End,

            default => $state,
        };
    }

    /**
     * @param list<ServiceRegion> &$regions
     * @param-out ServiceRegion $currentRegion
     * @param-out null $commentOrigin
     */
    private function addService(
        ?ServiceRegion &$currentRegion,
        array &$regions,
        int $index,
        ServiceRegionState $nextState,
        ?ServiceRegionState &$commentOrigin,
    ): ServiceRegionState {
        if ($currentRegion === null) {
            $currentRegion = new ServiceRegion();
            $regions[] = $currentRegion;
        }
        $currentRegion->serviceIndices[] = $index;
        $commentOrigin = null;
        return $nextState;
    }

    /**
     * @param list<ServiceRegion> &$regions
     * @param-out ServiceRegion $currentRegion
     * @param-out ServiceRegionState $commentOrigin
     */
    private function addComment(
        ?ServiceRegion &$currentRegion,
        array &$regions,
        int $index,
        ServiceRegionState $nextState,
        ?ServiceRegionState &$commentOrigin,
        ServiceRegionState $origin,
    ): ServiceRegionState {
        if ($currentRegion === null) {
            $currentRegion = new ServiceRegion();
            $regions[] = $currentRegion;
        }
        $currentRegion->boundaryCommentIndex = $index;
        $commentOrigin = $origin;
        return $nextState;
    }

    /**
     * @param list<ServiceRegion> &$regions
     * @param-out ServiceRegionState $commentOrigin
     */
    private function appendComment(
        ?ServiceRegion &$currentRegion,
        array &$regions,
        int $index,
        ?ServiceRegionState &$commentOrigin,
    ): ServiceRegionState {
        if ($commentOrigin === null) {
            throw new \LogicException('Comment origin must be known while in comment state.');
        }

        return $this->addComment(
            $currentRegion,
            $regions,
            $index,
            ServiceRegionState::InComment,
            $commentOrigin,
            $commentOrigin,
        );
    }

    /**
     * @param list<ServiceRegion> &$regions
     */
    private function resolveCommentBlock(
        ?ServiceRegion &$currentRegion,
        array &$regions,
        int $index,
        ?ServiceRegionState &$commentOrigin,
    ): ServiceRegionState {
        if ($commentOrigin === ServiceRegionState::InService) {
            $prevServiceIndex = null;
            if ($currentRegion !== null && $currentRegion->serviceIndices !== []) {
                $prevServiceIndex = $currentRegion->serviceIndices[array_key_last($currentRegion->serviceIndices)];
            }

            throw new AmbiguousCommentException(
                $prevServiceIndex !== null ? (string) $prevServiceIndex : '',
                (string) $index,
            );
        }

        return $this->addService($currentRegion, $regions, $index, ServiceRegionState::InService, $commentOrigin);
    }

    /**
     * @param list<ServiceRegion> &$regions
     * @param-out ServiceRegion $currentRegion
     * @param-out null $commentOrigin
     */
    private function startNewRegionAfterBoundary(
        ?ServiceRegion &$currentRegion,
        array &$regions,
        int $index,
        ?ServiceRegionState &$commentOrigin,
    ): ServiceRegionState {
        $boundaryCommentIndex = $currentRegion?->boundaryCommentIndex;
        if ($currentRegion !== null) {
            $currentRegion->boundaryCommentIndex = null;
        }

        $currentRegion = new ServiceRegion();
        $currentRegion->boundaryCommentIndex = $boundaryCommentIndex;
        $currentRegion->startingBoundaryCommentIndex = $boundaryCommentIndex;
        $currentRegion->startsAfterBoundary = true;
        $currentRegion->serviceIndices[] = $index;
        $regions[] = $currentRegion;
        $commentOrigin = null;

        return ServiceRegionState::InService;
    }
}
