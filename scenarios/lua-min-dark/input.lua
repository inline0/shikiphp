-- Simple stack module
local Stack = {}
Stack.__index = Stack

function Stack.new()
    return setmetatable({ items = {}, size = 0 }, Stack)
end

function Stack:push(value)
    self.size = self.size + 1
    self.items[self.size] = value
end

function Stack:pop()
    if self.size == 0 then
        error("stack is empty")
    end
    local v = self.items[self.size]
    self.items[self.size] = nil
    self.size = self.size - 1
    return v
end

local s = Stack.new()
for i = 1, 5 do
    s:push(i * 2)
end
print(string.format("top = %d, hex = 0xff", s:pop()))
print([[multi
line string]])
