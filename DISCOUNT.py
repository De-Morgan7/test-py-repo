def calculate_discounted_price():
    original_price = float(input("Enter the original price: "))
    discount_percentage = float(input("Enter the discount percentage: "))

    discount_amount = original_price * (discount_percentage / 100)
    final_price = original_price - discount_amount

    if discount_percentage >= 20:
        print("Discounted price is:", final_price)
    else:
        print("The price is:", original_price)


# call the function so it actually runs
calculate_discounted_price()
