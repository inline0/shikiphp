<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Unit\Transformer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shikiphp\Highlighter;
use Shikiphp\Transformer\Notation\NotationHighlight;

/**
 * Locks in per-language comment-syntax detection for the notation base: the
 * notation marker is stripped and the line picks up the highlight class.
 */
final class NotationCommentSyntaxTest extends TestCase
{
    private static Highlighter $highlighter;

    public static function setUpBeforeClass(): void
    {
        self::$highlighter = Highlighter::createBundled();
    }

    private function render(string $code, string $lang): string
    {
        return self::$highlighter->codeToHtml($code, [
            'lang' => $lang,
            'theme' => 'github-dark',
            'transformers' => [new NotationHighlight()],
        ]);
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function comments(): array
    {
        return [
            'js line'      => ['javascript', "const a = 1 // [!code highlight]\nconst b = 2\n"],
            'python hash'  => ['python', "a = 1  # [!code highlight]\nb = 2\n"],
            'css block'    => ['css', ".a { color: red } /* [!code highlight] */\n.b { color: blue }\n"],
            'html comment' => ['html', "<div></div> <!-- [!code highlight] -->\n<span></span>\n"],
            'sql dash'     => ['sql', "SELECT 1; -- [!code highlight]\nSELECT 2;\n"],
            'shell hash'   => ['bash', "echo hi # [!code highlight]\necho bye\n"],
            'lua dash'     => ['lua', "local a = 1 -- [!code highlight]\nlocal b = 2\n"],
        ];
    }

    #[Test]
    #[DataProvider('comments')]
    public function notation_is_detected_and_stripped(string $lang, string $code): void
    {
        $html = $this->render($code, $lang);

        $this->assertStringNotContainsString('[!code', $html);
        $this->assertStringContainsString('class="line highlighted"', $html);
        $this->assertStringContainsString('has-highlighted', $html);
    }
}
