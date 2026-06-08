#!/usr/bin/env node
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { bundledLanguages, createHighlighter } from 'shiki';
import { createJavaScriptRegexEngine } from 'shiki/engine/javascript';

function usage() {
  process.stderr.write('usage: node oracle-tokens-themes.mjs <lang> <file> <themesJson>\n');
  process.exit(2);
}

const [lang, file, themesJson] = process.argv.slice(2);
if (!lang || !file || !themesJson) usage();

const code = readFileSync(file, 'utf8');
const themes = JSON.parse(themesJson);

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
  themes: Object.values(themes),
  engine: createJavaScriptRegexEngine(),
});

const lines = highlighter.codeToTokensWithThemes(code, { lang, themes });

const out = lines.map((line) =>
  line.map((token) => ({
    content: token.content,
    offset: token.offset,
    variants: Object.fromEntries(
      Object.entries(token.variants).map(([key, style]) => [
        key,
        {
          color: style.color ?? null,
          fontStyle: typeof style.fontStyle === 'number' ? style.fontStyle : 0,
          bgColor: style.bgColor ?? null,
        },
      ]),
    ),
  })),
);

process.stdout.write(JSON.stringify(out));
