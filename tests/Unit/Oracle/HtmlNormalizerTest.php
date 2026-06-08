<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Unit\Oracle;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shikiphp\Tests\Oracle\HtmlNormalizer;

final class HtmlNormalizerTest extends TestCase
{
    #[Test]
    public function whitespace_only_span_content_is_preserved(): void
    {
        $html = '<span style="color:#000"> </span><span style="color:#fff">x</span>';
        $lines = HtmlNormalizer::lines($html);

        self::assertSame([
            '<span style="color:#000"> </span>',
            '<span style="color:#fff">x</span>',
        ], $lines);
    }

    #[Test]
    public function adjacent_tags_split_onto_their_own_lines(): void
    {
        $html = '<pre><code><span class="line"></span></code></pre>';
        $lines = HtmlNormalizer::lines($html);

        self::assertSame([
            '<pre>',
            '<code>',
            '<span class="line">',
            '</span>',
            '</code>',
            '</pre>',
        ], $lines);
    }

    #[Test]
    public function indented_content_inside_a_span_is_not_collapsed(): void
    {
        $html = '<span class="line"><span style="color:#000">  id</span></span>';
        $lines = HtmlNormalizer::lines($html);

        self::assertSame([
            '<span class="line">',
            '<span style="color:#000">  id</span>',
            '</span>',
        ], $lines);
    }
}
