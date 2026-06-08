// A small Groovy script
class Greeter {
    String name = 'World'

    String greet() {
        "Hello, ${name}!"
    }
}

def numbers = [1, 2, 3, 4, 5]
def squares = numbers.collect { it * it }
def sum = numbers.inject(0) { acc, n -> acc + n }

def config = [
    debug : true,
    retries: 3,
]

def g = new Greeter(name: 'Groovy')
println g.greet()
println "Sum=${sum}, squares=${squares}"

numbers.findAll { it % 2 == 0 }.each { println "even: $it" }
