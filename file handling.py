filename = input("Enter the filename: ")

try:
    # Open and read the file
    with open(filename, "r") as file:
        data = file.read()

    # Process the data
    word_count = len(data.split())
    modified_data = data.lower()

    # Write results to output.txt
    with open("output.txt", "w") as outfile:
        outfile.write("Processed Data:\n")
        outfile.write(modified_data + "\n")
        outfile.write(f"\nWord Count: {word_count}\n")

    print(" Successfully processed and saved to output.txt")

except FileNotFoundError:
    print(f" Error: The file '{filename}' was not found.")
except PermissionError:
    print(f" Error: Permission denied when trying to read '{filename}' or write to 'output.txt'.")
except Exception as e:
    print(f" An unexpected error occurred: {e}")
