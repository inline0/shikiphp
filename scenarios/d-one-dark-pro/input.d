import std.stdio;
import std.algorithm : map, sum;

struct Point {
    double x, y;
    double mag() const {
        return x * x + y * y;
    }
}

void main() {
    auto points = [Point(1, 2), Point(3, 4)];
    auto total = points.map!(p => p.mag).sum;
    writeln("total = ", total);
    foreach (i, p; points) {
        writefln("point %d: (%g, %g)", i, p.x, p.y);
    }
}
