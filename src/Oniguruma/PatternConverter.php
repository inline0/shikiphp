<?php

declare(strict_types=1);

namespace Shikiphp\Oniguruma;

use Shikiphp\Oniguruma\Exceptions\ConversionFailed;

/**
 * PHP port of oniguruma-to-es: rewrites a TextMate-grammar Oniguruma
 * pattern into a JS-RegExp source + flags accepted by Shikiphp\Regex.
 *
 * Output is emitted with the `u` flag so `\p{...}` resolves against the
 * engine's real Unicode tables; the Matcher maps UTF-16 code-unit offsets
 * to/from code points itself, so scanner offsets stay UTF-16 throughout.
 */
final class PatternConverter
{
    /** @var array<string, string> POSIX class → JS char-class body. */
    private const POSIX = [
        'alpha' => '\\p{L}',
        'alnum' => '\\p{L}\\p{Nd}',
        'digit' => '\\p{Nd}',
        'xdigit' => '0-9A-Fa-f',
        'space' => '\\s',
        'blank' => ' \\t',
        'upper' => '\\p{Lu}',
        'lower' => '\\p{Ll}',
        'word' => '\\p{L}\\p{Nd}\\p{Pc}\\p{Mn}\\p{Mc}',
        'punct' => '\\p{P}\\p{S}',
        'cntrl' => '\\x00-\\x1F\\x7F',
        'graph' => '\\x21-\\x7E\\P{Cc}',
        'print' => '\\x20-\\x7E\\P{Cc}',
        'ascii' => '\\x00-\\x7F',
    ];

    /** @var array<string, string> Negated POSIX class → JS char-class body (single-complement forms only). */
    private const POSIX_NEGATED = [
        'alpha' => '\\P{L}',
        'digit' => '\\P{Nd}',
        'space' => '\\S',
        'upper' => '\\P{Lu}',
        'lower' => '\\P{Ll}',
        'word' => '\\W',
    ];

    private string $src = '';
    private int $pos = 0;
    private int $len = 0;
    private bool $extended = false;
    private bool $flagI = false;
    private bool $flagM = false;
    private bool $flagS = false;
    private bool $sticky = false;
    private int $groupCount = 0;
    private int $atomicCount = 0;

    /** @var array<int|string, string> Converted inner body of each capturing group, keyed by number and name. */
    private array $groupBodies = [];

    /** @return array{pattern: string, flags: string, atomicSlots: list<int>} */
    public function convert(string $onigPattern): array
    {
        $this->src = $onigPattern;
        $this->len = strlen($onigPattern);
        $this->pos = 0;
        $this->extended = false;
        $this->flagI = false;
        $this->flagM = false;
        $this->flagS = false;
        $this->sticky = false;
        $this->groupCount = 0;
        $this->atomicCount = 0;
        $this->groupBodies = [];

        $this->consumeLeadingGlobalFlags();

        $out = $this->convertSequence(false);
        if ($this->pos < $this->len) {
            throw ConversionFailed::unbalanced($onigPattern);
        }

        $flags = 'u';
        if ($this->flagI) {
            $flags .= 'i';
        }
        if ($this->flagM) {
            $flags .= 'm';
        }
        if ($this->flagS) {
            $flags .= 's';
        }
        if ($this->sticky) {
            $flags .= 'y';
        }

        return ['pattern' => $out, 'flags' => $flags, 'atomicSlots' => $this->atomicSlots($out)];
    }

