#include <iostream>
#include <vector>
#include <memory>

namespace geo {

template <typename T>
class Vec {
public:
    explicit Vec(std::initializer_list<T> xs) : data_(xs) {}

    T sum() const noexcept {
        T acc{};
        for (const auto& x : data_) acc += x;
        return acc;
    }

private:
    std::vector<T> data_;
};

} // namespace geo

int main() {
    auto v = std::make_unique<geo::Vec<double>>(std::initializer_list<double>{1.5, 2.0, 3.25});
    constexpr int flags = 0b1010;
    std::cout << "sum=" << v->sum() << " flags=" << flags << '\n';
    return 0;
}
