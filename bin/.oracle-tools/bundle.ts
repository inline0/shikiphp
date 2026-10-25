#!/usr/bin/env node
import { createRequire } from 'node:module';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { grammars as grammarsIndex, injections as injectionsIndex } from 'tm-grammars';
import { themes as themesIndex } from 'tm-themes';

import * as Cli from "effect/cli/Command";
import * as Effect from "effect/Effect";
import * as FileSystem from "effect/FileSystem";
import { attempt, main } from "../../scripts/lib/cli.ts";

export const bundle = Effect.gen(function* () {
const fs = yield* FileSystem.FileSystem;
const require = createRequire(import.meta.url);
const here = dirname(fileURLToPath(import.meta.url));
const registryDir = resolve(here, '../../src/Registry');
const grammarsOutDir = resolve(registryDir, 'grammars');
const themesOutDir = resolve(registryDir, 'themes');

const shikiVersion = (yield* attempt(() => require('shiki/package.json') as { version: string })).version;

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
const byAlias = new Map<string, typeof allGrammars[number]>();
for (const g of allGrammars) {
  for (const alias of g.aliases ?? []) byAlias.set(alias, g);
}

function resolveGrammar(id: string) {
  return byName.get(id) ?? byAlias.get(id);
}

const closure = new Map<string, typeof allGrammars[number]>();

function addGrammarClosure(id: string): typeof allGrammars[number] {
  const grammar = resolveGrammar(id);
  if (!grammar) throw new Error(`unknown grammar: ${id}`);
  if (closure.has(grammar.name)) return grammar;
  closure.set(grammar.name, grammar);
  for (const dep of grammar.embedded ?? []) addGrammarClosure(dep);
  return grammar;
}

const requestedGrammars = new Map<string, typeof allGrammars[number]>();
for (const id of ALL_LANGS) {
  requestedGrammars.set(id, addGrammarClosure(id));
}

const loadGrammarJson = (name: string) => fs.readFileString(require.resolve(`tm-grammars/grammars/${name}.json`)).pipe(Effect.flatMap((text) => attempt(() => JSON.parse(text))));

const loadThemeJson = (name: string) => fs.readFileString(require.resolve(`tm-themes/themes/${name}.json`)).pipe(Effect.flatMap((text) => attempt(() => JSON.parse(text))));

yield* fs.remove(grammarsOutDir, { recursive: true, force: true });
yield* fs.remove(themesOutDir, { recursive: true, force: true });
yield* fs.makeDirectory(grammarsOutDir, { recursive: true });
yield* fs.makeDirectory(themesOutDir, { recursive: true });

const manifestLanguages: Record<string, ReturnType<typeof manifestEntry>> = {};
const injectingScopes = new Set<string>();

for (const [name, grammar] of closure) {
  const json = yield* loadGrammarJson(name);
  if (typeof json.injectionSelector === 'string') {
    injectingScopes.add(grammar.scopeName);
  }
  yield* fs.writeFileString(resolve(grammarsOutDir, `${name}.json`), JSON.stringify(json));
}

function manifestEntry(grammar: typeof allGrammars[number]) {
  const entry = {
    file: `grammars/${grammar.name}.json`,
    scopeName: grammar.scopeName,
    aliases: grammar.aliases ?? [],
    embedded: grammar.embedded ?? [],
    injects: undefined as true | undefined,
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

const manifestThemes: Record<string, { file: string; displayName: string; type: string }> = {};
const themeMeta = new Map(themesIndex.map((t) => [t.name, t]));

for (const id of ALL_THEMES) {
  const meta = themeMeta.get(id);
  if (!meta) throw new Error(`unknown theme: ${id}`);
  const json = yield* loadThemeJson(id);
  yield* fs.writeFileString(resolve(themesOutDir, `${id}.json`), JSON.stringify(json));
  manifestThemes[id] = {
    file: `themes/${id}.json`,
    displayName: meta.displayName ?? json.displayName ?? id,
    type: meta.type ?? json.type ?? 'dark',
  };
}

const manifest = {
  version: shikiVersion,
  upstreams: Object.fromEntries(yield* Effect.forEach([
    'shiki', '@shikijs/transformers', '@shikijs/cli', '@shikijs/vscode-textmate',
    'oniguruma-to-es', 'tm-grammars', 'tm-themes',
  ], (name) => Effect.gen(function* () {
    const text = yield* fs.readFileString(resolve(here, '../../node_modules', name, 'package.json'));
    return [name, (yield* attempt(() => JSON.parse(text) as { version: string })).version] as const;
  }))),
  languages: manifestLanguages,
  themes: manifestThemes,
};

yield* fs.writeFileString(
  resolve(registryDir, 'manifest.json'),
  `${JSON.stringify(manifest, null, 2)}\n`,
);

const grammarFiles = closure.size;
const themeFiles = ALL_THEMES.length;
yield* Effect.sync(() => process.stdout.write(
  `bundled ${grammarFiles} grammars, ${themeFiles} themes ` +
  `(${Object.keys(manifestLanguages).length} language ids) ` +
  `against shiki ${shikiVersion}\n`,
));
});
export const command = Cli.make("bundle", {}, () => bundle);
if (import.meta.main) main(command);
