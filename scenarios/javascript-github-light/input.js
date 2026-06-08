import { readFile } from 'node:fs/promises';

const GREETING = 'hello, world';

export async function load(path) {
  const data = await readFile(path, 'utf8');
  return data
    .split('\n')
    .filter((line) => line.length > 0)
    .map((line, i) => `${i}: ${line}`);
}

class Counter {
  #count = 0;
  increment() {
    return ++this.#count;
  }
}
