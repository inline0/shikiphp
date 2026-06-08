-module(demo).
-export([factorial/1, greet/1]).

%% Compute factorial of N
-spec factorial(non_neg_integer()) -> pos_integer().
factorial(0) -> 1;
factorial(N) when N > 0 ->
    N * factorial(N - 1).

greet(Name) ->
    Greeting = "Hello, " ++ Name ++ "!",
    io:format("~s~n", [Greeting]).

sum_list(List) ->
    lists:foldl(fun(X, Acc) -> X + Acc end, 0, List).

main() ->
    Result = factorial(5),
    Map = #{name => <<"erlang">>, value => Result},
    io:format("~p~n", [Map]).