    /**
     * Walk the converted source and return the JS capture-slot numbers occupied
     * by atomic-emulation groups (`(?<atomicN>…)`). A named group still consumes
     * a numbered slot in JS, so the scanner must strip these from its results.
     *
     * @return list<int>
     */
    private function atomicSlots(string $pattern): array
    {
        $slots = [];
        $group = 0;
        $n = strlen($pattern);
        $inClass = false;
        for ($i = 0; $i < $n; $i++) {
            $ch = $pattern[$i];
            if ($ch === '\\') {
                $i++;
                continue;
            }
            if ($inClass) {
                if ($ch === ']') {
                    $inClass = false;
                }
                continue;
            }
            if ($ch === '[') {
                $inClass = true;
                continue;
            }
            if ($ch !== '(') {
                continue;
            }
            if (substr($pattern, $i + 1, 1) !== '?') {
                $group++;
                continue;
            }
            $rest = substr($pattern, $i, 32);
            if (preg_match('/^\\(\\?<atomic\\d+>/', $rest) === 1) {
                $group++;
                $slots[] = $group;
                continue;
            }
            if (preg_match('/^\\(\\?<[A-Za-z_]/', $rest) === 1) {
                $group++;
            }
        }
        return $slots;
    }

    private function consumeLeadingGlobalFlags(): void
    {
        while ($this->pos < $this->len) {
            $flags = $this->matchGlobalFlagGroup();
            if ($flags === null) {
                return;
            }
            foreach (str_split($flags) as $f) {
                $this->applyFlag($f);
            }
        }
    }

    /** Match a leading `(?flags)` group at the cursor; returns its flag letters or null. */
    private function matchGlobalFlagGroup(): ?string
    {
        if (!str_starts_with(substr($this->src, $this->pos), '(?')) {
            return null;
        }
        $close = strpos($this->src, ')', $this->pos + 2);
        if ($close === false) {
            return null;
        }
        $inner = substr($this->src, $this->pos + 2, $close - ($this->pos + 2));
        if ($inner === '' || preg_match('/^[imsx]+$/', $inner) !== 1) {
            return null;
        }
        $this->pos = $close + 1;
        return $inner;
    }

    private function applyFlag(string $f): void
    {
        if ($f === 'i') {
            $this->flagI = true;
        } elseif ($f === 'm') {
            $this->flagM = true;
            $this->flagS = true;
        } elseif ($f === 's') {
            $this->flagS = true;
        } elseif ($f === 'x') {
            $this->extended = true;
        }
    }

    /** Convert a sequence of alternatives up to a closing `)` (group) or end. */
    private function convertSequence(bool $inGroup): string
    {
        $out = '';
        while ($this->pos < $this->len) {
            $ch = $this->src[$this->pos];

            if ($ch === ')') {
                if ($inGroup) {
                    return $out;
                }
                throw ConversionFailed::unbalanced($this->src);
            }

            if ($this->extended && $this->skipExtendedWhitespace($ch)) {
                continue;
            }

            if ($ch === '|') {
                $out .= '|';
                $this->pos++;
                continue;
            }

            if ($ch === '(') {
                $out .= $this->applyQuantifier($this->convertGroup());
                continue;
            }

            if ($ch === '[') {
                $out .= $this->applyQuantifier($this->convertCharClass());
                continue;
            }

            if ($ch === '\\') {
                $atom = $this->convertEscape();
                $out .= $this->applyQuantifier($atom);
                continue;
            }

            if ($ch === '^' || $ch === '$' || $ch === '.') {
                $this->pos++;
                $out .= $this->applyQuantifier($ch);
                continue;
            }

            $this->pos++;
            $out .= $this->applyQuantifier($this->escapeLiteral($ch));
        }

        if ($inGroup) {
            throw ConversionFailed::unbalanced($this->src);
        }
        return $out;
    }

    /** In extended mode: skip an unescaped space or `#...` comment. Returns true if consumed. */
    private function skipExtendedWhitespace(string $ch): bool
    {
        if ($ch === ' ' || $ch === "\t" || $ch === "\n" || $ch === "\r" || $ch === "\f") {
            $this->pos++;
            return true;
        }
        if ($ch === '#') {
            while ($this->pos < $this->len && $this->src[$this->pos] !== "\n") {
                $this->pos++;
            }
            return true;
        }
        return false;
    }

