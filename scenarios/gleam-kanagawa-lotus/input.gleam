import gleam/io
import gleam/list
import gleam/int

pub type Shape {
  Circle(radius: Float)
  Square(side: Float)
}

pub fn area(shape: Shape) -> Float {
  case shape {
    Circle(r) -> 3.14159 *. r *. r
    Square(s) -> s *. s
  }
}

pub fn main() {
  [1, 2, 3]
  |> list.map(int.to_string)
  |> list.each(io.println)
}
