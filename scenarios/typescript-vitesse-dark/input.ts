interface User {
  id: number;
  name: string;
  roles: ReadonlyArray<'admin' | 'user'>;
}

type Result<T> = { ok: true; value: T } | { ok: false; error: string };

export function findUser(users: User[], id: number): Result<User> {
  const user = users.find((u) => u.id === id);
  if (!user) {
    return { ok: false, error: `no user ${id}` };
  }
  return { ok: true, value: user };
}

const admins = (users: User[]) => users.filter((u) => u.roles.includes('admin'));
