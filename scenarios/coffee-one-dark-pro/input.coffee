# Greet a list of users
class Greeter
  constructor: (@name = "World") ->

  greet: ->
    "Hello, #{@name}!"

square = (x) -> x * x
numbers = [1, 2, 3, 4, 5]
squares = (square n for n in numbers)

greeter = new Greeter "Coffee"
console.log greeter.greet()

config =
  debug: yes
  retries: 3
  tags: ["a", "b"]

for n in numbers when n % 2 is 0
  console.log "even: #{n}"
