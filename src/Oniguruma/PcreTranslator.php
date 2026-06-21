<?php

declare(strict_types=1);

namespace Shikiphp\Oniguruma;

/**
 * Translates the JS-RegExp source the PatternConverter emits into a native PCRE
 * pattern, but only for the subset whose match is provably identical to the
 * vendored Matcher. Anything outside that subset returns null and stays on the
 * VM. The bundled equivalence harness (bin/.oracle-tools/pcre-equivalence.php)
 * proves the classification holds against the Matcher for the real grammar
 * corpus — zero divergences.
 *
 * PCRE2 under /u diverges from ECMAScript in several places, so the translator
 * either rewrites the construct to its spec form (`\d\w\s`, `.`) or rejects the
 * whole pattern:
 *   - `\d \w \s` (+ negations): emitted as explicit ranges that mirror
 *     Regex\Ast\CharClass, because PCRE2 /u treats them Unicode-aware.
 *   - `.`: `[^\n\r\x{2028}\x{2029}]` (non-dotAll) — PCRE2 `.` only excludes \n.
 *   - `\b \B`: rejected — PCRE2 /u word boundary is Unicode-aware.
 *   - a capturing group reachable inside a quantified atom: rejected — ES resets
 *     the capture each iteration, PCRE keeps the last.
 *   - lookbehind containing a capture: rejected — ES captures right-to-left.
 *   - named groups / backreferences / `\p{}` / `\G` / atomic emulation: rejected.
 */
final class PcreTranslator
{
    /** PCRE class body for ECMAScript `\s` (mirrors Regex\Ast\CharClass::whitespace). */
    private const WS = '\\t\\n\\x0B\\f\\r \\x{A0}\\x{1680}\\x{2000}-\\x{200A}\\x{2028}\\x{2029}\\x{202F}\\x{205F}\\x{3000}\\x{FEFF}';

    /** PCRE class body for ECMAScript `\w`. */
    private const WORD = '0-9A-Za-z_';

    /** PCRE lookarounds for the exact ECMAScript (ASCII-`\w`) word boundary. */
    private const WORD_B = '(?:(?<=[0-9A-Za-z_])(?![0-9A-Za-z_])|(?<![0-9A-Za-z_])(?=[0-9A-Za-z_]))';
    private const WORD_NB = '(?:(?<=[0-9A-Za-z_])(?=[0-9A-Za-z_])|(?<![0-9A-Za-z_])(?![0-9A-Za-z_]))';

    private string $src = '';
    private int $pos = 0;
    private int $len = 0;
    private bool $safe = true;
    private bool $dotAll = false;
    private bool $positionMode = false;

    /**
     * Translate JS source+flags to a delimited PCRE pattern, or null if the
     * pattern is not provably equivalent to the Matcher.
     *
     * In `$positionMode` capture fidelity is NOT required — the caller only uses
     * the match position/extent and re-runs the VM Matcher there for true ES
     * captures — so capture-divergent constructs are admitted (quantified
     * captures, capture groups become non-capturing, capture-bearing lookbehind,
     * scoped-flag groups, atomic emulation as native `(?>…)`, `\p{…}` passed
     * through gated by PCRE compilation, `\b`/`\B` as exact ASCII lookarounds).
     * Extent-divergent constructs (backreferences, `\G`) remain rejected.
     *
     * @return array{pcre: string, anchored: bool}|null `anchored` is set for the
     *   sticky (`y`) flag: the caller runs with PREG_ANCHORED at the offset.
     */
    public function translate(string $jsSource, string $jsFlags, bool $positionMode = false): ?array
    {
        $this->src = $jsSource;
        $this->len = strlen($jsSource);
        $this->pos = 0;
        $this->safe = true;
        $this->dotAll = str_contains($jsFlags, 's');
        $this->positionMode = $positionMode;

        [$body] = $this->convert();
        if (!$this->safe || $this->pos !== $this->len) {
            return null;
        }

        $modifiers = 'u';
        if (str_contains($jsFlags, 'i')) {
            $modifiers .= 'i';
        }
        if (str_contains($jsFlags, 's')) {
            $modifiers .= 's';
        }
        // `m` is never emitted: the converter rewrites `^`/`$` to explicit
        // lookarounds, so PCRE multiline must stay OFF (a PCRE `$` would
        // otherwise match before a trailing newline). `u` is the base flag.

        if (str_contains($jsFlags, 'y')) {
            // Sticky: the `A` modifier (PCRE2_ANCHORED) ties the match to the
            // offset preg_match starts at, realising leading-`\G` stickiness.
            $modifiers .= 'A';
        }

        return ['pcre' => '/' . $body . '/' . $modifiers, 'anchored' => str_contains($jsFlags, 'y')];
    }

