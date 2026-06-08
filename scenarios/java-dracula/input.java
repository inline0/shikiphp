package com.example.shop;

import java.util.List;
import java.util.stream.Collectors;

/** Immutable order record. */
public record Order(String id, List<Item> items) {

    public double total() {
        return items.stream()
            .mapToDouble(Item::price)
            .sum();
    }

    public static Order of(String id, Item... items) {
        return new Order(id, List.of(items));
    }
}

class Item {
    private final String name;
    private final double price;

    Item(String name, double price) {
        this.name = name;
        this.price = price;
    }

    double price() { return price; }

    @Override
    public String toString() {
        return String.format("%s=$%.2f", name, price);
    }
}
