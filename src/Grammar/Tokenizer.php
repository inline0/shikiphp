<?php

declare(strict_types=1);

namespace Shikiphp\Grammar;

use Shikiphp\Grammar\Rule\BeginEndRule;
use Shikiphp\Grammar\Rule\BeginWhileRule;
use Shikiphp\Grammar\Rule\CaptureRule;
use Shikiphp\Grammar\Rule\CompiledRule;
use Shikiphp\Grammar\Rule\MatchRule;
use Shikiphp\Grammar\Rule\Rule;
use Shikiphp\Oniguruma\OnigCaptureIndex;
use Shikiphp\Oniguruma\OnigString;

/**
 * Port of vscode-textmate's `_tokenizeString`: drives the rule stack one line at
 * a time, compiling an OnigScanner per active state (rule patterns + end/while +
 * injections), emitting gap-free tokens, applying captures, and pushing/popping
 * begin/end and begin/while rules.
 */
final class Tokenizer
{
    private const MAX_LINE_LENGTH = 100000;

    private OnigString $onigString;

    private int $lineLength;

    public function __construct(
        private readonly Grammar $grammar,
    ) {
    }

    public function tokenizeLine(string $line, ?StateStack $prevState): TokenizeLineResult
    {
        $isFirstLine = $prevState === null;
        $stack = $prevState ?? $this->grammar->initialState();

        $lineWithNewline = $line . "\n";
        $this->onigString = new OnigString($lineWithNewline);
        $this->lineLength = self::utf16Length($lineWithNewline);

        $lineTokens = new LineTokens();

        if ($this->lineLength - 1 > self::MAX_LINE_LENGTH) {
            $scopes = $stack->contentNameScopesList?->toArray() ?? [];
            return new TokenizeLineResult([new Token(0, $this->lineLength - 1, $scopes)], $stack, true);
        }

        $whileCheck = $this->checkWhileConditions($isFirstLine, $stack, $lineTokens);
        $stack = $this->tokenizeString(
            $whileCheck['stack'],
            $whileCheck['isFirstLine'],
            $whileCheck['linePos'],
            $whileCheck['anchorPosition'],
            $lineTokens,
        );

        $lineLengthWithoutNewline = self::utf16Length($line);
        $tokens = self::clampToLine($lineTokens->getResult($stack, $this->lineLength), $lineLengthWithoutNewline);

        return new TokenizeLineResult($tokens, $stack, false);
    }

    /**
     * The line was tokenized with a trailing `\n`; clip any token that runs into
     * it so the last token ends exactly at the real line length.
     *
     * @param list<Token> $tokens
     * @return list<Token>
     */
    private static function clampToLine(array $tokens, int $lineLength): array
    {
        $out = [];
        foreach ($tokens as $token) {
            if ($token->startIndex >= $lineLength) {
                continue;
            }
            $end = min($token->endIndex, $lineLength);
            $out[] = $end === $token->endIndex ? $token : new Token($token->startIndex, $end, $token->scopes);
        }

        return $out;
    }

