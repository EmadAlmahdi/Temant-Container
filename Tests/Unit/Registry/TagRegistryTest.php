<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Unit\Registry;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Temant\Container\Registry\TagRegistry;

final class TagRegistryTest extends TestCase
{
    private TagRegistry $tags;

    protected function setUp(): void
    {
        $this->tags = new TagRegistry();
    }

    #[Test]
    public function groupsIdsUnderTagsInInsertionOrder(): void
    {
        $this->tags->add('a', 'group');
        $this->tags->add('b', 'group');

        self::assertSame(['a', 'b'], $this->tags->idsFor('group'));
        self::assertSame([], $this->tags->idsFor('missing'));
    }

    #[Test]
    public function deduplicatesRepeatedIds(): void
    {
        $this->tags->add('a', 'group');
        $this->tags->add('a', 'group');

        self::assertSame(['a'], $this->tags->idsFor('group'));
    }

    #[Test]
    public function reportsEveryTagForAnId(): void
    {
        $this->tags->add('a', 'one');
        $this->tags->add('a', 'two');
        $this->tags->add('b', 'one');

        self::assertEqualsCanonicalizing(['one', 'two'], $this->tags->tagsFor('a'));
    }
}
