# Base Class
class Smartphone:
    def __init__(self, brand, model, battery_life, storage):
        self.brand = brand
        self.model = model
        self.battery_life = battery_life  # in hours
        self.storage = storage  # in GB
        self.is_on = False

    def power_on(self):
        if not self.is_on:
            self.is_on = True
            print(f"{self.brand} {self.model} is now ON.")
        else:
            print(f"{self.brand} {self.model} is already ON.")

    def power_off(self):
        if self.is_on:
            self.is_on = False
            print(f"{self.brand} {self.model} is now OFF.")
        else:
            print(f"{self.brand} {self.model} is already OFF.")

    def install_app(self, app_name, size):
        if size <= self.storage:
            self.storage -= size
            print(f"Installed {app_name}. Remaining storage: {self.storage} GB.")
        else:
            print(f"Not enough storage to install {app_name}.")

    def __str__(self):
        return f"{self.brand} {self.model} | Battery: {self.battery_life} hrs | Storage: {self.storage} GB"

# Inherited Class (Specialized)
class GamingSmartphone(Smartphone):
    def __init__(self, brand, model, battery_life, storage, cooling_system):
        super().__init__(brand, model, battery_life, storage)
        self.cooling_system = cooling_system  # Boolean

    # Polymorphism: overriding method
    def install_app(self, app_name, size):
        if "Game" in app_name and self.cooling_system:
            print(f"Optimized installation for {app_name} with cooling system active!")
        super().install_app(app_name, size)

    def enable_game_mode(self):
        if self.is_on:
            print(f"{self.brand} {self.model} is now in Game Mode! 🎮")
        else:
            print("Please turn on the device before enabling Game Mode.")

# Example usage
phone1 = Smartphone("Samsung", "Galaxy S24", 24, 128)
phone2 = GamingSmartphone("Asus", "ROG Phone 7", 36, 256, True)

print(phone1)
phone1.power_on()
phone1.install_app("Instagram", 10)
print()

print(phone2)
phone2.power_on()
phone2.install_app("PUBG Game", 15)
phone2.enable_game_mode()