    private function tokenizeString(
        StateStack $stack,
        bool $isFirstLine,
        int $linePos,
        int $anchorPosition,
        LineTokens $lineTokens,
    ): StateStack {
        while (true) {
            $result = $this->matchRuleOrInjections($stack, $isFirstLine, $linePos, $anchorPosition);

            if ($result === null) {
                $lineTokens->produce($stack, $this->lineLength);
                break;
            }

            $captureIndices = $result['captureIndices'];
            $matchedRuleId = $result['matchedRuleId'];
            $hasAdvanced = $captureIndices !== [] && $captureIndices[0]->end > $linePos;

            $currentRule = $stack->getRule($this->grammar);

            if ($matchedRuleId === Rule::END_RULE_ID) {
                assert($currentRule instanceof BeginEndRule);
                $poppedRule = $currentRule;

                $lineTokens->produce($stack, $captureIndices[0]->start);
                $stack = $stack->withContentNameScopesList($stack->nameScopesList);
                $this->handleCaptures($stack, $lineTokens, $poppedRule->endCaptures, $captureIndices);
                $lineTokens->produce($stack, $captureIndices[0]->end);

                $popped = $stack;
                $stack = $stack->pop() ?? $popped;
                $anchorPosition = $popped->anchorPosition;

                if (!$hasAdvanced && $popped->getEnterPos() === $linePos) {
                    $stack = $popped;
                    $lineTokens->produce($stack, $this->lineLength);
                    break;
                }
            } else {
                $rule = $this->grammar->getRule($matchedRuleId);

                $lineTokens->produce($stack, $captureIndices[0]->start);
                $beforePush = $stack;

                $scopeName = $rule->nameScope($this->onigString->content, $captureIndices);
                $nameScopesList = ScopeStack::pushScopes($stack->contentNameScopesList, $scopeName ?? '');
                $stack = $stack->push(
                    $matchedRuleId,
                    $anchorPosition,
                    $captureIndices[0]->end === $this->lineLength,
                    null,
                    $nameScopesList,
                    $nameScopesList,
                    $linePos,
                );

                if ($rule instanceof BeginEndRule) {
                    $this->handleCaptures($stack, $lineTokens, $rule->beginCaptures, $captureIndices);
                    $lineTokens->produce($stack, $captureIndices[0]->end);
                    $anchorPosition = $captureIndices[0]->end;

                    $contentName = $rule->contentNameScope($this->onigString->content, $captureIndices);
                    $contentNameScopesList = ScopeStack::pushScopes($nameScopesList, $contentName ?? '');
                    $stack = $stack->withContentNameScopesList($contentNameScopesList);

                    if ($rule->endHasBackReferences()) {
                        $stack = $stack->withEndRule(
                            $rule->end->resolveBackReferences($this->onigString->content, $captureIndices),
                        );
                    }

                    if (!$hasAdvanced && $beforePush->hasSameRuleAs($stack)) {
                        $stack = $stack->pop() ?? $stack;
                        $lineTokens->produce($stack, $this->lineLength);
                        break;
                    }
                } elseif ($rule instanceof BeginWhileRule) {
                    $this->handleCaptures($stack, $lineTokens, $rule->beginCaptures, $captureIndices);
                    $lineTokens->produce($stack, $captureIndices[0]->end);
                    $anchorPosition = $captureIndices[0]->end;

                    $contentName = $rule->contentNameScope($this->onigString->content, $captureIndices);
                    $contentNameScopesList = ScopeStack::pushScopes($nameScopesList, $contentName ?? '');
                    $stack = $stack->withContentNameScopesList($contentNameScopesList);

                    if ($rule->whileHasBackReferences()) {
                        $stack = $stack->withEndRule(
                            $rule->while->resolveBackReferences($this->onigString->content, $captureIndices),
                        );
                    }

                    if (!$hasAdvanced && $beforePush->hasSameRuleAs($stack)) {
                        $stack = $stack->pop() ?? $stack;
                        $lineTokens->produce($stack, $this->lineLength);
                        break;
                    }
                } else {
                    assert($rule instanceof MatchRule);
                    $this->handleCaptures($stack, $lineTokens, $rule->captures, $captureIndices);
                    $lineTokens->produce($stack, $captureIndices[0]->end);

                    $stack = $stack->pop() ?? $stack;

                    if (!$hasAdvanced) {
                        $stack = $stack->safePopForFailedMatch();
                        $lineTokens->produce($stack, $this->lineLength);
                        break;
                    }
                }
            }

            if ($captureIndices[0]->end > $linePos) {
                $linePos = $captureIndices[0]->end;
                $isFirstLine = false;
            }
        }

        return $stack;
    }

    /**
     * @return array{captureIndices: list<OnigCaptureIndex>, matchedRuleId: int}|null
     */
    private function matchRuleOrInjections(
        StateStack $stack,
        bool $isFirstLine,
        int $linePos,
        int $anchorPosition,
    ): ?array {
        $matchResult = $this->matchRule($stack, $isFirstLine, $linePos, $anchorPosition);

        $injections = $this->grammar->injections();
        if ($injections === []) {
            return $matchResult;
        }

        $injectionResult = $this->matchInjections($injections, $stack, $isFirstLine, $linePos, $anchorPosition);
        if ($injectionResult === null) {
            return $matchResult;
        }

        if ($matchResult === null) {
            return $injectionResult;
        }

        $matchStart = $matchResult['captureIndices'][0]->start;
        $injectionStart = $injectionResult['captureIndices'][0]->start;

        if (
            $injectionStart < $matchStart
            || ($injectionResult['priority'] === -1 && $injectionStart === $matchStart)
        ) {
            return ['captureIndices' => $injectionResult['captureIndices'], 'matchedRuleId' => $injectionResult['matchedRuleId']];
        }

        return $matchResult;
    }

