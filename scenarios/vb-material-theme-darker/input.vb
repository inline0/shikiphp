Imports System

Module Demo
    ' Compute factorial recursively
    Function Factorial(ByVal n As Integer) As Integer
        If n <= 1 Then
            Return 1
        End If
        Return n * Factorial(n - 1)
    End Function

    Sub Main()
        Dim name As String = "World"
        Dim numbers() As Integer = {1, 2, 3, 4, 5}
        Dim total As Integer = 0

        For Each n As Integer In numbers
            total += n
        Next

        Console.WriteLine($"Hello, {name}!")
        Console.WriteLine("5! = " & Factorial(5))
    End Sub
End Module
