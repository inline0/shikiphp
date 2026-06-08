(defpackage :demo
  (:use :cl))
(in-package :demo)

(defstruct point x y)

(defun distance (a b)
  (sqrt (+ (expt (- (point-x a) (point-x b)) 2)
           (expt (- (point-y a) (point-y b)) 2))))

(let ((p1 (make-point :x 0 :y 0))
      (p2 (make-point :x 3 :y 4)))
  (format t "distance: ~a~%" (distance p1 p2)))
