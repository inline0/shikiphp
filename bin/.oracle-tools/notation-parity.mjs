#!/usr/bin/env node
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { bundledLanguages, createHighlighter } from 'shiki';
import { createJavaScriptRegexEngine } from 'shiki/engine/javascript';
import {
  transformerNotationHighlight,
  transformerNotationDiff,
  transformerNotationFocus,
  transformerNotationErrorLevel,
  transformerNotationWordHighlight,
} from '@shikijs/transformers';

const [kind, lang, theme, file] = process.argv.slice(2);
if (!kind || !lang || !theme || !file) {
  process.stderr.write('usage: node notation-parity.mjs <kind> <lang> <theme> <file>\n');
  process.exit(2);
}

const code = readFileSync(file, 'utf8');
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

const make = {
  highlight: transformerNotationHighlight,
  diff: transformerNotationDiff,
  focus: transformerNotationFocus,
  error: transformerNotationErrorLevel,
  word: transformerNotationWordHighlight,
};

const transformers = [make[kind]()];
const html = highlighter.codeToHtml(code, { lang, theme, transformers });
process.stdout.write(html);
