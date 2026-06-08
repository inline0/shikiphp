CREATE OR REPLACE PROCEDURE raise_salary (
    p_emp_id   IN NUMBER,
    p_percent  IN NUMBER
) AS
    v_salary NUMBER;
BEGIN
    SELECT salary INTO v_salary
    FROM employees
    WHERE employee_id = p_emp_id;

    UPDATE employees
    SET salary = v_salary * (1 + p_percent / 100)
    WHERE employee_id = p_emp_id;

    COMMIT;
EXCEPTION
    WHEN NO_DATA_FOUND THEN
        DBMS_OUTPUT.PUT_LINE('Employee not found');
END raise_salary;
