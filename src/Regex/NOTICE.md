# Vendored regex engine

The `Shikiphp\Regex` namespace is a pure-PHP, spec-faithful **ECMAScript
(JavaScript) regular-expression engine** — a parser (`Parser`) that builds a
RegExp AST and a tree-walking matcher (`Matcher`) that evaluates it with full
support for captures, backreferences, lookaround, capture-reset semantics, and
Unicode property escapes.

It is vendored from the [`phasis`](https://github.com/inline0/phasis) project
(a pure-PHP JavaScript engine, also by inline0) and re-namespaced from
`Phasis\Regex` to `Shikiphp\Regex`. The only behavioural change is that the
external `Phasis\Exceptions\SyntaxError` dependency is replaced by the local
{@see RegexSyntaxError}.

## Why a JavaScript regex engine?

Shiki highlights with TextMate grammars, whose patterns are written for the
**Oniguruma** regex engine. Modern Shiki's default engine
(`@shikijs/engine-javascript`) converts each Oniguruma pattern to a native
JavaScript `RegExp` using [`oniguruma-to-es`](https://github.com/slevithan/oniguruma-to-es),
then runs it with the JS regex engine.

shikiphp follows the same path: `Shikiphp\Oniguruma\PatternConverter` ports the
Oniguruma→JS conversion, and this vendored engine plays the role of the
JavaScript `RegExp` runtime. Operating in UTF-16 code-unit space matches both
the JS engine and Shiki's tokenizer exactly.

Do not edit files in this directory by hand; re-sync from upstream `phasis`
instead.
