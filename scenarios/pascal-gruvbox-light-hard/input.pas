program Fibonacci;

var
  i, a, b, temp: Integer;

function Square(x: Integer): Integer;
begin
  Square := x * x;
end;

begin
  a := 0;
  b := 1;
  for i := 1 to 10 do
  begin
    WriteLn('fib(', i, ') = ', a, ' sq=', Square(a));
    temp := a + b;
    a := b;
    b := temp;
  end;
end.
