(define (factorial n)
  (let loop ((acc 1) (k n))
    (if (= k 0)
        acc
        (loop (* acc k) (- k 1)))))

(define-syntax swap!
  (syntax-rules ()
    ((_ a b)
     (let ((tmp a))
       (set! a b)
       (set! b tmp)))))

(display (factorial 5))
(newline)