    private function convertGroup(): string
    {
        $this->pos++; // consume (
        if ($this->pos >= $this->len) {
            throw ConversionFailed::unbalanced($this->src);
        }

        if ($this->src[$this->pos] !== '?') {
            $this->groupCount++;
            $number = $this->groupCount;
            $body = $this->convertSequence(true);
            $this->expect(')');
            $this->groupBodies[$number] = $body;
            return '(' . $body . ')';
        }

        $this->pos++; // consume ?
        $c = $this->pos < $this->len ? $this->src[$this->pos] : '';

        if ($c === ':' || $c === '=' || $c === '!') {
            $this->pos++;
            $body = $this->convertSequence(true);
            $this->expect(')');
            return '(?' . $c . $body . ')';
        }

        if ($c === '>') {
            $this->pos++;
            $body = $this->convertSequence(true);
            $this->expect(')');
            return $this->atomic($body);
        }

        if ($c === '<') {
            return $this->convertAngleGroup();
        }

        if ($c === "'") {
            return $this->convertQuotedNameGroup();
        }

        if ($c === '#') {
            while ($this->pos < $this->len && $this->src[$this->pos] !== ')') {
                $this->pos++;
            }
            $this->expect(')');
            return '';
        }

        if (preg_match('/^[imsx]/', $c) === 1) {
            return $this->convertScopedFlagGroup();
        }

        throw ConversionFailed::unsupported('(?' . $c, $this->src);
    }

    private function convertAngleGroup(): string
    {
        $this->pos++; // consume <
        $c = $this->pos < $this->len ? $this->src[$this->pos] : '';
        if ($c === '=' || $c === '!') {
            $this->pos++;
            $body = $this->convertSequence(true);
            $this->expect(')');
            return '(?<' . $c . $body . ')';
        }
        $name = $this->readName('>');
        $this->expect('>');
        $this->groupCount++;
        $number = $this->groupCount;
        $body = $this->convertSequence(true);
        $this->expect(')');
        $this->groupBodies[$number] = $body;
        $this->groupBodies[$name] = $body;
        return '(?<' . $name . '>' . $body . ')';
    }

    private function convertQuotedNameGroup(): string
    {
        $this->pos++; // consume '
        $name = $this->readName("'");
        $this->expect("'");
        $this->groupCount++;
        $number = $this->groupCount;
        $body = $this->convertSequence(true);
        $this->expect(')');
        $this->groupBodies[$number] = $body;
        $this->groupBodies[$name] = $body;
        return '(?<' . $name . '>' . $body . ')';
    }

    private function convertScopedFlagGroup(): string
    {
        $addI = $addM = $addS = false;
        $remove = false;
        $remI = $remM = $remS = false;
        while ($this->pos < $this->len) {
            $c = $this->src[$this->pos];
            if ($c === 'i') {
                $remove ? $remI = true : $addI = true;
            } elseif ($c === 'm') {
                $remove ? $remM = true : $addM = true;
            } elseif ($c === 's') {
                $remove ? $remS = true : $addS = true;
            } elseif ($c === 'x') {
                // extended toggled locally; handled by stripping, no JS flag.
            } elseif ($c === '-') {
                $remove = true;
            } else {
                break;
            }
            $this->pos++;
        }

        if ($this->pos < $this->len && $this->src[$this->pos] === ')') {
            $this->pos++;
            if ($addI) {
                $this->flagI = true;
            }
            if ($addM) {
                $this->flagM = true;
            }
            if ($addS) {
                $this->flagS = true;
            }
            return '';
        }

        $this->expect(':');
        $body = $this->convertSequence(true);
        $this->expect(')');

        if ($addM) {
            $addS = true;
        }
        $prefix = ($addI ? 'i' : '') . ($addM ? 'm' : '') . ($addS ? 's' : '');
        $suffix = ($remI ? 'i' : '') . ($remM ? 'm' : '') . ($remS ? 's' : '');
        $spec = $prefix . ($suffix !== '' ? '-' . $suffix : '');
        if ($spec === '') {
            return '(?:' . $body . ')';
        }
        return '(?' . $spec . ':' . $body . ')';
    }

