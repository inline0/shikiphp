module example::coin {
    use std::signer;

    struct Coin has key {
        value: u64,
    }

    public fun mint(account: &signer, amount: u64) {
        move_to(account, Coin { value: amount });
    }

    public fun balance(addr: address): u64 acquires Coin {
        borrow_global<Coin>(addr).value
    }
}
