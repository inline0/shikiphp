#!/usr/bin/env node
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { bundledLanguages, createHighlighter } from 'shiki';
import { createJavaScriptRegexEngine } from 'shiki/engine/javascript';

function usage() {
  process.stderr.write('usage: node oracle.mjs <lang> <theme> <file> [--tokens]\n');
  process.exit(2);
}

const args = process.argv.slice(2);
const tokensMode = args.includes('--tokens');
const positional = args.filter((a) => a !== '--tokens');
const [lang, theme, file] = positional;
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
  for (const dep of manifest.languages[id]?.embedded ?? []) {
    queue.push(dep);
  }
}

const langs = [...closure].filter((id) => id in bundledLanguages);

const highlighter = await createHighlighter({
  langs,
  themes: [theme],
  engine: createJavaScriptRegexEngine(),
});

if (tokensMode) {
  const result = highlighter.codeToTokens(code, { lang, theme });
  process.stdout.write(JSON.stringify(result.tokens));
} else {
  const html = highlighter.codeToHtml(code, { lang, theme });
  process.stdout.write(html);
}
