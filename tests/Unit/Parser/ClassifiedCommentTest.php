<?php

declare(strict_types=1);

namespace App\Tests\Unit\Parser;

use App\Parser\ClassifiedComment;
use App\Parser\CommentType;
use PHPUnit\Framework\TestCase;

final class ClassifiedCommentTest extends TestCase
{
    public function testClassifiedCommentCanBeCreated(): void
    {
        $comment = new ClassifiedComment(
            CommentType::Boundary,
            '    # boundary comment',
            'App\Foo',
            'App\Bar',
        );
        self::assertSame(CommentType::Boundary, $comment->type);
        self::assertSame('App\Foo', $comment->prevServiceKey);
        self::assertSame('App\Bar', $comment->nextServiceKey);
        self::assertSame(0, $comment->blankLinesBefore);
        self::assertSame(0, $comment->blankLinesAfter);
    }

    public function testClassifiedCommentCanBeCreatedWithoutServiceKeys(): void
    {
        $comment = new ClassifiedComment(
            CommentType::Boundary,
            '    # boundary comment',
            null,
            null,
        );
        self::assertSame(CommentType::Boundary, $comment->type);
        self::assertNull($comment->prevServiceKey);
        self::assertNull($comment->nextServiceKey);
        self::assertSame(0, $comment->blankLinesBefore);
        self::assertSame(0, $comment->blankLinesAfter);
    }

    public function testClassifiedCommentStoresExplicitBlankLineCounts(): void
    {
        $comment = new ClassifiedComment(
            CommentType::ImmediatelyBefore,
            '    # note',
            'App\Alpha',
            'App\Bravo',
            2,
            1,
        );

        self::assertSame(2, $comment->blankLinesBefore);
        self::assertSame(1, $comment->blankLinesAfter);
    }
}
