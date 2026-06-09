fn main() {
    let nested: Vec<Vec<Vec<Option<Result<i32, String>>>>> = vec![vec![vec![Some(Ok(1)), Some(Err("x".into()))]]];
    let deep = (((((1 + 2) * 3) - (4 / (2 + 2))) + ((5 * 5) - (3 * 3))) * 2);
    let closure = |a: i32| move |b: i32| move |c: i32| ((a + b) * c) - (a * (b + c));
    let result = closure(1)(2)(3);
    let map: std::collections::HashMap<String, Vec<(i32, [i32; 3])>> = Default::default();
    println!("{} {} {:?}", deep, result, map.get("x").map(|v| v.iter().map(|(n, arr)| n + arr[0]).sum::<i32>()));
}
