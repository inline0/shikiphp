#include <iostream>
#include <vector>
#include <algorithm>

template <typename T>
class Stack {
    std::vector<T> data;
public:
    void push(const T& value) { data.push_back(value); }
    [[nodiscard]] bool empty() const noexcept { return data.empty(); }
    T pop() {
        T top = std::move(data.back());
        data.pop_back();
        return top;
    }
};

int main() {
    Stack<int> s;
    for (int i : {1, 2, 3}) s.push(i);
    while (!s.empty()) std::cout << s.pop() << ' ';
    std::cout << '\n';
}