    /**
     * The JS engine has no set operations (the `v` flag's `&&`/`--` are
     * unsupported here), so an intersection `[A&&B]` is emulated by matching one
     * code unit that satisfies every operand: lookaheads for all but the last,
     * the last consuming. A plain class without `&&` is emitted directly.
     */
    private function convertCharClass(): string
    {
        $this->pos++; // consume [
        $negated = false;
        if ($this->pos < $this->len && $this->src[$this->pos] === '^') {
            $negated = true;
            $this->pos++;
        }

        $operands = [$this->readClassOperand(true)];
        while ($this->atDoubleAmp()) {
            $this->pos += 2;
            $operands[] = $this->readClassOperand(false);
        }
        $this->expect(']');

        if (count($operands) === 1) {
            return $this->renderOperand($negated, $operands[0]);
        }

        $last = array_pop($operands);
        $out = '(?:';
        foreach ($operands as $op) {
            $out .= '(?=' . $this->renderOperand(false, $op) . ')';
        }
        $out .= $this->renderOperand($negated, $last) . ')';
        return $out;
    }

    /**
     * A positive class that contains negated nested subclasses (which JS cannot
     * express as a class member) becomes a union: `(?:[members]|[^sub]…)`.
     *
     * @param array{negated: string, body: string, alternatives: list<string>} $op
     */
    private function renderOperand(bool $negated, array $op): string
    {
        if ($op['alternatives'] === []) {
            return '[' . ($negated ? '^' : '') . $op['negated'] . $op['body'] . ']';
        }

        $branches = [];
        if ($op['body'] !== '' || $op['negated'] !== '') {
            $branches[] = '[' . $op['negated'] . $op['body'] . ']';
        }
        foreach ($op['alternatives'] as $alt) {
            $branches[] = $alt;
        }

        $union = '(?:' . implode('|', $branches) . ')';
        if (!$negated) {
            return $union;
        }

        return '(?:(?!' . $union . ')[\\s\\S])';
    }

    /**
     * Read one operand of a (possibly intersected) class up to `&&` or `]`.
     *
     * @param bool $allowLeadingBracket A literal `]` is only special as the first member of the whole class.
     * @return array{negated: string, body: string, alternatives: list<string>} `negated` is '^' for a sole-nested-class operand; `alternatives` holds negated nested subclasses lifted into a union.
     */
    private function readClassOperand(bool $allowLeadingBracket): array
    {
        $body = '';
        $negated = '';
        $alternatives = [];
        if ($allowLeadingBracket && $this->pos < $this->len && $this->src[$this->pos] === ']') {
            $body .= '\\]';
            $this->pos++;
        }

        while ($this->pos < $this->len && $this->src[$this->pos] !== ']') {
            if ($this->atDoubleAmp()) {
                break;
            }
            $ch = $this->src[$this->pos];

            if ($ch === '[' && $this->peekPosix() !== null) {
                $body .= $this->consumePosix();
                continue;
            }

            if ($ch === '[') {
                $nested = $this->readNestedClass();
                if ($body === '' && $alternatives === [] && $this->atOperandEnd()) {
                    $negated = $nested['negated'] ? '^' : '';
                    $body = $nested['body'];
                    break;
                }
                if ($nested['negated']) {
                    $alternatives[] = '[^' . $nested['body'] . ']';
                    continue;
                }
                $body .= $nested['body'];
                continue;
            }

            if ($ch === '\\') {
                $body .= $this->convertClassEscape();
                continue;
            }

            $this->pos++;
            $body .= $this->escapeClassLiteral($ch);
        }

        return ['negated' => $negated, 'body' => $body, 'alternatives' => $alternatives];
    }

    private function atOperandEnd(): bool
    {
        if ($this->pos >= $this->len) {
            return false;
        }
        return $this->src[$this->pos] === ']' || $this->atDoubleAmp();
    }

    private function atDoubleAmp(): bool
    {
        return $this->pos + 1 < $this->len
            && $this->src[$this->pos] === '&'
            && $this->src[$this->pos + 1] === '&';
    }

