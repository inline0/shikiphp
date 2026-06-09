"""User service module for benchmarking the highlighter."""

from __future__ import annotations

import asyncio
import logging
from dataclasses import dataclass, field
from datetime import datetime, timezone
from enum import Enum
from typing import Iterable, Optional

logger = logging.getLogger(__name__)

MAX_RETRIES: int = 5
DEFAULT_ROLE = "member"


class Role(str, Enum):
    ADMIN = "admin"
    MEMBER = "member"
    GUEST = "guest"


@dataclass(slots=True)
class User:
    id: int
    email: str
    role: Role = Role.MEMBER
    created_at: datetime = field(default_factory=lambda: datetime.now(timezone.utc))
    meta: dict[str, object] = field(default_factory=dict)

    def describe(self) -> str:
        return f"{self.email} <{self.role.value}>"


class UserStore:
    def __init__(self) -> None:
        self._users: dict[int, User] = {}
        self._seq = 0

    def add(self, email: str, role: Role = Role.MEMBER) -> User:
        self._seq += 1
        user = User(id=self._seq, email=email.lower(), role=role)
        self._users[user.id] = user
        logger.info("added user %s", email)
        return user

    def find(self, user_id: int) -> Optional[User]:
        return self._users.get(user_id)

    def filter(self, predicate) -> list[User]:
        return [u for u in self._users.values() if predicate(u)]

    async def fetch_remote(self, user_id: int) -> User | None:
        for attempt in range(1, MAX_RETRIES + 1):
            try:
                await asyncio.sleep(0.01 * attempt)
                if user_id % 7 == 0:
                    raise ValueError("simulated failure")
                return User(id=user_id, email=f"user{user_id}@example.com")
            except ValueError as exc:
                logger.warning("attempt %d failed: %s", attempt, exc)
        return None

    @property
    def size(self) -> int:
        return len(self._users)


def summarize(users: Iterable[User]) -> dict[str, int]:
    counts: dict[str, int] = {}
    for user in users:
        counts[user.role.value] = counts.get(user.role.value, 0) + 1
    return counts


async def main() -> None:
    store = UserStore()
    store.add("a@example.com", Role.ADMIN)
    store.add("b@example.com")
    admins = store.filter(lambda u: u.role is Role.ADMIN)
    print([u.describe() for u in admins], summarize(store.filter(lambda _: True)))
    remote = await store.fetch_remote(42)
    print("remote:", remote)


if __name__ == "__main__":
    asyncio.run(main())
