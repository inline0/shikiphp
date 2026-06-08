module main

struct Point {
mut:
	x int
	y int
}

fn (p Point) magnitude() f64 {
	return math.sqrt(f64(p.x * p.x + p.y * p.y))
}

fn main() {
	mut points := []Point{}
	for i in 0 .. 3 {
		points << Point{x: i, y: i * 2}
	}
	for p in points {
		println('${p.x},${p.y}')
	}
}
