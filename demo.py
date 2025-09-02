# Basic Calculator Program

# Ask the user to enter the first number
num1 = float(input("Enter the first number: "))

# Ask the user to enter the second number
num2 = float(input("Enter the second number: "))

# Ask the user to enter an operator (+, -, *, /)
operator = input("Enter an operator (+, -, *, /): ")

# Check which operator the user entered and perform that calculation
if operator == "+":
    result = num1 + num2
    print(num1, "+", num2, "=", result)

elif operator == "-":
    result = num1 - num2
    print(num1, "-", num2, "=", result)

elif operator == "*":
    result = num1 * num2
    print(num1, "*", num2, "=", result)

elif operator == "/":
    # Check to avoid division by zero
    if num2 != 0:
        result = num1 / num2
        print(num1, "/", num2, "=", result)
    else:
        print("Error: Cannot divide by zero")

else:
    print("Invalid operator. Please enter +, -, * or /")
