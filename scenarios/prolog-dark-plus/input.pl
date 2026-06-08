% Family relationships
parent(tom, bob).
parent(bob, ann).
parent(bob, pat).

grandparent(X, Z) :-
    parent(X, Y),
    parent(Y, Z).

ancestor(X, Y) :- parent(X, Y).
ancestor(X, Y) :-
    parent(X, Z),
    ancestor(Z, Y).

count([], 0).
count([_|T], N) :-
    count(T, N0),
    N is N0 + 1.
