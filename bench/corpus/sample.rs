use std::collections::HashMap;
use std::fmt;

const MAX_RETRIES: u32 = 5;

#[derive(Debug, Clone, PartialEq, Eq)]
pub enum Role {
    Admin,
    Member,
    Guest,
}

impl fmt::Display for Role {
    fn fmt(&self, f: &mut fmt::Formatter<'_>) -> fmt::Result {
        let s = match self {
            Role::Admin => "admin",
            Role::Member => "member",
            Role::Guest => "guest",
        };
        write!(f, "{s}")
    }
}

#[derive(Debug, Clone)]
pub struct User {
    pub id: u64,
    pub email: String,
    pub role: Role,
}

impl User {
    pub fn new(id: u64, email: impl Into<String>, role: Role) -> Self {
        Self {
            id,
            email: email.into(),
            role,
        }
    }

    pub fn describe(&self) -> String {
        format!("{} <{}>", self.email, self.role)
    }
}

#[derive(Default)]
pub struct UserStore {
    users: HashMap<u64, User>,
    seq: u64,
}

impl UserStore {
    pub fn add(&mut self, email: &str, role: Role) -> &User {
        self.seq += 1;
        let user = User::new(self.seq, email.to_lowercase(), role);
        self.users.entry(self.seq).or_insert(user)
    }

    pub fn find(&self, id: u64) -> Option<&User> {
        self.users.get(&id)
    }

    pub fn filter<F>(&self, pred: F) -> Vec<&User>
    where
        F: Fn(&&User) -> bool,
    {
        self.users.values().filter(pred).collect()
    }

    pub fn len(&self) -> usize {
        self.users.len()
    }
}

fn summarize<'a>(users: impl Iterator<Item = &'a User>) -> HashMap<String, usize> {
    let mut counts = HashMap::new();
    for user in users {
        *counts.entry(user.role.to_string()).or_insert(0) += 1;
    }
    counts
}

fn main() {
    let mut store = UserStore::default();
    store.add("a@example.com", Role::Admin);
    store.add("b@example.com", Role::Member);

    let admins: Vec<_> = store
        .filter(|u| u.role == Role::Admin)
        .iter()
        .map(|u| u.describe())
        .collect();

    println!("admins: {admins:?}");
    println!("summary: {:?}", summarize(store.filter(|_| true).into_iter().copied().collect::<Vec<_>>().iter().map(|u| u)));
    println!("total: {}", store.len());
}
