use std::collections::HashMap;

/// A point in 2D space.
#[derive(Debug, Clone)]
pub struct Point {
    x: f64,
    y: f64,
}

impl Point {
    pub fn new(x: f64, y: f64) -> Self {
        Point { x, y }
    }

    pub fn dist(&self, other: &Point) -> f64 {
        ((self.x - other.x).powi(2) + (self.y - other.y).powi(2)).sqrt()
    }
}

fn main() {
    let mut counts: HashMap<&str, u32> = HashMap::new();
    let raw = r#"hello "world" 0xFF_u8 1_000.5e-3"#;
    for word in raw.split_whitespace() {
        *counts.entry(word).or_insert(0) += 1;
    }
    println!("{:?} => {}", counts, '\n');
}
