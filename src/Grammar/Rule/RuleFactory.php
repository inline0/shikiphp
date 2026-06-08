<?php

declare(strict_types=1);

namespace Shikiphp\Grammar\Rule;

/**
 * Compiles raw `.tmLanguage` rule arrays into Rule objects with unique ids,
 * resolving `include` references through a RuleFactoryHelper. Mirrors
 * vscode-textmate's `RuleFactory`.
 */
final class RuleFactory
{
    /**
     * @param array<array-key, mixed> $desc
     * @param ?int $reservedId an id already minted by the caller (so a cyclic
     *   include can be cached before this rule's sub-patterns are compiled)
     */
    public static function getCompiledRuleId(array $desc, RuleFactoryHelper $helper, ?int $reservedId = null): int
    {
        if ($reservedId === null && isset($desc['__ruleId']) && is_int($desc['__ruleId'])) {
            return $desc['__ruleId'];
        }

        $id = $reservedId ?? $helper->nextRuleId();
        $desc['__ruleId'] = $id;

        $repository = isset($desc['repository']) && is_array($desc['repository']) ? $desc['repository'] : null;
        $helper->pushRepository($repository);
        try {
            return self::buildRule($desc, $helper, $id);
        } finally {
            $helper->popRepository($repository);
        }
    }

    /** @param array<array-key, mixed> $desc */
    private static function buildRule(array $desc, RuleFactoryHelper $helper, int $id): int
    {
        $name = self::stringOrNull($desc['name'] ?? null);
        $contentName = self::stringOrNull($desc['contentName'] ?? null);

        if (isset($desc['match']) && is_string($desc['match'])) {
            $rule = new MatchRule(
                $id,
                $name,
                new RegExpSource($desc['match'], $id),
                self::compileCaptures($desc['captures'] ?? null, $helper),
            );
            $helper->registerRule($rule);
            return $id;
        }

        if (!isset($desc['begin'])) {
            $patterns = self::compilePatterns($desc['patterns'] ?? null, $helper);
            $rule = new IncludeOnlyRule($id, $name, $contentName, $patterns['ids'], $patterns['hasMissing']);
            $helper->registerRule($rule);
            return $id;
        }

        assert(is_string($desc['begin']));
        $beginCaptures = self::compileCaptures($desc['beginCaptures'] ?? $desc['captures'] ?? null, $helper);
        $patterns = self::compilePatterns($desc['patterns'] ?? null, $helper);

        if (isset($desc['while']) && is_string($desc['while'])) {
            $rule = new BeginWhileRule(
                $id,
                $name,
                $contentName,
                new RegExpSource($desc['begin'], $id),
                $beginCaptures,
                new RegExpSource($desc['while'], Rule::WHILE_RULE_ID),
                self::compileCaptures($desc['whileCaptures'] ?? $desc['captures'] ?? null, $helper),
                $patterns['ids'],
            );
            $helper->registerRule($rule);
            return $id;
        }

        $end = is_string($desc['end'] ?? null) ? $desc['end'] : "\u{ffff}";
        $rule = new BeginEndRule(
            $id,
            $name,
            $contentName,
            new RegExpSource($desc['begin'], $id),
            $beginCaptures,
            new RegExpSource($end, Rule::END_RULE_ID),
            self::compileCaptures($desc['endCaptures'] ?? $desc['captures'] ?? null, $helper),
            $patterns['ids'],
            (bool) ($desc['applyEndPatternLast'] ?? false),
        );
        $helper->registerRule($rule);
        return $id;
    }

    /**
     * @param list<array<array-key, mixed>> $patterns
     * @return array{ids: list<int>, hasMissing: bool}
     */
    public static function compileRootPatterns(array $patterns, RuleFactoryHelper $helper): array
    {
        return self::compilePatterns($patterns, $helper);
    }

    /**
     * @param mixed $captures
     * @return array<int, ?int> group number → CaptureRule id (or null)
     */
    private static function compileCaptures(mixed $captures, RuleFactoryHelper $helper): array
    {
        if (!is_array($captures)) {
            return [];
        }

        $out = [];
        $maximumKey = -1;
        foreach (array_keys($captures) as $key) {
            $numeric = (int) $key;
            $maximumKey = max($maximumKey, $numeric);
        }
        for ($i = 0; $i <= $maximumKey; $i++) {
            $out[$i] = null;
        }

        foreach ($captures as $key => $value) {
            if (!is_array($value)) {
                continue;
            }
            $numeric = (int) $key;

            $name = self::stringOrNull($value['name'] ?? null);
            $contentName = self::stringOrNull($value['contentName'] ?? null);

            $retokenizeId = null;
            if (isset($value['patterns']) || isset($value['begin']) || isset($value['match'])) {
                $retokenizeId = self::getCompiledRuleId($value, $helper);
            }

            $captureRule = new CaptureRule($helper->nextRuleId(), $name, $contentName, $retokenizeId);
            $helper->registerRule($captureRule);
            $out[$numeric] = $captureRule->id;
        }

        return $out;
    }

    /**
     * @param mixed $patterns
     * @return array{ids: list<int>, hasMissing: bool}
     */
    private static function compilePatterns(mixed $patterns, RuleFactoryHelper $helper): array
    {
        if (!is_array($patterns)) {
            return ['ids' => [], 'hasMissing' => false];
        }

        $ids = [];
        $hasMissing = false;

        foreach ($patterns as $pattern) {
            if (!is_array($pattern)) {
                continue;
            }

            $include = $pattern['include'] ?? null;
            if (is_string($include)) {
                $resolved = self::resolveInclude($include, $helper);
                if ($resolved === null) {
                    $hasMissing = true;
                    continue;
                }
                $ids[] = $resolved;
                continue;
            }

            $ids[] = self::getCompiledRuleId($pattern, $helper);
        }

        return ['ids' => $ids, 'hasMissing' => $hasMissing];
    }

    private static function resolveInclude(string $include, RuleFactoryHelper $helper): ?int
    {
        if ($include === '$self') {
            return $helper->resolveSelf();
        }
        if ($include === '$base') {
            return $helper->resolveBase();
        }
        if ($include[0] === '#') {
            return $helper->resolveRepositoryReference(substr($include, 1));
        }

        $hash = strpos($include, '#');
        if ($hash === false) {
            return $helper->resolveExternalInclude($include, null);
        }

        $scopeName = substr($include, 0, $hash);
        $ruleName = substr($include, $hash + 1);
        return $helper->resolveExternalInclude($scopeName, $ruleName);
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
