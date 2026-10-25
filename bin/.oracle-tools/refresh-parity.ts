#!/usr/bin/env node
// Regenerate only from the pinned upstream packages; never derive expected output from PHP.
import { fileURLToPath } from "node:url";
import * as Cli from "effect/cli/Command";
import * as Effect from "effect/Effect";
import * as FileSystem from "effect/FileSystem";
import { main, promise } from "../../scripts/lib/cli.ts";
import { Highlighters, highlightersLayer } from "../../scripts/lib/shiki.ts";
import type { ShikiTransformer, BundledLanguage, BundledTheme } from 'shiki';
import { codeToANSI } from '@shikijs/cli';
import * as transformers from '@shikijs/transformers';

export const refresh = Effect.scoped(Effect.gen(function* () {
const fs = yield* FileSystem.FileSystem;
const factory = yield* Highlighters;
const highlighter = yield* factory.create(['javascript', 'yaml', 'python', 'markdown'], ['github-dark', 'horizon-bright', 'rose-pine', 'laserwave']);
const html: Array<{ code: string; lang: string; theme: string; kind: string; options: object; extra: object; output: string }> = [];
function capture(code: string, lang: string, kind: string, options: object = {}, extra: object = {}, theme = 'github-dark') {
  const name = `transformer${kind}`;
  const output = highlighter.codeToHtml(code, {lang, theme, ...extra, transformers: [(transformers[name as keyof typeof transformers] as (options: object) => ShikiTransformer)(options)]});
  html.push({code, lang, theme, kind, options, extra, output});
}
for (const code of ['const a = 1\n\nconst b = 2\n', '', '😀\r\nconst a = 1']) {
  capture(code, 'javascript', 'RenderLineNumber');
  capture(code, 'javascript', 'RenderLineNumber', {start: 0, classLineNumber: 'gutter custom'});
  capture(code, 'javascript', 'RenderLineNumber', {}, {structure: 'inline'});
}
capture('// explanation [!code highlight]\nconst a = 1', 'javascript', 'NotationHighlight');
capture('// [!code highlight]\nconst a = 1', 'javascript', 'NotationHighlight');
capture('// explanation [!code ++]\nconst a = 1', 'javascript', 'NotationDiff');
capture('// explanation [!code focus]\nconst a = 1', 'javascript', 'NotationFocus');
capture('# [!code --]\nname: value', 'yaml', 'NotationDiff', {}, {}, 'rose-pine');
capture('# [!code highlight]\nprint(1)', 'python', 'NotationHighlight', {}, {}, 'rose-pine');
capture('const apple = 1 // [!code word:apple]\nconst b = apple', 'javascript', 'NotationWordHighlight', {classActivePre: 'has-words', classActiveCode: 'words'});
capture('// explanation [!code word:apple]\nconst apple = 1', 'javascript', 'NotationWordHighlight', {classActiveCode: 'words'});
capture('hello\nworld', 'text', 'RenderLineNumber', {}, {}, 'horizon-bright');
capture('hello\nworld', 'text', 'RenderLineNumber', {}, {}, 'laserwave');
const ansi: Array<{ code: string; lang: string; theme: string; colorLevel: number; output: string }> = [];
for (const [code, lang, theme] of [
  ['const x = "hi" // comment\n', 'javascript', 'github-dark'],
  ['# hi\n\n**bold** _italic_', 'markdown', 'github-dark'],
  ['hello\r\nworld\n', 'text', 'horizon-bright'],
  ['# comment\nname: value', 'yaml', 'horizon-bright'],
  ['', 'text', 'github-dark'],
  ['a\u001b[39mb', 'text', 'github-dark'],
]) {
  for (let colorLevel = 0; colorLevel <= 3; colorLevel++) {
    ansi.push({code: code!, lang: lang!, theme: theme!, colorLevel, output: yield* promise(() => codeToANSI(code!, lang as BundledLanguage, theme as BundledTheme, {colorLevel: colorLevel as 0 | 1 | 2 | 3}))});
  }
}
const resumeOptions = {lang: 'javascript', theme: 'github-dark'} as const;
const resume = {head: '/* start', tail: 'still a comment */ const x = 1', output: ''};
resume.output = highlighter.codeToHtml(resume.tail, {...resumeOptions, grammarState: highlighter.getLastGrammarState(resume.head, resumeOptions)});
const code = 'const x = "hi"; // comment\nconst re = /foo/;';
const tokenTypes = highlighter.codeToTokensBase(code, {lang: 'javascript', theme: 'github-dark', includeExplanation: 'tokenType'})
  .map(line => line.map(({content, type}) => ({content, type})));
yield* fs.writeFileString(fileURLToPath(new URL('../../tests/Integration/fixtures/upstream-4.5.json', import.meta.url)),
  JSON.stringify({version: '4.5.0', html, ansi, resume, tokenTypes: {code, output: tokenTypes}}, null, 2) + '\n');
yield* Effect.sync(() => console.log(`Captured ${html.length} HTML, ${ansi.length} ANSI and token-type cases from Shiki 4.5.0.`));
})).pipe(Effect.provide(highlightersLayer));
export const command = Cli.make("refresh-parity", {}, () => refresh);
if (import.meta.main) main(command);
