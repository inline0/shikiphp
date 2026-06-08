# frozen_string_literal: true

require "json"

module Greetings
  GREETING = "Hello, %s!"

  class Person
    attr_reader :name, :age

    def initialize(name, age:)
      @name = name
      @age = age
    end

    def greet
      format(GREETING, name)
    end

    def to_h
      { name: name, age: @age }
    end
  end
end

people = [Greetings::Person.new("Ada", age: 36)]
people.each do |p|
  puts "#{p.greet} (#{p.age})"
  puts <<~INFO
    JSON: #{p.to_h.to_json}
  INFO
end
