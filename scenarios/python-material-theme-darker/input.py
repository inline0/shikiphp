from dataclasses import dataclass
from typing import Iterator


@dataclass(frozen=True)
class Point:
    x: float
    y: float

    def __add__(self, other: "Point") -> "Point":
        return Point(self.x + other.x, self.y + other.y)


def walk(points: list[Point]) -> Iterator[float]:
    for a, b in zip(points, points[1:]):
        yield ((a.x - b.x) ** 2 + (a.y - b.y) ** 2) ** 0.5


pts = [Point(0, 0), Point(3, 4), Point(6, 8)]
print(f"distances: {list(walk(pts))!r}")
