pub struct Point {
  x : Int
  y : Int
}

pub fn Point::new(x : Int, y : Int) -> Point {
  { x, y }
}

pub fn distance(self : Point, other : Point) -> Double {
  let dx = (self.x - other.x).to_double()
  let dy = (self.y - other.y).to_double()
  (dx * dx + dy * dy).sqrt()
}

fn main {
  let p = Point::new(3, 4)
  println(p.distance(Point::new(0, 0)))
}
