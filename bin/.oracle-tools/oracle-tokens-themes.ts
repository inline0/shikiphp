#!/usr/bin/env node
import { main } from "../../scripts/lib/cli.ts";
import { oracleCommand } from "../../scripts/lib/shiki.ts";
export const command = oracleCommand("oracle-tokens-themes");
if (import.meta.main) main(command);