    /**
     * @return array{captureIndices: list<OnigCaptureIndex>, matchedRuleId: int}|null
     */
    private function matchRule(
        StateStack $stack,
        bool $isFirstLine,
        int $linePos,
        int $anchorPosition,
    ): ?array {
        $rule = $stack->getRule($this->grammar);
        $ruleScanner = $this->compileRule($rule, $stack->endRule, $isFirstLine, $linePos === $anchorPosition);

        $match = $ruleScanner->scanner->findNextMatch($this->onigString, $linePos);
        if ($match === null) {
            return null;
        }

        return [
            'captureIndices' => $match->captureIndices,
            'matchedRuleId' => $ruleScanner->ruleIds[$match->index],
        ];
    }

    /**
     * @param list<Injection> $injections
     * @return array{captureIndices: list<OnigCaptureIndex>, matchedRuleId: int, priority: int}|null
     */
    private function matchInjections(
        array $injections,
        StateStack $stack,
        bool $isFirstLine,
        int $linePos,
        int $anchorPosition,
    ): ?array {
        $scopes = $stack->contentNameScopesList?->toArray() ?? [];

        $bestMatchRating = PHP_INT_MAX;
        $bestMatch = null;

        foreach ($injections as $injection) {
            if (!$injection->matches($scopes)) {
                continue;
            }

            $rule = $this->grammar->getRule($injection->ruleId);
            $ruleScanner = $this->compileRule($rule, $stack->endRule, $isFirstLine, $linePos === $anchorPosition);
            $match = $ruleScanner->scanner->findNextMatch($this->onigString, $linePos);
            if ($match === null) {
                continue;
            }

            $matchRating = $match->captureIndices[0]->start;
            if ($matchRating >= $bestMatchRating) {
                continue;
            }

            $bestMatchRating = $matchRating;
            $bestMatch = [
                'captureIndices' => $match->captureIndices,
                'matchedRuleId' => $ruleScanner->ruleIds[$match->index],
                'priority' => $injection->priority,
            ];

            if ($matchRating === $linePos) {
                break;
            }
        }

        return $bestMatch;
    }

    private function compileRule(Rule $rule, ?string $endRule, bool $allowA, bool $allowG): CompiledRule
    {
        $list = $rule->compile($this->grammar->rulesById(), $endRule, $allowA, $allowG);
        return $list->compile($allowA, $allowG);
    }

    /**
     * @return array{stack: StateStack, linePos: int, anchorPosition: int, isFirstLine: bool}
     */
    private function checkWhileConditions(
        bool $isFirstLine,
        StateStack $stack,
        LineTokens $lineTokens,
    ): array {
        $anchorPosition = $stack->beginRuleCapturedEOL ? 0 : -1;

        $whileRules = [];
        for ($node = $stack; $node !== null; $node = $node->pop()) {
            $rule = $node->getRule($this->grammar);
            if ($rule instanceof BeginWhileRule) {
                $whileRules[] = ['stack' => $node, 'rule' => $rule];
            }
        }

        $linePos = 0;
        for ($i = count($whileRules) - 1; $i >= 0; $i--) {
            /** @var BeginWhileRule $rule */
            $rule = $whileRules[$i]['rule'];
            /** @var StateStack $whileStack */
            $whileStack = $whileRules[$i]['stack'];

            $sourceList = $rule->compileWhile($whileStack->endRule, $isFirstLine, $anchorPosition === $linePos);
            $compiled = $sourceList->compile($isFirstLine, $anchorPosition === $linePos);
            $match = $compiled->scanner->findNextMatch($this->onigString, $linePos);

            if ($match === null) {
                $stack = $whileStack->pop() ?? $whileStack;
                break;
            }

            $lineTokens->produce($whileStack, $match->captureIndices[0]->start);
            $this->handleCaptures($whileStack, $lineTokens, $rule->whileCaptures, $match->captureIndices);
            $lineTokens->produce($whileStack, $match->captureIndices[0]->end);
            $anchorPosition = $match->captureIndices[0]->end;
            if ($match->captureIndices[0]->end > $linePos) {
                $linePos = $match->captureIndices[0]->end;
                $isFirstLine = false;
            }
        }

        return ['stack' => $stack, 'linePos' => $linePos, 'anchorPosition' => $anchorPosition, 'isFirstLine' => $isFirstLine];
    }