    /**
     * Convert a sequence up to a `)` or end. Returns [pcre, containsCapture]
     * where the flag is true if any capturing group is reachable in the
     * converted run (used to forbid quantifying a capture).
     *
     * @return array{0: string, 1: bool}
     *
     * @phpstan-impure
     */
    private function convert(): array
    {
        $out = '';
        $seqHasCapture = false;
        while ($this->pos < $this->len && $this->safe) {
            $ch = $this->src[$this->pos];

            if ($ch === ')') {
                break;
            }

            $atom = '';
            $atomHasCapture = false;

            if ($ch === '(') {
                [$atom, $atomHasCapture] = $this->convertGroup();
            } elseif ($ch === '[') {
                $atom = $this->convertClass();
            } elseif ($ch === '\\') {
                $atom = $this->convertEscape();
            } elseif ($ch === '|') {
                $out .= '|';
                $this->pos++;
                continue;
            } elseif ($ch === '.') {
                // Under the `s` flag a bare PCRE `.` matches any char (the
                // Matcher's dotAll `.`); without it, exclude JS line terminators.
                $this->pos++;
                $atom = $this->dotAll ? '.' : '[^\\n\\r\\x{2028}\\x{2029}]';
            } elseif ($ch === '^') {
                // The converter never emits `m`, so JS `^` (no-multiline) matches
                // only at input start — exactly PCRE `\A`. (It only appears inside
                // the converter's anchor lookarounds.)
                $this->pos++;
                $atom = '\\A';
            } elseif ($ch === '$') {
                // Likewise JS `$` (no-multiline) matches only at input end — PCRE
                // `\z` (absolute end; not `$`, which would match before a final \n).
                $this->pos++;
                $atom = '\\z';
            } elseif ($ch === '*' || $ch === '+' || $ch === '?' || $ch === '{') {
                // A quantifier with no preceding atom is malformed for us → VM.
                $this->safe = false;
                return [$out, $seqHasCapture];
            } elseif ($ch === '/') {
                // Escape the `/` pattern delimiter when it appears as a literal.
                $this->pos++;
                $atom = '\\/';
            } else {
                $atom = $ch;
                $this->pos++;
            }

            if (!$this->safe) {
                return [$out, $seqHasCapture];
            }

            $quant = $this->peekQuantifier();
            if ($quant !== '') {
                if ($atomHasCapture && !$this->positionMode) {
                    // Quantifying a capture diverges (ES resets per iteration);
                    // irrelevant in position mode, where captures are discarded.
                    $this->safe = false;
                    return [$out, $seqHasCapture];
                }
                $atom .= $this->copyQuantifier();
            }

            $out .= $atom;
            $seqHasCapture = $seqHasCapture || $atomHasCapture;
        }
        return [$out, $seqHasCapture];
    }

