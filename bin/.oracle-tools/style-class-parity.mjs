#!/usr/bin/env node
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { bundledLanguages, createHighlighter } from 'shiki';
import { createJavaScriptRegexEngine } from 'shiki/engine/javascript';
import {
  transformerRemoveLineBreak,
  transformerStyleToClass,
} from '@shikijs/transformers';

const [kind, lang, file, ...rest] = process.argv.slice(2);
if (!kind || !lang || !file) {
  process.stderr.write('usage: node style-class-parity.mjs <kind> <lang> <file> [theme|themesJson]\n');
  process.exit(2);
}

const code = readFileSync(file, 'utf8').replace(/\n$/, '');

const manifestPath = join(dirname(fileURLToPath(import.meta.url)), '../../src/Registry/manifest.json');
const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
const closure = new Set();
const queue = [lang];
while (queue.length > 0) {
  const id = queue.shift();
  if (closure.has(id)) continue;
  closure.add(id);
  for (const dep of manifest.languages[id]?.embedded ?? []) queue.push(dep);
}
const langs = [...closure].filter((id) => id in bundledLanguages);

switch (kind) {
  case 'remove-line-break': {
    const theme = rest[0] ?? 'github-dark';
    const hl = await createHighlighter({ langs, themes: [theme], engine: createJavaScriptRegexEngine() });
    process.stdout.write(hl.codeToHtml(code, { lang, theme, transformers: [transformerRemoveLineBreak()] }));
    break;
  }
  case 'style-to-class-single': {
    const theme = rest[0] ?? 'github-dark';
    const hl = await createHighlighter({ langs, themes: [theme], engine: createJavaScriptRegexEngine() });
    const t = transformerStyleToClass();
    const html = hl.codeToHtml(code, { lang, theme, transformers: [t] });
    process.stdout.write(JSON.stringify({ html, css: t.getCSS() }));
    break;
  }
  case 'style-to-class-dual': {
    const themes = JSON.parse(rest[0] ?? '{"light":"github-light","dark":"github-dark"}');
    const hl = await createHighlighter({ langs, themes: Object.values(themes), engine: createJavaScriptRegexEngine() });
    const t = transformerStyleToClass();
    const html = hl.codeToHtml(code, { lang, themes, transformers: [t] });
    process.stdout.write(JSON.stringify({ html, css: t.getCSS() }));
    break;
  }
  default:
    process.stderr.write(`unknown kind: ${kind}\n`);
    process.exit(2);
}
