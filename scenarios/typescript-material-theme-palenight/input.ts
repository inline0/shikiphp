type Result<T> = { ok: true; value: T } | { ok: false; error: string };

interface User {
  id: number;
  name: string;
  roles: readonly string[];
}

function parse<T>(raw: string, guard: (x: unknown) => x is T): Result<T> {
  try {
    const data = JSON.parse(raw) as unknown;
    return guard(data) ? { ok: true, value: data } : { ok: false, error: "bad" };
  } catch (e) {
    return { ok: false, error: String(e) };
  }
}

const isUser = (x: unknown): x is User =>
  typeof x === "object" && x !== null && "id" in x;