    /**
     * @return array{0: string, 1: bool} [pcre, containsCapture]
     *
     * @phpstan-impure
     */
    private function convertGroup(): array
    {
        $this->pos++; // (
        if ($this->pos >= $this->len) {
            $this->safe = false;
            return ['', false];
        }

        if ($this->src[$this->pos] !== '?') {
            [$body] = $this->convertParenBody();
            // Position mode discards captures, so a plain capture group becomes
            // non-capturing (cheaper, and immune to PCRE numbering concerns).
            return [$this->positionMode ? '(?:' . $body . ')' : '(' . $body . ')', true];
        }

        $this->pos++; // ?
        $c = $this->pos < $this->len ? $this->src[$this->pos] : '';

        if ($c === ':') {
            $this->pos++;
            [$body, $hasCapture] = $this->convertParenBody();
            return ['(?:' . $body . ')', $hasCapture];
        }

        if ($c === '=' || $c === '!') {
            if ($c === '=' && $this->positionMode && str_starts_with(substr($this->src, $this->pos + 1, 9), '(?<atomic')) {
                return [$this->convertAtomicEmulation(), false];
            }
            $this->pos++;
            [$body, $hasCapture] = $this->convertParenBody();
            // A capture inside a lookahead participates per ES; PCRE agrees for
            // lookahead (left-to-right). But quantifying the lookahead would
            // expose reset divergence, so propagate the capture flag.
            return ['(?' . $c . $body . ')', $hasCapture];
        }

        if ($c === '<') {
            $n = $this->src[$this->pos + 1] ?? '';
            if ($n === '=' || $n === '!') {
                $this->pos += 2;
                $rawStart = $this->pos;
                [$body, $hasCapture] = $this->convertParenBody();
                if ($hasCapture && !$this->positionMode) {
                    // ES lookbehind captures right-to-left; PCRE left-to-right.
                    // Extent-equal either way, so position mode admits it.
                    $this->safe = false;
                    return ['', false];
                }
                // PCRE2 rejects an unbounded-length lookbehind (`\s*`, `a+`,
                // `{n,}`); the converter emits these for some Oniguruma anchors.
                // And a lookahead that opens a lookbehind branch (consuming
                // nothing before it) is evaluated at a different anchor in PCRE
                // than in ES — the Matcher's `\n(?!…)` anchor branches keep the
                // lookahead after a consuming atom, which stays equivalent.
                // Either shape: route to the VM.
                $rawBody = substr($this->src, $rawStart, $this->pos - 1 - $rawStart);
                if (self::hasUnboundedQuantifier($rawBody) || self::hasLeadingLookaroundBranch($rawBody)) {
                    $this->safe = false;
                    return ['', false];
                }
                return ['(?<' . $n . $body . ')', false];
            }
            if ($this->positionMode && preg_match('/\\G\\(\\?<([A-Za-z_][A-Za-z0-9_]*)>/', $this->src, $m, 0, $this->pos - 2) === 1) {
                // Real named capture (atomic emulation was handled above): emit
                // non-capturing; a later `\k<name>` backref still rejects.
                $this->pos += strlen($m[1]) + 2;
                [$body] = $this->convertParenBody();
                return ['(?:' . $body . ')', true];
            }
            // Named group → VM (atomic emulation / real named captures carry
            // backref semantics routed to the Matcher).
            $this->safe = false;
            return ['', false];
        }

        if ($this->positionMode && preg_match('/\\G([is]*(?:-[is]+)?):/', $this->src, $m, 0, $this->pos) === 1) {
            // Scoped-flag group: PCRE shares the syntax and (under /u) the
            // simple-case-folding semantics; extent-equal, position mode only.
            $this->pos += strlen($m[0]);
            [$body, $hasCapture] = $this->convertParenBody();
            return ['(?' . $m[1] . ':' . $body . ')', $hasCapture];
        }

        // Inline-flag group `(?flags:…)` (strict mode), inline-comment group, or
        // anything else: route to VM.
        $this->safe = false;
        return ['', false];
    }

    /**
     * Translate the converter's atomic-group emulation `(?=(?<atomicN>X))\k<atomicN>`
     * into PCRE's native `(?>X)`. Cursor sits on the `=` of the lookahead.
     * Position mode only: the named-capture slot is discarded anyway.
     */
    private function convertAtomicEmulation(): string
    {
        if (preg_match('/\\G=\\(\\?<(atomic\\d+)>/', $this->src, $m, 0, $this->pos) !== 1) {
            $this->safe = false;
            return '';
        }
        $name = $m[1];
        $this->pos += strlen($m[0]);
        [$body] = $this->convertParenBody();
        // convertParenBody consumed the named group's `)`; the lookahead's `)`
        // and the backref must follow exactly as the converter emits them.
        $tail = ')\\k<' . $name . '>';
        if (!$this->safe || substr($this->src, $this->pos, strlen($tail)) !== $tail) {
            $this->safe = false;
            return '';
        }
        $this->pos += strlen($tail);
        return '(?>' . $body . ')';
    }

