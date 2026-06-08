import 'dart:math';

/// A simple 2D point.
class Point {
  final double x, y;
  const Point(this.x, this.y);

  double distanceTo(Point other) {
    final dx = x - other.x;
    final dy = y - other.y;
    return sqrt(dx * dx + dy * dy);
  }

  @override
  String toString() => 'Point($x, $y)';
}

void main() {
  const origin = Point(0, 0);
  final p = Point(3, 4);
  print('Distance: ${p.distanceTo(origin)}');

  final nums = [for (var i = 0; i < 5; i++) i * i];
  nums.where((n) => n.isEven).forEach(print);
}
