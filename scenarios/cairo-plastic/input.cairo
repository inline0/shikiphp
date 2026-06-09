#[starknet::contract]
mod Counter {
    #[storage]
    struct Storage {
        count: u128,
    }

    #[external(v0)]
    fn increment(ref self: ContractState) {
        let current = self.count.read();
        self.count.write(current + 1);
    }

    #[view]
    fn get(self: @ContractState) -> u128 {
        self.count.read()
    }
}
