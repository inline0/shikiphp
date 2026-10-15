#!/usr/bin/env node
// Regenerate only from the pinned upstream packages; never derive expected output from PHP.
import { writeFileSync } from 'node:fs';
import { createHighlighter } from 'shiki';
import { createJavaScriptRegexEngine } from 'shiki/engine/javascript';
import { codeToANSI } from '@shikijs/cli';
import * as transformers from '@shikijs/transformers';

const highlighter = await createHighlighter({
  langs: ['javascript', 'yaml', 'python', 'markdown'],
  themes: ['github-dark', 'horizon-bright', 'rose-pine', 'laserwave'],
  engine: createJavaScriptRegexEngine(),
});
const html = [];
function capture(code, lang, kind, options = {}, extra = {}, theme = 'github-dark') {
  const name = `transformer${kind}`;
  const output = highlighter.codeToHtml(code, {lang, theme, ...extra, transformers: [transformers[name](options)]});
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
const ansi = [];
for (const [code, lang, theme] of [
  ['const x = "hi" // comment\n', 'javascript', 'github-dark'],
  ['# hi\n\n**bold** _italic_', 'markdown', 'github-dark'],
  ['hello\r\nworld\n', 'text', 'horizon-bright'],
  ['# comment\nname: value', 'yaml', 'horizon-bright'],
  ['', 'text', 'github-dark'],
  ['a\u001b[39mb', 'text', 'github-dark'],
]) {
  for (let colorLevel = 0; colorLevel <= 3; colorLevel++) {
    ansi.push({code, lang, theme, colorLevel, output: await codeToANSI(code, lang, theme, {colorLevel})});
  }
}
const resumeOptions = {lang: 'javascript', theme: 'github-dark'};
const resume = {head: '/* start', tail: 'still a comment */ const x = 1'};
resume.output = highlighter.codeToHtml(resume.tail, {...resumeOptions, grammarState: highlighter.getLastGrammarState(resume.head, resumeOptions)});
const code = 'const x = "hi"; // comment\nconst re = /foo/;';
const tokenTypes = highlighter.codeToTokensBase(code, {lang: 'javascript', theme: 'github-dark', includeExplanation: 'tokenType'})
  .map(line => line.map(({content, type}) => ({content, type})));
writeFileSync(new URL('../../tests/Integration/fixtures/upstream-4.5.json', import.meta.url),
  JSON.stringify({version: '4.5.0', html, ansi, resume, tokenTypes: {code, output: tokenTypes}}, null, 2) + '\n');
console.log(`Captured ${html.length} HTML, ${ansi.length} ANSI and token-type cases from Shiki 4.5.0.`);