    /**
     * Convert a paren body and consume the closing `)`.
     *
     * @return array{0: string, 1: bool}
     */
    private function convertParenBody(): array
    {
        [$body, $hasCapture] = $this->convert();
        if (($this->src[$this->pos] ?? '') !== ')') {
            $this->safe = false;
            return [$body, $hasCapture];
        }
        $this->pos++; // )
        return [$body, $hasCapture];
    }

    /** @phpstan-impure */
    private function convertClass(): string
    {
        $out = '[';
        $this->pos++; // [
        if (($this->src[$this->pos] ?? '') === '^') {
            $out .= '^';
            $this->pos++;
        }
        if (($this->src[$this->pos] ?? '') === ']') {
            $out .= '\\]';
            $this->pos++;
        }
        while ($this->pos < $this->len && $this->src[$this->pos] !== ']') {
            $ch = $this->src[$this->pos];
            if ($ch === '\\') {
                $out .= $this->convertClassEscape();
                if (!$this->safe) {
                    return $out;
                }
                continue;
            }
            if ($ch === '[') {
                // JS char classes are flat (the converter lifts negated
                // subclasses out), so `[` is a literal member; PCRE agrees.
                $out .= '\\[';
                $this->pos++;
                continue;
            }
            if ($ch === '/') {
                // Escape the `/` pattern delimiter inside a class.
                $out .= '\\/';
                $this->pos++;
                continue;
            }
            $out .= $ch;
            $this->pos++;
        }
        if (($this->src[$this->pos] ?? '') !== ']') {
            $this->safe = false;
            return $out;
        }
        $this->pos++; // ]
        $out .= ']';
        return $out;
    }

    /** @phpstan-impure */
    private function convertClassEscape(): string
    {
        $this->pos++; // backslash
        if ($this->pos >= $this->len) {
            $this->safe = false;
            return '';
        }
        $c = $this->src[$this->pos];

        if ($c === 'd') {
            $this->pos++;
            return '0-9';
        }
        if ($c === 'w') {
            $this->pos++;
            return self::WORD;
        }
        if ($c === 's') {
            $this->pos++;
            return self::WS;
        }
        if (($c === 'p' || $c === 'P') && $this->positionMode) {
            return $this->copyUnicodeProperty();
        }
        if ($c === 'D' || $c === 'W' || $c === 'S' || $c === 'p' || $c === 'P') {
            // Negated shorthands and Unicode properties inside a class can't be
            // expressed as range members equivalently → VM.
            $this->safe = false;
            return '';
        }
        if ($c === 'b') {
            // \b inside a class is backspace (U+0008) in JS and PCRE alike.
            $this->pos++;
            return '\\x{08}';
        }
        if ($c === 'u') {
            return $this->convertUnicodeEscape();
        }
        if ($c === 'x') {
            $this->pos++;
            return $this->copyHexX();
        }
        if (in_array($c, ['n', 'r', 't', 'f', 'v', '0', '\\', ']', '[', '^', '-'], true)) {
            $this->pos++;
            return '\\' . $c;
        }
        $this->pos++;
        return '\\' . $c;
    }

