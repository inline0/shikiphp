object Demo {
  // Recursive factorial
  def factorial(n: Int): Int =
    if (n <= 1) 1 else n * factorial(n - 1)

  sealed trait Shape
  case class Circle(r: Double) extends Shape
  case class Rect(w: Double, h: Double) extends Shape

  def area(s: Shape): Double = s match {
    case Circle(r)  => math.Pi * r * r
    case Rect(w, h) => w * h
  }

  def main(args: Array[String]): Unit = {
    val nums = List(1, 2, 3, 4, 5)
    val squares = nums.map(x => x * x)
    val name = "Scala"
    println(s"Hello, $name! sum=${squares.sum}")
  }
}
