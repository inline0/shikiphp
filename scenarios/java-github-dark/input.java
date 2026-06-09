import java.util.List;
import java.util.stream.Collectors;

public record Employee(String name, String dept, double salary) {

    public static void main(String[] args) {
        var employees = List.of(
            new Employee("Alice", "Eng", 95000),
            new Employee("Bob", "Eng", 85000),
            new Employee("Carol", "Sales", 70000)
        );

        var byDept = employees.stream()
            .collect(Collectors.groupingBy(Employee::dept,
                Collectors.averagingDouble(Employee::salary)));

        byDept.forEach((dept, avg) ->
            System.out.printf("%s: %.2f%n", dept, avg));
    }
}
