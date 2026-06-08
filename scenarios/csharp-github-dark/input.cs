using System;
using System.Collections.Generic;
using System.Linq;

namespace Inventory;

public record Product(string Name, decimal Price);

public class Catalog
{
    private readonly List<Product> _items = new();

    public void Add(Product p) => _items.Add(p);

    public decimal TotalUnder(decimal max)
    {
        return _items
            .Where(p => p.Price <= max)
            .Sum(p => p.Price);
    }

    public static void Main()
    {
        var catalog = new Catalog();
        catalog.Add(new Product("Pen", 1.50m));
        catalog.Add(new Product("Desk", 199.99m));
        var name = "world";
        Console.WriteLine($"Hello, {name}! Total: {catalog.TotalUnder(50m):C}");
    }
}
