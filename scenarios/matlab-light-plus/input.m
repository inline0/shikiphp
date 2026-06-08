function result = fibonacci(n)
    % Compute first n Fibonacci numbers
    result = zeros(1, n);
    result(1) = 1;
    if n > 1
        result(2) = 1;
    end
    for k = 3:n
        result(k) = result(k-1) + result(k-2);
    end
end

x = linspace(0, 2*pi, 100);
y = sin(x) .* exp(-x/5);
plot(x, y, 'r-', 'LineWidth', 2);
title('Damped sine');
