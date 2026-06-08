#!/usr/bin/env node
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { bundledLanguages, createHighlighter } from 'shiki';
import { createJavaScriptRegexEngine } from 'shiki/engine/javascript';

function usage() {
  process.stderr.write('usage: node oracle-options.mjs <lang> <theme> <file> <optionsJson>\n');
  process.exit(2);
}

const [lang, theme, file, optionsJson] = process.argv.slice(2);
if (!lang || !theme || !file) usage();

const code = readFileSync(file, 'utf8');
const extra = optionsJson ? JSON.parse(optionsJson) : {};

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

const themesOpt = extra.themes ? Object.values(extra.themes) : [theme];

const highlighter = await createHighlighter({
  langs,
  themes: themesOpt,
  engine: createJavaScriptRegexEngine(),
});

const opts = { lang, ...extra };
if (!extra.themes) opts.theme = theme;

try {
  const html = highlighter.codeToHtml(code, opts);
  process.stdout.write(html);
} catch (e) {
  process.stdout.write('ERROR: ' + e.message);
}
