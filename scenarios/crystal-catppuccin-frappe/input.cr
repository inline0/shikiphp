require "json"

class Point
  include JSON::Serializable
  property x : Int32
  property y : Int32

  def initialize(@x, @y)
  end

  def distance : Float64
    Math.sqrt((x ** 2 + y ** 2).to_f)
  end
end

points = [Point.new(3, 4), Point.new(1, 1)]
points.each { |p| puts "#{p.x},#{p.y} => #{p.distance}" }