    /**
     * @param array<int, ?int> $captures group number → CaptureRule id
     * @param list<OnigCaptureIndex> $captureIndices
     */
    private function handleCaptures(
        StateStack $stack,
        LineTokens $lineTokens,
        array $captures,
        array $captureIndices,
    ): void {
        if ($captures === []) {
            return;
        }

        $len = min(count($captures), count($captureIndices));
        /** @var list<array{scopes: ?ScopeStack, endPos: int}> $localStack */
        $localStack = [];
        $maxEnd = $captureIndices[0]->end;

        for ($i = 0; $i < $len; $i++) {
            $captureRuleId = $captures[$i] ?? null;
            if ($captureRuleId === null) {
                continue;
            }

            $captureRule = $this->grammar->getRule($captureRuleId);
            assert($captureRule instanceof CaptureRule);

            $captureIndex = $captureIndices[$i];
            if ($captureIndex->length() === 0) {
                continue;
            }

            if ($captureIndex->start > $maxEnd) {
                break;
            }

            while ($localStack !== [] && $localStack[count($localStack) - 1]['endPos'] <= $captureIndex->start) {
                $popped = array_pop($localStack);
                $lineTokens->produceFromScopes($popped['scopes'], $popped['endPos']);
            }

            if ($localStack !== []) {
                $lineTokens->produceFromScopes($localStack[count($localStack) - 1]['scopes'], $captureIndex->start);
            } else {
                $lineTokens->produce($stack, $captureIndex->start);
            }

            if ($captureRule->retokenizeCapturedWithRuleId !== null) {
                $scopeName = $captureRule->nameScope($this->onigString->content, $captureIndices);
                $nameScopesList = ScopeStack::pushScopes($stack->contentNameScopesList, $scopeName ?? '');
                $contentName = $captureRule->contentNameScope($this->onigString->content, $captureIndices);
                $contentNameScopesList = ScopeStack::pushScopes($nameScopesList, $contentName ?? '');

                $captureSubStack = $stack->push(
                    $captureRule->retokenizeCapturedWithRuleId,
                    -1,
                    false,
                    null,
                    $nameScopesList,
                    $contentNameScopesList,
                    $captureIndex->start,
                );

                $regionText = self::utf16Substr($this->onigString->content, 0, $captureIndex->end);
                $this->tokenizeCaptureRegion($regionText, $captureIndex->start === 0, $captureIndex->start, $captureSubStack, $lineTokens);
                continue;
            }

            $captureRuleScopeName = $captureRule->nameScope($this->onigString->content, $captureIndices);
            if ($captureRuleScopeName === null) {
                continue;
            }

            $base = $localStack !== []
                ? $localStack[count($localStack) - 1]['scopes']
                : $stack->contentNameScopesList;
            $captureRuleScopesList = ScopeStack::pushScopes($base, $captureRuleScopeName);

            $localStack[] = ['scopes' => $captureRuleScopesList, 'endPos' => $captureIndex->end];
        }

        while ($localStack !== []) {
            $popped = array_pop($localStack);
            $lineTokens->produceFromScopes($popped['scopes'], $popped['endPos']);
        }
    }

    private function tokenizeCaptureRegion(
        string $regionText,
        bool $isFirstLine,
        int $linePos,
        StateStack $stack,
        LineTokens $lineTokens,
    ): void {
        $savedString = $this->onigString;
        $savedLength = $this->lineLength;

        $this->onigString = new OnigString($regionText);
        $this->lineLength = self::utf16Length($regionText);

        $this->tokenizeString($stack, $isFirstLine, $linePos, -1, $lineTokens);

        $this->onigString = $savedString;
        $this->lineLength = $savedLength;
    }

    private static function utf16Length(string $utf8): int
    {
        $utf16 = mb_convert_encoding($utf8, 'UTF-16LE', 'UTF-8');
        return intdiv(strlen($utf16), 2);
    }

    private static function utf16Substr(string $utf8, int $startCodeUnit, int $endCodeUnit): string
    {
        $utf16 = mb_convert_encoding($utf8, 'UTF-16LE', 'UTF-8');
        $slice = substr($utf16, $startCodeUnit * 2, ($endCodeUnit - $startCodeUnit) * 2);
        return mb_convert_encoding($slice, 'UTF-8', 'UTF-16LE');
    }
}
