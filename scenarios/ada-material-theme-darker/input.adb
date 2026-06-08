with Ada.Text_IO; use Ada.Text_IO;

procedure Main is
   type Counter is range 0 .. 100;

   function Square (X : Integer) return Integer is
   begin
      return X * X;
   end Square;

   N : Counter := 0;
begin
   for I in 1 .. 5 loop
      Put_Line ("Square:" & Integer'Image (Square (Integer (I))));
      N := N + 1;
   end loop;
end Main;