    /** @phpstan-impure */
    private function convertEscape(): string
    {
        $this->pos++; // backslash
        if ($this->pos >= $this->len) {
            $this->safe = false;
            return '';
        }
        $c = $this->src[$this->pos];

        if ($c === 'd') {
            $this->pos++;
            return '[0-9]';
        }
        if ($c === 'D') {
            $this->pos++;
            return '[^0-9]';
        }
        if ($c === 'w') {
            $this->pos++;
            return '[' . self::WORD . ']';
        }
        if ($c === 'W') {
            $this->pos++;
            return '[^' . self::WORD . ']';
        }
        if ($c === 's') {
            $this->pos++;
            return '[' . self::WS . ']';
        }
        if ($c === 'S') {
            $this->pos++;
            return '[^' . self::WS . ']';
        }
        if ($c === 'b' && $this->positionMode) {
            // PHP's /u implies UCP, making PCRE `\b` Unicode-aware; ES `\b` is
            // ASCII-`\w`-based. Rewrite to exact lookarounds.
            $this->pos++;
            return self::WORD_B;
        }
        if ($c === 'B' && $this->positionMode) {
            $this->pos++;
            return self::WORD_NB;
        }
        if (($c === 'p' || $c === 'P') && $this->positionMode) {
            return $this->copyUnicodeProperty();
        }
        if ($c === 'G' && $this->positionMode) {
            // Scan-anchor. PCRE pins `\G` to the preg_match offset exactly as the
            // VM pins it to the scan start, so the prefilter's match positions
            // coincide with the VM's (no overshoot — see the equivalence harness).
            // Capture fidelity is not required here; the VM confirm, run with the
            // original scan start as its `\G` anchor, supplies the true captures.
            $this->pos++;
            return '\\G';
        }
        if ($c === 'b' || $c === 'B' || $c === 'p' || $c === 'P' || $c === 'G' || $c === 'k') {
            // Word boundary (Unicode-aware in PCRE2/u), Unicode property
            // (table skew), scan-anchor, named backref → VM.
            $this->safe = false;
            return '';
        }
        if (ctype_digit($c)) {
            // Numbered backreference — capture-reset divergence risk → VM.
            $this->safe = false;
            return '';
        }
        if ($c === 'u') {
            return $this->convertUnicodeEscape();
        }
        if ($c === 'x') {
            $this->pos++;
            return $this->copyHexX();
        }
        if (in_array($c, ['n', 'r', 't', 'f', 'v', '0'], true)) {
            $this->pos++;
            return '\\' . $c;
        }
        // Identity escape of a metacharacter / literal.
        $this->pos++;
        return '\\' . $c;
    }

    /** Convert `\uHHHH` or `\u{H..}` to PCRE `\x{...}`. Cursor sits on `u`. */
    private function convertUnicodeEscape(): string
    {
        $this->pos++; // u
        if (($this->src[$this->pos] ?? '') === '{') {
            $close = strpos($this->src, '}', $this->pos);
            if ($close === false) {
                $this->safe = false;
                return '';
            }
            $hex = substr($this->src, $this->pos + 1, $close - $this->pos - 1);
            if ($hex === '' || ctype_xdigit($hex) === false) {
                $this->safe = false;
                return '';
            }
            if ($this->isLoneSurrogate((int) hexdec($hex))) {
                $this->safe = false;
                return '';
            }
            $this->pos = $close + 1;
            return '\\x{' . $hex . '}';
        }
        $hex = '';
        while (strlen($hex) < 4 && $this->pos < $this->len && ctype_xdigit($this->src[$this->pos])) {
            $hex .= $this->src[$this->pos];
            $this->pos++;
        }
        if (strlen($hex) !== 4) {
            $this->safe = false;
            return '';
        }
        if ($this->isLoneSurrogate((int) hexdec($hex))) {
            $this->safe = false;
            return '';
        }
        return '\\x{' . $hex . '}';
    }

    private function isLoneSurrogate(int $cp): bool
    {
        return $cp >= 0xD800 && $cp <= 0xDFFF;
    }

    /**
     * Copy `\p{Name}` / `\P{Name}` through to PCRE (position mode). Bare names
     * only — PCRE2 shares the General_Category and Script names; `Name=Value`
     * forms and anything PCRE doesn't recognise are rejected (the latter by the
     * caller's compile check). Cursor sits on the `p`/`P`.
     */
    private function copyUnicodeProperty(): string
    {
        $c = $this->src[$this->pos];
        $this->pos++;
        if (preg_match('/\\G\\{([A-Za-z_]+)\\}/', $this->src, $m, 0, $this->pos) !== 1) {
            $this->safe = false;
            return '';
        }
        $this->pos += strlen($m[0]);
        return '\\' . $c . '{' . $m[1] . '}';
    }

