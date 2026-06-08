import Foundation

protocol Shape {
    var area: Double { get }
}

struct Circle: Shape {
    let radius: Double
    var area: Double { .pi * radius * radius }
}

enum Result<T> {
    case success(T)
    case failure(String)
}

func describe(_ shapes: [Shape]) -> String {
    let total = shapes.reduce(0.0) { $0 + $1.area }
    return "count=\(shapes.count), total=\(String(format: "%.2f", total))"
}

let shapes: [Shape] = [Circle(radius: 2.0), Circle(radius: 3.5)]
let outcome: Result<String> = .success(describe(shapes))

switch outcome {
case .success(let msg):
    print("OK: \(msg)")
case .failure(let err):
    print("ERR: \(err)")
}
