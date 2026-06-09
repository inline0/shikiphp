use std::collections::HashMap;

#[derive(Debug, Clone)]
struct Cache<K, V> {
    store: HashMap<K, V>,
}

impl<K: std::hash::Hash + Eq, V: Clone> Cache<K, V> {
    fn new() -> Self {
        Self { store: HashMap::new() }
    }

    fn get_or_insert(&mut self, key: K, f: impl FnOnce() -> V) -> V {
        self.store.entry(key).or_insert_with(f).clone()
    }
}

fn main() {
    let mut c = Cache::new();
    let v = c.get_or_insert("a", || vec![1, 2, 3]);
    println!("{:?}", v);
}
