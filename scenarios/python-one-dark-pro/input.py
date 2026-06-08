from dataclasses import dataclass


@dataclass
class Point:
    x: float
    y: float

    def distance(self, other: "Point") -> float:
        return ((self.x - other.x) ** 2 + (self.y - other.y) ** 2) ** 0.5


def main() -> None:
    points = [Point(i, i * 2) for i in range(5)]
    total = sum(p.distance(Point(0, 0)) for p in points)
    print(f"total distance = {total:.3f}")


if __name__ == "__main__":
    main()
