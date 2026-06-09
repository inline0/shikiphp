(defn fib [n]
  (if (< n 2)
      n
      (+ (fib (- n 1)) (fib (- n 2)))))

(setv nums (list (range 10)))
(for [n nums]
  (print (.format "fib({}) = {}" n (fib n))))

(defclass Point []
  (defn __init__ [self x y]
    (setv self.x x)
    (setv self.y y)))
