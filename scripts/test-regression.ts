#!/usr/bin/env node
import { fileURLToPath } from "node:url";
import * as Cli from "effect/cli/Command";
import * as Flag from "effect/cli/Flag";
import * as Effect from "effect/Effect";
import * as FileSystem from "effect/FileSystem";
import { attempt, fail, main } from "./lib/cli.ts";
import { run } from "./lib/command.ts";
const root = fileURLToPath(new URL("..", import.meta.url));
const helper = fileURLToPath(new URL("../bin/.oracle-tools/run-scenario.php", import.meta.url));
interface Result { readonly name: string; readonly pass: boolean; readonly diff: readonly string[] }
export interface RegressionInput { readonly filter: string; readonly scenario: string; readonly jobs: number; readonly fast: boolean }
export const regression = (input: RegressionInput) => Effect.gen(function* () {
  const fs = yield* FileSystem.FileSystem;
  if (!(yield* fs.exists(`${root}/node_modules/shiki`))) return yield* fail("Oracle unavailable: install Node 26 and run `pnpm install --frozen-lockfile` in the repository root.", 2);
  const list = yield* run({ command: process.env.PHP_BINARY || "php", args: [helper, "--list"], cwd: root });
  const all = yield* attempt(() => JSON.parse(list.stdout) as string[]);
  const scenarios = all.filter((name) => input.scenario ? name === input.scenario : name.includes(input.filter));
  if (scenarios.length === 0) {
    yield* Effect.sync(() => console.log(`No scenarios found${input.filter ? ` matching '${input.filter}'` : ""}`));
    return;
  }
  const results = input.jobs <= 1
    ? yield* Effect.gen(function* () {
        const batch = yield* run({ command: process.env.PHP_BINARY || "php", args: ["-d", "memory_limit=512M", helper, "--batch", ...scenarios], cwd: root });
        return yield* attempt(() => JSON.parse(batch.stdout) as Result[]);
      })
    : yield* Effect.forEach(scenarios, (name) => Effect.gen(function* () {
    const result = yield* run({ command: process.env.PHP_BINARY || "php", args: ["-d", "memory_limit=512M", helper, name], cwd: root, check: false });
    if (result.code !== 0) return { name, pass: false, diff: [result.stderr.trim() || "failed to run worker"] } satisfies Result;
    return yield* attempt(() => JSON.parse(result.stdout) as Result);
  }), { concurrency: Math.max(1, input.jobs) });
  if (input.jobs > 1) results.sort((a, b) => a.name.localeCompare(b.name));
  const failed = results.filter((result) => !result.pass).length;
  yield* Effect.sync(() => {
    for (const result of results) {
      if (result.pass && !input.fast) console.log(`PASS  ${result.name}`);
      if (!result.pass) { console.log(`FAIL  ${result.name}`); if (!input.fast) for (const line of result.diff) console.log(`  ${line}`); }
    }
    console.log(`\nTotal:  ${results.length}\nPassed: ${results.length - failed}\nFailed: ${failed}`);
  });
  if (failed > 0) return yield* fail("Oracle regression failed.");
});
export const command = Cli.make("test-regression", {
  filter: Flag.String("filter").pipe(Flag.withDefault("")),
  scenario: Flag.String("scenario").pipe(Flag.withDefault("")),
  jobs: Flag.Int("jobs").pipe(Flag.withDefault(1)),
  fast: Flag.Boolean("fast").pipe(Flag.withDefault(false)),
}, regression).pipe(Cli.withDescription("Compare PHP highlighting against pinned Shiki with scoped, bounded workers."));
if (import.meta.main) main(command);
