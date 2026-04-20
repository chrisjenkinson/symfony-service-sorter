<?php

declare(strict_types=1);

namespace App\Parser;

use App\Parser\Extraction\ServiceChunkExtractor;
use App\Parser\Extraction\ServicesBlockExtractor;
use App\Parser\Region\ServiceRegionAnalyzer;

final class YamlServiceParser
{
    public function __construct(
        private readonly ServicesBlockExtractor $servicesBlockExtractor,
        private readonly ServiceChunkExtractor $serviceChunkExtractor,
        private readonly ServiceRegionAnalyzer $serviceRegionAnalyzer,
    ) {
    }

    public function parse(string $content): ParsedFile
    {
        $servicesBlock = $this->servicesBlockExtractor->extract($content);

        if ($servicesBlock['servicesHeader'] === '') {
            return new ParsedFile(
                preamble: $servicesBlock['preamble'],
                servicesHeader: '',
                chunks: [],
                remainder: [],
            );
        }

        if ($servicesBlock['blockIndent'] === null) {
            return new ParsedFile(
                preamble: $servicesBlock['preamble'],
                servicesHeader: $servicesBlock['servicesHeader'],
                chunks: [],
                remainder: $servicesBlock['emptyBlockRemainder'],
            );
        }

        $chunkExtraction = $this->serviceChunkExtractor->extract(
            $servicesBlock['blockLines'],
            $servicesBlock['blockIndent'],
        );

        $analysis = $this->serviceRegionAnalyzer->analyze(
            $servicesBlock['servicesHeader'],
            $servicesBlock['blockLines'],
            $servicesBlock['blockIndent'],
            $chunkExtraction['chunks'],
            $chunkExtraction['descriptions'],
        );

        return new ParsedFile(
            preamble: $servicesBlock['preamble'],
            servicesHeader: $servicesBlock['servicesHeader'],
            chunks: $chunkExtraction['chunks'],
            remainder: $chunkExtraction['remainder'],
            classifiedComments: $analysis['classifiedComments'],
            groups: $analysis['groups'],
        );
    }
}
