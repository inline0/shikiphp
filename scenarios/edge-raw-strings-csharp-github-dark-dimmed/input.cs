using System;

class Demo
{
    static void Main()
    {
        var json = """
            {
                "name": "test",
                "path": "C:\Users\app"
            }
            """;

        var name = "world";
        var interp = $"""
            Hello {name}!
            The "quotes" stay literal.
            """;

        var verbatim = @"C:\path\no\escapes";
        Console.WriteLine(json + interp + verbatim);
    }
}
