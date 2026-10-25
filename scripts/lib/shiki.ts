import { fileURLToPath } from "node:url";
import * as Cli from "effect/cli/Command";
import * as Argument from "effect/cli/Argument";
import * as Flag from "effect/cli/Flag";
import * as Context from "effect/Context";
import * as Effect from "effect/Effect";
import * as FileSystem from "effect/FileSystem";
import * as Layer from "effect/Layer";
import type * as Scope from "effect/Scope";
import { bundledLanguages, createHighlighter, type Highlighter, type BundledLanguage, type BundledTheme, type ShikiTransformer, type CodeToHastOptions } from "shiki";
import { createJavaScriptRegexEngine } from "shiki/engine/javascript";
import * as transformers from "@shikijs/transformers";
import { attempt, fail, promise, type ScriptExit } from "./cli.ts";

export class Highlighters extends Context.Service<Highlighters, {
  readonly create: (langs: readonly string[], themes: readonly string[]) => Effect.Effect<Highlighter, ScriptExit, Scope.Scope>;
}>()("shikiphp/tooling/Highlighters") {}
export const highlightersLayer = Layer.succeed(Highlighters, {
  create: (langs, themes) => Effect.acquireRelease(
    promise(() => createHighlighter({ langs: [...langs] as BundledLanguage[], themes: [...themes] as BundledTheme[], engine: createJavaScriptRegexEngine() })),
    (highlighter) => Effect.sync(() => highlighter.dispose()),
  ),
});
export interface Manifest { readonly languages: Record<string, { readonly embedded?: readonly string[] }> }
export const languageClosure = (lang: string, manifest: Manifest): string[] => {
  const closure = new Set<string>();
  const queue = [lang];
  for (let index = 0; index < queue.length; index++) {
    const id = queue[index]!;
    if (closure.has(id)) continue;
    closure.add(id);
    queue.push(...(manifest.languages[id]?.embedded ?? []));
  }
  return [...closure].filter((id) => id in bundledLanguages);
};
export const manifestPath = fileURLToPath(new URL("../../src/Registry/manifest.json", import.meta.url));
export const oracleModes = ["oracle", "oracle-options", "oracle-tokens-themes", "notation-parity", "meta-misc-parity", "style-class-parity", "transformer-parity"] as const;
export type OracleMode = typeof oracleModes[number];
const notation = {
  highlight: transformers.transformerNotationHighlight,
  diff: transformers.transformerNotationDiff,
  focus: transformers.transformerNotationFocus,
  error: transformers.transformerNotationErrorLevel,
  word: transformers.transformerNotationWordHighlight,
};
const readJson = <A>(text: string) => attempt(() => JSON.parse(text) as A);
export const runOracle = (mode: OracleMode, args: readonly string[], tokens = false) => Effect.scoped(Effect.gen(function* () {
  const fs = yield* FileSystem.FileSystem;
  const factory = yield* Highlighters;
  const kindMode = ["notation-parity", "meta-misc-parity", "style-class-parity"].includes(mode);
  const [kind, lang, theme, file, ...rest] = kindMode
    ? mode === "style-class-parity" ? [args[0], args[1], "github-dark", args[2], ...args.slice(3)] : args
    : mode === "oracle-tokens-themes" ? ["", args[0], "", args[1], args[2]] : ["", ...args];
  if (!lang || !file || (mode === "oracle-tokens-themes" && !rest[0]) || (mode !== "oracle-tokens-themes" && !theme) || (kindMode && !kind)) {
    return yield* fail(`usage: node ${mode}.ts ${kindMode ? "<kind> " : ""}<lang> ${mode === "oracle-tokens-themes" ? "<file> <themesJson>" : mode === "style-class-parity" ? "<file> [theme|themesJson]" : "<theme> <file> [extra]"}`, 2);
  }
  let code = yield* fs.readFileString(file);
  if (mode === "meta-misc-parity" || mode === "style-class-parity") code = code.replace(/\n$/, "");
  const manifest = yield* readJson<Manifest>(yield* fs.readFileString(manifestPath));
  const langs = languageClosure(lang, manifest);
  const extra = mode === "oracle-options" ? yield* readJson<Record<string, unknown>>(rest[0] || "{}") : {};
  const themes = mode === "oracle-tokens-themes" || (mode === "style-class-parity" && kind === "style-to-class-dual")
    ? yield* readJson<Record<string, string>>(rest[0] || '{"light":"github-light","dark":"github-dark"}')
    : extra.themes as Record<string, string> | undefined;
  const selectedTheme = mode === "style-class-parity" && !themes ? rest[0] || "github-dark" : theme!;
  const highlighter = yield* factory.create(langs, themes ? Object.values(themes) : [selectedTheme]);
  const selected: ShikiTransformer[] = [];
  let meta: { __raw: string } | undefined;
  let style: ReturnType<typeof transformers.transformerStyleToClass> | undefined;
  if (mode === "notation-parity") {
    if (!(kind! in notation)) return yield* fail(`unknown kind: ${kind}`, 2);
    selected.push(notation[kind as keyof typeof notation]());
  }
  if (mode === "transformer-parity") selected.push({ name: "line-class", line(node, line) { this.addClassToHast(node, `line-${line}`); } });
  if (mode === "meta-misc-parity") {
    switch (kind) {
      case "meta-highlight": selected.push(transformers.transformerMetaHighlight()); meta = { __raw: rest[0] ?? "" }; break;
      case "meta-word-highlight": selected.push(transformers.transformerMetaWordHighlight()); meta = { __raw: rest[0] ?? "" }; break;
      case "render-whitespace": {
        const position = rest[0] ?? "all";
        if (position !== "all" && position !== "boundary" && position !== "trailing") return yield* fail(`unknown whitespace position: ${position}`, 2);
        selected.push(transformers.transformerRenderWhitespace({ position })); break;
      }
      case "compact-line-options": selected.push(transformers.transformerCompactLineOptions(yield* readJson<Parameters<typeof transformers.transformerCompactLineOptions>[0]>(rest[0] ?? "[]"))); break;
      case "remove-notation-escape": selected.push(transformers.transformerRemoveNotationEscape()); break;
      default: return yield* fail(`unknown kind: ${kind}`, 2);
    }
  }
  if (mode === "style-class-parity") {
    if (kind === "remove-line-break") selected.push(transformers.transformerRemoveLineBreak());
    else if (kind === "style-to-class-single" || kind === "style-to-class-dual") { style = transformers.transformerStyleToClass(); selected.push(style); }
    else return yield* fail(`unknown kind: ${kind}`, 2);
  }
  const options = { lang, ...(themes ? { themes } : { theme: selectedTheme }), ...extra, ...(selected.length ? { transformers: selected } : {}), ...(meta ? { meta } : {}) } as CodeToHastOptions;
  const output = yield* attempt(() => {
    if (mode === "oracle-tokens-themes") {
      const lines = highlighter.codeToTokensWithThemes(code, { lang: lang as BundledLanguage, themes: themes! as Record<string, BundledTheme> });
      return JSON.stringify(lines.map((line) => line.map((token) => ({ content: token.content, offset: token.offset, variants: Object.fromEntries(Object.entries(token.variants).map(([key, value]) => [key, { color: value.color ?? null, fontStyle: typeof value.fontStyle === "number" ? value.fontStyle : 0, bgColor: value.bgColor ?? null }])) }))));
    }
    if (tokens && mode === "oracle") return JSON.stringify(highlighter.codeToTokens(code, { lang: lang as BundledLanguage, theme: selectedTheme as BundledTheme }).tokens);
    try {
      const html = highlighter.codeToHtml(code, options);
      return style ? JSON.stringify({ html, css: style.getCSS() }) : html;
    } catch (cause) {
      if (mode === "oracle-options") return `ERROR: ${cause instanceof Error ? cause.message : String(cause)}`;
      throw cause;
    }
  });
  yield* Effect.sync(() => process.stdout.write(output));
}));
export const oracleCommand = (mode: OracleMode) => Cli.make(mode, {
  args: Argument.String("arguments").pipe(Argument.variadic()),
  tokens: Flag.Boolean("tokens").pipe(Flag.withDefault(false)),
}, ({ args, tokens }) => runOracle(mode, args, tokens).pipe(Effect.provide(highlightersLayer)))
  .pipe(Cli.withDescription("Generate the pinned Shiki reference output. Positional arguments match the PHP oracle callers."));
