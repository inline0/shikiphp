require "json"

module Greeter
  class Person
    attr_reader :name, :age

    def initialize(name:, age:)
      @name = name
      @age = age
    end

    def to_h
      { name: @name, age: @age }
    end
  end
end

people = [
  Greeter::Person.new(name: "Alice", age: 30),
  Greeter::Person.new(name: "Bob", age: 25),
]

total = people.sum(&:age)
puts people.map(&:to_h).to_json
puts "average age: #{total / people.size}"
