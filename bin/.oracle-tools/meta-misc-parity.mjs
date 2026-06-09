#!/usr/bin/env node
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { bundledLanguages, createHighlighter } from 'shiki';
import { createJavaScriptRegexEngine } from 'shiki/engine/javascript';
import {
  transformerMetaHighlight,
  transformerMetaWordHighlight,
  transformerRenderWhitespace,
  transformerCompactLineOptions,
  transformerRemoveNotationEscape,
} from '@shikijs/transformers';

const [kind, lang, theme, file, ...rest] = process.argv.slice(2);
if (!kind || !lang || !theme || !file) {
  process.stderr.write('usage: node meta-misc-parity.mjs <kind> <lang> <theme> <file> [extra]\n');
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

const highlighter = await createHighlighter({
  langs,
  themes: [theme],
  engine: createJavaScriptRegexEngine(),
});

let transformers = [];
let meta;
const opts = { lang, theme };

switch (kind) {
  case 'meta-highlight':
    transformers = [transformerMetaHighlight()];
    meta = { __raw: rest[0] ?? '' };
    break;
  case 'meta-word-highlight':
    transformers = [transformerMetaWordHighlight()];
    meta = { __raw: rest[0] ?? '' };
    break;
  case 'render-whitespace':
    transformers = [transformerRenderWhitespace({ position: rest[0] ?? 'all' })];
    break;
  case 'compact-line-options':
    transformers = [transformerCompactLineOptions(JSON.parse(rest[0] ?? '[]'))];
    break;
  case 'remove-notation-escape':
    transformers = [transformerRemoveNotationEscape()];
    break;
  default:
    process.stderr.write(`unknown kind: ${kind}\n`);
    process.exit(2);
}

opts.transformers = transformers;
if (meta) opts.meta = meta;

process.stdout.write(highlighter.codeToHtml(code, opts));