    /**
     * True if the JS-regex body contains an unbounded quantifier (`*`, `+`, or an
     * open `{n,}` interval) outside a character class — the case PCRE2 refuses
     * inside a lookbehind.
     */
    private static function hasUnboundedQuantifier(string $body): bool
    {
        $len = strlen($body);
        $inClass = false;
        for ($i = 0; $i < $len; $i++) {
            $ch = $body[$i];
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
            if ($ch === '*' || $ch === '+') {
                return true;
            }
            if ($ch === '{') {
                $close = strpos($body, '}', $i);
                if ($close !== false) {
                    $inner = substr($body, $i + 1, $close - $i - 1);
                    if (preg_match('/^\d+,$/', $inner) === 1) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * True if any top-level alternative branch of a lookbehind body opens with a
     * lookahead `(?=`/`(?!` (consuming nothing before it). PCRE evaluates such a
     * branch at a different anchor than ES — only a lookahead that follows a
     * consuming atom within its branch stays equivalent.
     */
    private static function hasLeadingLookaroundBranch(string $body): bool
    {
        $len = strlen($body);
        $depth = 0;
        $inClass = false;
        $atBranchStart = true;
        for ($i = 0; $i < $len; $i++) {
            $ch = $body[$i];
            if ($ch === '\\') {
                $i++;
                $atBranchStart = false;
                continue;
            }
            if ($inClass) {
                if ($ch === ']') {
                    $inClass = false;
                }
                $atBranchStart = false;
                continue;
            }
            if ($ch === '[') {
                $inClass = true;
                $atBranchStart = false;
                continue;
            }
            if ($ch === '|' && $depth === 0) {
                $atBranchStart = true;
                continue;
            }
            if ($ch === '(') {
                $isLookahead = ($body[$i + 1] ?? '') === '?'
                    && in_array($body[$i + 2] ?? '', ['=', '!'], true);
                if ($atBranchStart && $isLookahead) {
                    return true;
                }
                $depth++;
                $atBranchStart = false;
                continue;
            }
            if ($ch === ')') {
                $depth--;
                $atBranchStart = false;
                continue;
            }
            $atBranchStart = false;
        }
        return false;
    }

    /** Copy a `\xHH` escape (cursor just past `x`) as PCRE `\x{HH}`. */
    private function copyHexX(): string
    {
        $hex = '';
        while (strlen($hex) < 2 && $this->pos < $this->len && ctype_xdigit($this->src[$this->pos])) {
            $hex .= $this->src[$this->pos];
            $this->pos++;
        }
        if ($hex === '') {
            // Bare `\x` is NUL in JS; reject the ambiguity → VM.
            $this->safe = false;
            return '';
        }
        return '\\x{' . $hex . '}';
    }

    /** Peek the quantifier token at the cursor without consuming. */
    private function peekQuantifier(): string
    {
        if ($this->pos >= $this->len) {
            return '';
        }
        $ch = $this->src[$this->pos];
        if ($ch === '*' || $ch === '+' || $ch === '?') {
            return $ch;
        }
        if ($ch === '{') {
            $close = strpos($this->src, '}', $this->pos);
            if ($close === false) {
                return '';
            }
            $inner = substr($this->src, $this->pos + 1, $close - $this->pos - 1);
            return preg_match('/^\d+(,\d*)?$/', $inner) === 1 ? '{' . $inner . '}' : '';
        }
        return '';
    }

    /** Copy a quantifier token (`*` `+` `?` `{n,m}`) plus a lazy/possessive suffix. */
    private function copyQuantifier(): string
    {
        $ch = $this->src[$this->pos];
        if ($ch === '{') {
            $close = strpos($this->src, '}', $this->pos);
            $out = substr($this->src, $this->pos, $close - $this->pos + 1);
            $this->pos = $close + 1;
        } else {
            $out = $ch;
            $this->pos++;
        }
        $next = $this->src[$this->pos] ?? '';
        if ($next === '?' || $next === '+') {
            $out .= $next;
            $this->pos++;
        }
        return $out;
    }
}
