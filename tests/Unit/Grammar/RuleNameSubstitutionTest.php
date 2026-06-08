<?php

declare(strict_types=1);

namespace Shikiphp\Tests\Unit\Grammar;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shikiphp\Grammar\Rule\CaptureRule;
use Shikiphp\Oniguruma\OnigCaptureIndex;

final class RuleNameSubstitutionTest extends TestCase
{
    /** @return list<OnigCaptureIndex> */
    private function captures(): array
    {
        return [
            new OnigCaptureIndex(0, 5),
            new OnigCaptureIndex(0, 3),
            new OnigCaptureIndex(3, 5),
        ];
    }

    #[Test]
    public function static_name_is_returned_unchanged(): void
    {
        $rule = new CaptureRule(1, 'keyword.control.css', null, null);

        self::assertSame('keyword.control.css', $rule->nameScope('0.5rem', $this->captures()));
    }

    #[Test]
    public function downcase_backreference_is_substituted(): void
    {
        $rule = new CaptureRule(1, 'keyword.other.unit.${2:/downcase}.css', null, null);

        self::assertSame('keyword.other.unit.re.css', $rule->nameScope('0.5REM', $this->captures()));
    }

    #[Test]
    public function upcase_backreference_is_substituted(): void
    {
        $rule = new CaptureRule(1, 'meta.${2:/upcase}', null, null);

        self::assertSame('meta.RE', $rule->nameScope('0.5rem', $this->captures()));
    }

    #[Test]
    public function bare_numeric_backreference_is_substituted(): void
    {
        $rule = new CaptureRule(1, 'tag.$2', null, null);

        self::assertSame('tag.re', $rule->nameScope('0.5rem', $this->captures()));
    }

    #[Test]
    public function content_name_backreference_is_substituted(): void
    {
        $rule = new CaptureRule(1, null, 'content.${2:/downcase}', null);

        self::assertSame('content.re', $rule->contentNameScope('0.5REM', $this->captures()));
    }

    #[Test]
    public function missing_line_text_leaves_template_unresolved(): void
    {
        $rule = new CaptureRule(1, 'keyword.other.unit.${2:/downcase}.css', null, null);

        self::assertSame('keyword.other.unit.${2:/downcase}.css', $rule->nameScope());
    }
}