    /** @return array{negated: bool, body: string} */
    private function readNestedClass(): array
    {
        $this->pos++; // consume [
        $negated = false;
        if ($this->pos < $this->len && $this->src[$this->pos] === '^') {
            $negated = true;
            $this->pos++;
        }
        $body = '';
        while ($this->pos < $this->len && $this->src[$this->pos] !== ']') {
            $ch = $this->src[$this->pos];
            if ($ch === '[' && $this->peekPosix() !== null) {
                $body .= $this->consumePosix();
                continue;
            }
            if ($ch === '[') {
                $body .= $this->readNestedClass()['body'];
                continue;
            }
            if ($ch === '\\') {
                $body .= $this->convertClassEscape();
                continue;
            }
            $this->pos++;
            $body .= $this->escapeClassLiteral($ch);
        }
        $this->expect(']');
        return ['negated' => $negated, 'body' => $body];
    }

    /** Peek for a `[:name:]` POSIX class at the cursor; returns name or null. */
    private function peekPosix(): ?string
    {
        if (!str_starts_with(substr($this->src, $this->pos), '[:')) {
            return null;
        }
        $close = strpos($this->src, ':]', $this->pos + 2);
        if ($close === false) {
            return null;
        }
        $inner = substr($this->src, $this->pos + 2, $close - ($this->pos + 2));
        if (preg_match('/^\\^?[a-z]+$/', $inner) !== 1) {
            return null;
        }
        return $inner;
    }

    private function consumePosix(): string
    {
        $inner = $this->peekPosix();
        assert($inner !== null);
        $close = strpos($this->src, ':]', $this->pos);
        assert($close !== false);
        $this->pos = $close + 2;

        $negated = str_starts_with($inner, '^');
        $name = $negated ? substr($inner, 1) : $inner;
        if (!$negated) {
            return self::POSIX[$name] ?? '';
        }
        return self::POSIX_NEGATED[$name] ?? '';
    }

    private function convertClassEscape(): string
    {
        $this->pos++; // consume \
        if ($this->pos >= $this->len) {
            return '\\\\';
        }
        $c = $this->src[$this->pos];

        if ($c === 'h') {
            $this->pos++;
            return '\\p{AHex}';
        }
        if ($c === 'H') {
            $this->pos++;
            return '\\P{AHex}';
        }
        if ($c === 'p' || $c === 'P') {
            return $this->passUnicodeProperty(true);
        }

        $this->pos++;
        if (in_array($c, ['d', 'D', 'w', 'W', 's', 'S', 'n', 'r', 't', 'f', 'v', '0', 'b', '\\', ']', '[', '^', '-'], true)) {
            return '\\' . $c;
        }
        if ($c === 'x' || $c === 'u') {
            return $this->convertHexEscape($c);
        }
        return $this->escapeClassLiteral($c);
    }

    private function convertEscape(): string
    {
        $this->pos++; // consume \
        if ($this->pos >= $this->len) {
            return '\\\\';
        }
        $c = $this->src[$this->pos];

        if ($c === 'A') {
            $this->pos++;
            return '(?<![\\s\\S])';
        }
        if ($c === 'z') {
            $this->pos++;
            return '(?![\\s\\S])';
        }
        if ($c === 'Z') {
            $this->pos++;
            return '(?=\\n?(?![\\s\\S]))';
        }
        if ($c === 'G') {
            $this->pos++;
            $this->sticky = true;
            return '';
        }
        if ($c === 'R') {
            $this->pos++;
            return '(?:\\r\\n|[\\n\\r\\x0B\\f\\x85\\u2028\\u2029])';
        }
        if ($c === 'h') {
            $this->pos++;
            return '\\p{AHex}';
        }
        if ($c === 'H') {
            $this->pos++;
            return '\\P{AHex}';
        }
        if ($c === 'k') {
            return $this->convertNamedBackref();
        }
        if ($c === 'g') {
            return $this->convertSubroutineRef();
        }
        if ($c === 'p' || $c === 'P') {
            return $this->passUnicodeProperty(false);
        }

        $this->pos++;
        if (in_array($c, ['d', 'D', 'w', 'W', 's', 'S', 'b', 'B', 'n', 'r', 't', 'f', 'v', '0', 'A', 'Z'], true)) {
            return '\\' . $c;
        }
        if (ctype_digit($c)) {
            return '\\' . $c . $this->readDigitsTail();
        }
        if ($c === 'x' || $c === 'u') {
            return $this->convertHexEscape($c);
        }
        return $this->escapeLiteral($c);
    }

