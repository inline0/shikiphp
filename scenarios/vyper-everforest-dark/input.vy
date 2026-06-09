# @version ^0.3.0

owner: public(address)
balances: public(HashMap[address, uint256])

@external
def __init__():
    self.owner = msg.sender

@external
@payable
def deposit():
    self.balances[msg.sender] += msg.value

@external
def withdraw(amount: uint256):
    assert self.balances[msg.sender] >= amount, "insufficient"
    self.balances[msg.sender] -= amount
    send(msg.sender, amount)
