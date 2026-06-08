#!/usr/bin/env node
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { bundledLanguages, createHighlighter } from 'shiki';
import { createJavaScriptRegexEngine } from 'shiki/engine/javascript';

function usage() {
  process.stderr.write('usage: node transformer-parity.mjs <lang> <theme> <file>\n');
  process.exit(2);
}

const [lang, theme, file] = process.argv.slice(2);
if (!lang || !theme || !file) usage();

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

const addLineClass = {
  name: 'line-class',
  line(node, line) {
    this.addClassToHast(node, `line-${line}`);
  },
};

const html = highlighter.codeToHtml(code, { lang, theme, transformers: [addLineClass] });
process.stdout.write(html);
