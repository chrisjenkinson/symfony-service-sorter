<?php

declare(strict_types=1);

namespace App\Tests\Unit\Sorter;

use App\Sorter\ServiceKeyNormalizer;
use PHPUnit\Framework\TestCase;

final class ServiceKeyNormalizerTest extends TestCase
{
    private ServiceKeyNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new ServiceKeyNormalizer();
    }

    public function testReplacesBackslashWithDot(): void
    {
        self::assertSame('app.foo.bar', $this->normalizer->normalize('App\\Foo\\Bar'));
    }

    public function testLowercasesKey(): void
    {
        self::assertSame('app.foo', $this->normalizer->normalize('App\\Foo'));
    }

    public function testConvertsPascalCaseToSnakeCase(): void
    {
        self::assertSame('app.read_model', $this->normalizer->normalize('App\\ReadModel'));
    }

    public function testConvertsConsecutiveUppercaseToSnakeCase(): void
    {
        self::assertSame('app.xml_parser', $this->normalizer->normalize('App\\XMLParser'));
    }

    public function testConvertsHttpPrefix(): void
    {
        self::assertSame('app.http_client', $this->normalizer->normalize('App\\HTTPClient'));
    }

    public function testHandlesAlreadyDotSeparatedKey(): void
    {
        self::assertSame('app.read_model', $this->normalizer->normalize('app.read_model'));
    }

    public function testHandlesUnderscorePrefixedKey(): void
    {
        self::assertSame('_defaults', $this->normalizer->normalize('_defaults'));
        self::assertSame('_instanceof', $this->normalizer->normalize('_instanceof'));
    }

    public function testHandlesSimpleKey(): void
    {
        self::assertSame('app.foo', $this->normalizer->normalize('App\\Foo'));
    }

    public function testSingleWordKey(): void
    {
        self::assertSame('app', $this->normalizer->normalize('App'));
    }

    public function testTrailingUppercaseSequence(): void
    {
        self::assertSame('app.http_request', $this->normalizer->normalize('App\\HTTPRequest'));
    }
}
