import assert from "node:assert/strict";
import { test } from "node:test";
import * as Effect from "effect/Effect";
import * as FileSystem from "effect/FileSystem";
import type { Highlighter } from "shiki";
import { Command, type CommandCall } from "../lib/command.ts";
import { Highlighters, languageClosure, runOracle } from "../lib/shiki.ts";
import { regression } from "../test-regression.ts";

test("embedded language closure terminates cycles and excludes unknown languages", () => {
  assert.deepEqual(languageClosure("javascript", { languages: { javascript: { embedded: ["typescript", "missing"] }, typescript: { embedded: ["javascript"] } } }), ["javascript", "typescript"]);
});

test("oracle releases its highlighter after a rendering failure", async () => {
  let released = false;
  let requested: readonly string[] = [];
  const highlighter = { codeToHtml: () => { throw new Error("render failed"); } } as unknown as Highlighter;
  const error = await Effect.runPromise(runOracle("oracle", ["javascript", "github-dark", "/input.ts"]).pipe(
    Effect.flip,
    Effect.provideService(FileSystem.FileSystem, FileSystem.makeNoop({ readFileString: (path) => Effect.succeed(path.endsWith("manifest.json") ? '{"languages":{"javascript":{}}}' : "const x=1") })),
    Effect.provideService(Highlighters, { create: (langs) => { requested = langs; return Effect.acquireRelease(Effect.succeed(highlighter), () => Effect.sync(() => { released = true; })); } }),
  ));
  assert.match(error.message, /render failed/);
  assert.deepEqual(requested, ["javascript"]);
  assert.equal(released, true);
});

test("oracle workers honor the job bound and preserve failed comparisons", async (t) => {
  const output: string[] = [];
  t.mock.method(console, "log", (line: string) => { output.push(line); });
  const calls: CommandCall[] = [];
  let active = 0;
  let maximum = 0;
  const error = await Effect.runPromise(regression({ filter: "", scenario: "", jobs: 2, fast: false }).pipe(
    Effect.flip,
    Effect.provideService(FileSystem.FileSystem, FileSystem.makeNoop({ exists: () => Effect.succeed(true) })),
    Effect.provideService(Command, { run: (call) => {
      calls.push(call);
      if (call.args?.includes("--list")) return Effect.succeed({ code: 0, stderr: "", stdout: '["one scenario","two","three"]' });
      return Effect.acquireUseRelease(
        Effect.sync(() => { active++; maximum = Math.max(maximum, active); }),
        () => Effect.sleep("10 millis").pipe(Effect.as({ code: 0, stderr: "", stdout: JSON.stringify({ name: call.args!.at(-1), pass: call.args!.at(-1) !== "two", diff: ["expected output differs"] }) })),
        () => Effect.sync(() => { active--; }),
      );
    } }),
  ));
  assert.equal(maximum, 2);
  assert.equal(active, 0);
  assert.equal(calls.length, 4);
  assert.ok(calls.some((call) => call.args?.at(-1) === "one scenario"));
  assert.ok(output.includes("FAIL  two"));
  assert.ok(output.includes("  expected output differs"));
  assert.match(error.message, /regression failed/);
});

test("serial matrix retains one PHP highlighter for the selected batch", async (t) => {
  t.mock.method(console, "log", () => {});
  const calls: CommandCall[] = [];
  await Effect.runPromise(regression({ filter: "", scenario: "", jobs: 1, fast: true }).pipe(
    Effect.provideService(FileSystem.FileSystem, FileSystem.makeNoop({ exists: () => Effect.succeed(true) })),
    Effect.provideService(Command, { run: (call) => {
      calls.push(call);
      return Effect.succeed({ code: 0, stderr: "", stdout: call.args?.includes("--list") ? '["one","two"]' : '[{"name":"one","pass":true,"diff":[]},{"name":"two","pass":true,"diff":[]}]' });
    } }),
  ));
  assert.equal(calls.length, 2);
  assert.deepEqual(calls[1]!.args!.slice(-3), ["--batch", "one", "two"]);
});
