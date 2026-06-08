from __future__ import annotations

import asyncio
from dataclasses import dataclass


@dataclass(frozen=True)
class Task:
    name: str
    priority: int = 0

    def label(self) -> str:
        return f"{self.name!r} (p{self.priority})"


async def run(tasks: list[Task]) -> dict[str, int]:
    results: dict[str, int] = {}
    for t in sorted(tasks, key=lambda x: -x.priority):
        await asyncio.sleep(0.01)
        results[t.name] = t.priority
    return results


if __name__ == "__main__":
    items = [Task("build", 2), Task("test", 5), Task("deploy")]
    out = asyncio.run(run(items))
    print("\n".join(f"{k}={v}" for k, v in out.items()))
