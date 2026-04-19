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
    }
}