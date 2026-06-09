#!/usr/bin/env node
import { createRequire } from 'node:module';
import { mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { grammars as grammarsIndex, injections as injectionsIndex } from 'tm-grammars';
import { themes as themesIndex } from 'tm-themes';

const require = createRequire(import.meta.url);
const here = dirname(fileURLToPath(import.meta.url));
const registryDir = resolve(here, '../../src/Registry');
const grammarsOutDir = resolve(registryDir, 'grammars');
const themesOutDir = resolve(registryDir, 'themes');

const shikiVersion = require('shiki/package.json').version;

const allGrammars = [...grammarsIndex, ...injectionsIndex];

const ALL_LANGS = allGrammars.map((g) => g.name);
const ALL_THEMES = themesIndex.map((t) => t.name);

const EXTRA_ALIASES = {
  js: 'javascript',
  ts: 'typescript',
  py: 'python',
  sh: 'bash',
  yml: 'yaml',
  'c++': 'cpp',
  'c#': 'csharp',
  md: 'markdown',
};

const byName = new Map(allGrammars.map((g) => [g.name, g]));
const byAlias = new Map();
for (const g of allGrammars) {
  for (const alias of g.aliases ?? []) byAlias.set(alias, g);
}

function resolveGrammar(id) {
  return byName.get(id) ?? byAlias.get(id);
}

const closure = new Map();

function addGrammarClosure(id) {
  const grammar = resolveGrammar(id);
  if (!grammar) throw new Error(`unknown grammar: ${id}`);
  if (closure.has(grammar.name)) return grammar;
  closure.set(grammar.name, grammar);
  for (const dep of grammar.embedded ?? []) addGrammarClosure(dep);
  return grammar;
}

const requestedGrammars = new Map();
for (const id of ALL_LANGS) {
  requestedGrammars.set(id, addGrammarClosure(id));
}

function loadGrammarJson(name) {
  const path = require.resolve(`tm-grammars/grammars/${name}.json`);
  return JSON.parse(readFileSync(path, 'utf8'));
}

function loadThemeJson(name) {
  const path = require.resolve(`tm-themes/themes/${name}.json`);
  return JSON.parse(readFileSync(path, 'utf8'));
}

rmSync(grammarsOutDir, { recursive: true, force: true });
rmSync(themesOutDir, { recursive: true, force: true });
mkdirSync(grammarsOutDir, { recursive: true });
mkdirSync(themesOutDir, { recursive: true });

const manifestLanguages = {};
const injectingScopes = new Set();

for (const [name, grammar] of closure) {
  const json = loadGrammarJson(name);
  if (typeof json.injectionSelector === 'string') {
    injectingScopes.add(grammar.scopeName);
  }
  writeFileSync(resolve(grammarsOutDir, `${name}.json`), JSON.stringify(json));
}

function manifestEntry(grammar) {
  const entry = {
    file: `grammars/${grammar.name}.json`,
    scopeName: grammar.scopeName,
    aliases: grammar.aliases ?? [],
    embedded: grammar.embedded ?? [],
  };
  if (injectingScopes.has(grammar.scopeName)) {
    entry.injects = true;
  }
  return entry;
}

for (const [, grammar] of closure) {
  manifestLanguages[grammar.name] = manifestEntry(grammar);
}

for (const [id, grammar] of requestedGrammars) {
  manifestLanguages[id] ??= manifestEntry(grammar);
}

for (const grammar of closure.values()) {
  for (const alias of grammar.aliases ?? []) {
    manifestLanguages[alias] ??= manifestEntry(grammar);
  }
}

for (const [alias, target] of Object.entries(EXTRA_ALIASES)) {
  const grammar = resolveGrammar(target);
  if (!grammar) throw new Error(`alias target missing: ${target}`);
  manifestLanguages[alias] ??= manifestEntry(grammar);
}

const manifestThemes = {};
const themeMeta = new Map(themesIndex.map((t) => [t.name, t]));

for (const id of ALL_THEMES) {
  const meta = themeMeta.get(id);
  if (!meta) throw new Error(`unknown theme: ${id}`);
  const json = loadThemeJson(id);
  writeFileSync(resolve(themesOutDir, `${id}.json`), JSON.stringify(json));
  manifestThemes[id] = {
    file: `themes/${id}.json`,
    displayName: meta.displayName ?? json.displayName ?? id,
    type: meta.type ?? json.type ?? 'dark',
  };
}

const manifest = {
  version: shikiVersion,
  languages: manifestLanguages,
  themes: manifestThemes,
};

writeFileSync(
  resolve(registryDir, 'manifest.json'),
  `${JSON.stringify(manifest, null, 2)}\n`,
);

const grammarFiles = closure.size;
const themeFiles = ALL_THEMES.length;
process.stdout.write(
  `bundled ${grammarFiles} grammars, ${themeFiles} themes ` +
  `(${Object.keys(manifestLanguages).length} language ids) ` +
  `against shiki ${shikiVersion}\n`,
);