    private function passUnicodeProperty(bool $inClass): string
    {
        $pc = $this->src[$this->pos]; // p or P
        $this->pos++;
        if ($this->pos >= $this->len || $this->src[$this->pos] !== '{') {
            return $this->escapeLiteral($pc);
        }
        $close = strpos($this->src, '}', $this->pos);
        if ($close === false) {
            return $this->escapeLiteral($pc);
        }
        $body = substr($this->src, $this->pos + 1, $close - ($this->pos + 1));
        $this->pos = $close + 1;
        $negated = $pc === 'P';
        if (str_starts_with($body, '^')) {
            $negated = !$negated;
            $body = substr($body, 1);
        }

        $posix = strtolower($body);
        if (isset(self::POSIX[$posix])) {
            $expanded = self::expandPosixProperty($posix, $negated, $inClass);
            if ($expanded !== null) {
                return $expanded;
            }
        }

        return '\\' . ($negated ? 'P' : 'p') . '{' . $body . '}';
    }

    private static function expandPosixProperty(string $name, bool $negated, bool $inClass): ?string
    {
        if (!$negated) {
            $expanded = self::POSIX[$name];
            return $inClass || self::isSingleClassAtom($expanded) ? $expanded : '[' . $expanded . ']';
        }

        if (isset(self::POSIX_NEGATED[$name])) {
            return self::POSIX_NEGATED[$name];
        }

        if ($inClass) {
            return null;
        }

        return '[^' . self::POSIX[$name] . ']';
    }

    private static function isSingleClassAtom(string $expanded): bool
    {
        return preg_match('/^\\\\[pP]\\{[^}]+\\}$|^\\\\[a-zA-Z]$/', $expanded) === 1;
    }

    private function convertNamedBackref(): string
    {
        $this->pos++; // consume k
        $open = $this->pos < $this->len ? $this->src[$this->pos] : '';
        if ($open !== '<' && $open !== "'") {
            return 'k';
        }
        $close = $open === '<' ? '>' : "'";
        $this->pos++;
        $name = $this->readName($close);
        $this->expect($close);
        if ($name !== '' && ctype_digit($name[0])) {
            return '\\' . ltrim($name, '+-');
        }
        return '\\k<' . $name . '>';
    }

    /**
     * A subroutine call re-runs the referenced subpattern (it does not
     * re-match captured text). Inline the group's converted body as a fresh
     * capturing group, mirroring how oniguruma-to-es expands `\g<n>`.
     */
    private function convertSubroutineRef(): string
    {
        $this->pos++; // consume g
        $open = $this->pos < $this->len ? $this->src[$this->pos] : '';
        if ($open !== '<' && $open !== "'") {
            return 'g';
        }
        $close = $open === '<' ? '>' : "'";
        $this->pos++;
        $name = $this->readName($close);
        $this->expect($close);

        $key = $name;
        if ($name !== '' && (ctype_digit($name[0]) || $name[0] === '+' || $name[0] === '-')) {
            $key = (int) ltrim($name, '+-');
        }

        $body = $this->groupBodies[$key] ?? null;
        if ($body === null) {
            throw ConversionFailed::unsupported('\\g<' . $name . '>', $this->src);
        }
        return '(?:' . $body . ')';
    }

