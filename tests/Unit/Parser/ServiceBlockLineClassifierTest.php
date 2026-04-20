<?php

declare(strict_types=1);

namespace App\Tests\Unit\Parser;

use App\Parser\Region\LineType;
use App\Parser\Region\ServiceBlockLineClassifier;
use PHPUnit\Framework\TestCase;

final class ServiceBlockLineClassifierTest extends TestCase
{
    private ServiceBlockLineClassifier $classifier;

    protected function setUp(): void
    {
        $this->classifier = new ServiceBlockLineClassifier();
    }

    public function testClassifiesHeaderLine(): void
    {
        $result = $this->classifier->classify('services:');
        self::assertSame(LineType::ServicesHeader, $result);
    }

    public function testClassifiesBlankLine(): void
    {
        $result = $this->classifier->classify('');
        self::assertSame(LineType::Blank, $result);
    }

    public function testClassifiesCommentLine(): void
    {
        $result = $this->classifier->classify('    # this is a comment');
        self::assertSame(LineType::Comment, $result);
    }

    public function testClassifiesServiceLine(): void
    {
        $result = $this->classifier->classify('    App\Foo:');
        self::assertSame(LineType::Service, $result);
    }

    public function testClassifiesTopLevelSiblingBlockAsBlockExit(): void
    {
        $result = $this->classifier->classify('parameters:');
        self::assertSame(LineType::TopLevelSibling, $result);
    }

    public function testClassifiesValueAsService(): void
    {
        $result = $this->classifier->classify('        autowire: true');
        self::assertSame(LineType::Service, $result);
    }
}
