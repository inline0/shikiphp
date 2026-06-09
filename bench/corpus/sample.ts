import { EventEmitter } from "node:events";
import type { Logger } from "./logger";

export interface User {
  readonly id: number;
  email: string;
  role: "admin" | "member" | "guest";
  createdAt: Date;
  meta?: Record<string, unknown>;
}

export type Result<T, E = Error> =
  | { ok: true; value: T }
  | { ok: false; error: E };

const MAX_RETRIES = 5 as const;

export class UserStore extends EventEmitter {
  private readonly users = new Map<number, User>();
  private seq = 0;

  constructor(private readonly logger: Logger) {
    super();
  }

  add(email: string, role: User["role"] = "member"): User {
    const id = ++this.seq;
    const user: User = { id, email: email.toLowerCase(), role, createdAt: new Date() };
    this.users.set(id, user);
    this.emit("added", user);
    this.logger.info(`user added: ${email}`);
    return user;
  }

  find(id: number): User | undefined {
    return this.users.get(id);
  }

  async findRemote(id: number): Promise<Result<User>> {
    for (let attempt = 1; attempt <= MAX_RETRIES; attempt++) {
      try {
        const res = await fetch(`https://api.example.com/users/${id}`);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const value = (await res.json()) as User;
        return { ok: true, value };
      } catch (error) {
        this.logger.warn(`attempt ${attempt} failed`, error);
        await new Promise((r) => setTimeout(r, 2 ** attempt * 100));
      }
    }
    return { ok: false, error: new Error("exhausted retries") };
  }

  filter(predicate: (u: User) => boolean): User[] {
    return [...this.users.values()].filter(predicate);
  }

  get size(): number {
    return this.users.size;
  }
}

function assertNever(x: never): never {
  throw new Error(`Unexpected: ${JSON.stringify(x)}`);
}

export function describe(user: User): string {
  switch (user.role) {
    case "admin":
      return `${user.email} (admin)`;
    case "member":
      return `${user.email} (member)`;
    case "guest":
      return `${user.email} (guest)`;
    default:
      return assertNever(user.role);
  }
}

const store = new UserStore(console as unknown as Logger);
store.add("a@example.com", "admin");
store.add("b@example.com");
const admins = store.filter((u) => u.role === "admin").map(describe);
console.log(admins, store.size);