    /** Apply a quantifier (incl. possessive emulation) to the just-emitted atom. */
    private function applyQuantifier(string $atom): string
    {
        if ($this->pos >= $this->len) {
            return $atom;
        }
        $ch = $this->src[$this->pos];
        if ($ch !== '*' && $ch !== '+' && $ch !== '?' && $ch !== '{') {
            return $atom;
        }

        $quant = $this->readQuantifier();
        if ($quant === null) {
            return $atom;
        }

        $mode = '';
        if ($this->pos < $this->len) {
            $next = $this->src[$this->pos];
            if ($next === '?' || $next === '+') {
                $mode = $next;
                $this->pos++;
            }
        }

        if ($mode === '?') {
            return $atom . $quant . '?';
        }
        if ($mode === '+') {
            return $this->atomic($atom . $quant);
        }
        return $atom . $quant;
    }

    /** Read a quantifier token (`*`, `+`, `?`, `{n,m}`) without its mode suffix. */
    private function readQuantifier(): ?string
    {
        $ch = $this->src[$this->pos];
        if ($ch === '*' || $ch === '+' || $ch === '?') {
            $this->pos++;
            return $ch;
        }
        $close = strpos($this->src, '}', $this->pos);
        if ($close === false) {
            return null;
        }
        $inner = substr($this->src, $this->pos + 1, $close - ($this->pos + 1));
        if (preg_match('/^\\d+(,\\d*)?$/', $inner) !== 1) {
            return null;
        }
        $this->pos = $close + 1;
        return '{' . $inner . '}';
    }

    /**
     * Emulate an atomic group via lookahead-named-capture + backreference.
     * A generated name is used so the emulation capture never perturbs the
     * numbered-group sequence the engine and grammar backrefs rely on.
     */
    private function atomic(string $body): string
    {
        $this->atomicCount++;
        $name = 'atomic' . $this->atomicCount;
        return '(?=(?<' . $name . '>' . $body . '))\\k<' . $name . '>';
    }

    private function readName(string $terminator): string
    {
        $name = '';
        while ($this->pos < $this->len && $this->src[$this->pos] !== $terminator) {
            $name .= $this->src[$this->pos];
            $this->pos++;
        }
        return $name;
    }

    private function readDigitsTail(): string
    {
        $out = '';
        while ($this->pos < $this->len && ctype_digit($this->src[$this->pos])) {
            $out .= $this->src[$this->pos];
            $this->pos++;
        }
        return $out;
    }

    /**
     * Convert `\xHH`, `\x{H..}`, `\uHHHH`, `\u{H..}` to a JS escape. The cursor
     * sits just past the `x`/`u`. The brace form becomes `\u{...}` (JS `\x`
     * accepts only two fixed hex digits; only `\u{...}` takes the variable form).
     */
    private function convertHexEscape(string $kind): string
    {
        if ($this->pos < $this->len && $this->src[$this->pos] === '{') {
            $close = strpos($this->src, '}', $this->pos);
            if ($close !== false) {
                $inner = substr($this->src, $this->pos + 1, $close - ($this->pos + 1));
                $this->pos = $close + 1;
                return '\\u{' . trim($inner) . '}';
            }
        }
        $want = $kind === 'u' ? 4 : 2;
        $digits = '';
        while ($this->pos < $this->len && strlen($digits) < $want && ctype_xdigit($this->src[$this->pos])) {
            $digits .= $this->src[$this->pos];
            $this->pos++;
        }
        return '\\' . $kind . $digits;
    }

    private function expect(string $ch): void
    {
        if ($this->pos >= $this->len || $this->src[$this->pos] !== $ch) {
            throw ConversionFailed::unbalanced($this->src);
        }
        $this->pos++;
    }

    private function escapeLiteral(string $ch): string
    {
        if (preg_match('/[.*+?^${}()|[\\]\\\\\\/]/', $ch) === 1) {
            return '\\' . $ch;
        }
        return $ch;
    }

    private function escapeClassLiteral(string $ch): string
    {
        if ($ch === '\\' || $ch === ']' || $ch === '[' || $ch === '^') {
            return '\\' . $ch;
        }
        return $ch;
    }
}
