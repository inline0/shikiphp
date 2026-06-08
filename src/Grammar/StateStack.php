<?php

declare(strict_types=1);

namespace Shikiphp\Grammar;

use Shikiphp\Grammar\Rule\Rule;

/**
 * One frame of the tokenizer's rule stack (vscode-textmate's `StackElement`):
 * the active rule, the resolved dynamic end/while source, and the scope lists in
 * effect. Immutable — push/pop return new instances.
 */
final readonly class StateStack
{
    /**
     * @param int $depth 1-based nesting depth (the root frame is depth 1)
     * @param int $anchorPosition the `\G` anchor offset for this frame, or -1
     * @param ?string $endRule the resolved end/while source (back-references applied), or null
     * @param int $enterPos the line position at which this frame was pushed, or -1
     */
    public function __construct(
        public ?StateStack $parent,
        public int $ruleId,
        public int $depth,
        public bool $beginRuleCapturedEOL,
        public int $anchorPosition,
        public ?string $endRule,
        public ?ScopeStack $nameScopesList,
        public ?ScopeStack $contentNameScopesList,
        public int $enterPos = -1,
    ) {
    }

    public static function root(int $ruleId, ?ScopeStack $nameScopesList): self
    {
        return new self(
            parent: null,
            ruleId: $ruleId,
            depth: 1,
            beginRuleCapturedEOL: false,
            anchorPosition: -1,
            endRule: null,
            nameScopesList: $nameScopesList,
            contentNameScopesList: $nameScopesList,
            enterPos: -1,
        );
    }

    public function push(
        int $ruleId,
        int $anchorPosition,
        bool $beginRuleCapturedEOL,
        ?string $endRule,
        ?ScopeStack $nameScopesList,
        ?ScopeStack $contentNameScopesList,
        int $enterPos = -1,
    ): self {
        return new self(
            parent: $this,
            ruleId: $ruleId,
            depth: $this->depth + 1,
            beginRuleCapturedEOL: $beginRuleCapturedEOL,
            anchorPosition: $anchorPosition,
            endRule: $endRule,
            nameScopesList: $nameScopesList,
            contentNameScopesList: $contentNameScopesList,
            enterPos: $enterPos,
        );
    }

    public function pop(): ?self
    {
        return $this->parent;
    }

    public function safePop(): self
    {
        return $this->parent ?? $this;
    }

    public function safePopForFailedMatch(): self
    {
        return $this->parent ?? $this;
    }

    public function getRule(Grammar $grammar): Rule
    {
        return $grammar->getRule($this->ruleId);
    }

    public function getEnterPos(): int
    {
        return $this->enterPos;
    }

    public function hasSameRuleAs(self $other): bool
    {
        $node = $this;
        while ($node !== null && $node->enterPos === $other->enterPos) {
            if ($node->ruleId === $other->ruleId) {
                return true;
            }
            $node = $node->parent;
        }

        return false;
    }

    public function withContentNameScopesList(?ScopeStack $contentNameScopesList): self
    {
        if ($this->contentNameScopesList === $contentNameScopesList) {
            return $this;
        }
        assert($this->parent !== null);

        return $this->parent->push(
            $this->ruleId,
            $this->anchorPosition,
            $this->beginRuleCapturedEOL,
            $this->endRule,
            $this->nameScopesList,
            $contentNameScopesList,
            $this->enterPos,
        );
    }

    public function withEndRule(?string $endRule): self
    {
        if ($this->endRule === $endRule) {
            return $this;
        }

        return new self(
            parent: $this->parent,
            ruleId: $this->ruleId,
            depth: $this->depth,
            beginRuleCapturedEOL: $this->beginRuleCapturedEOL,
            anchorPosition: $this->anchorPosition,
            endRule: $endRule,
            nameScopesList: $this->nameScopesList,
            contentNameScopesList: $this->contentNameScopesList,
            enterPos: $this->enterPos,
        );
    }

    public function equals(?self $other): bool
    {
        $a = $this;
        $b = $other;
        while (true) {
            if ($a === $b) {
                return true;
            }
            if ($a === null || $b === null) {
                return false;
            }
            if (
                $a->depth !== $b->depth
                || $a->ruleId !== $b->ruleId
                || $a->endRule !== $b->endRule
            ) {
                return false;
            }
            if (!self::scopeListsEqual($a->nameScopesList, $b->nameScopesList)) {
                return false;
            }
            if (!self::scopeListsEqual($a->contentNameScopesList, $b->contentNameScopesList)) {
                return false;
            }
            $a = $a->parent;
            $b = $b->parent;
        }
    }

    private static function scopeListsEqual(?ScopeStack $a, ?ScopeStack $b): bool
    {
        if ($a === null) {
            return $b === null;
        }

        return $a->equals($b);
    }
}
