import std/strformat

type
  Animal = object
    name: string
    legs: int

proc describe(a: Animal): string =
  result = &"{a.name} has {a.legs} legs"

var animals = @[
  Animal(name: "dog", legs: 4),
  Animal(name: "bird", legs: 2),
]

for a in animals:
  echo describe(a)
