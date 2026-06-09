using System;
using System.Linq;
using System.Collections.Generic;

namespace Demo;

public record Product(string Name, decimal Price);

public class Catalog
{
    private readonly List<Product> _items = new();

    public void Add(Product p) => _items.Add(p);

    public decimal Total => _items.Sum(p => p.Price);

    public IEnumerable<string> Expensive(decimal min) =>
        _items.Where(p => p.Price > min)
              .Select(p => $"{p.Name}: {p.Price:C}");
}
