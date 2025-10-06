# ===============================
# Assignment: Data Analysis & Visualization
# ===============================

# ---- Import Required Libraries ----
import pandas as pd
import matplotlib.pyplot as plt
import seaborn as sns
from sklearn.datasets import load_iris

# Make plots look nicer
sns.set(style="whitegrid")

# ===============================
# Task 1: Load and Explore Dataset
# ===============================
try:
    # Load the Iris dataset
    iris = load_iris(as_frame=True)
    df = iris.frame  # convert to DataFrame
    df["species"] = df["target"].map(dict(enumerate(iris.target_names)))  # add species names
    
    print("✅ Dataset loaded successfully!\n")
except FileNotFoundError:
    print("❌ Error: Dataset file not found.")
except Exception as e:
    print(f"⚠️ Unexpected error: {e}")

# Display first few rows
print("First 5 rows of the dataset:")
print(df.head(), "\n")

# Dataset info
print("Dataset Info:")
print(df.info(), "\n")

# Check for missing values
print("Missing Values:")
print(df.isnull().sum(), "\n")

# (No missing values in Iris, but if there were, we could drop/fill them)

# ===============================
# Task 2: Basic Data Analysis
# ===============================
print("Basic Statistics of Numerical Columns:")
print(df.describe(), "\n")

# Group by species and compute mean of numerical features
grouped = df.groupby("species").mean()
print("Average values grouped by species:")
print(grouped, "\n")

# Example finding
max_species = grouped["petal length (cm)"].idxmax()
print(f"📌 Finding: {max_species} has the largest average petal length.\n")

# ===============================
# Task 3: Data Visualization
# ===============================

# 1. Line chart (Sepal length across samples, just for trend visualization)
plt.figure(figsize=(8,5))
plt.plot(df.index, df["sepal length (cm)"], label="Sepal Length", color="blue")
plt.title("Line Chart: Sepal Length across Samples")
plt.xlabel("Sample Index")
plt.ylabel("Sepal Length (cm)")
plt.legend()
plt.show()

# 2. Bar chart: Average petal length per species
plt.figure(figsize=(8,5))
sns.barplot(x="species", y="petal length (cm)", data=df, ci=None, palette="Set2")
plt.title("Bar Chart: Average Petal Length by Species")
plt.xlabel("Species")
plt.ylabel("Average Petal Length (cm)")
plt.show()

# 3. Histogram: Distribution of Sepal Width
plt.figure(figsize=(8,5))
plt.hist(df["sepal width (cm)"], bins=15, color="skyblue", edgecolor="black")
plt.title("Histogram: Distribution of Sepal Width")
plt.xlabel("Sepal Width (cm)")
plt.ylabel("Frequency")
plt.show()

# 4. Scatter plot: Sepal length vs Petal length
plt.figure(figsize=(8,5))
sns.scatterplot(x="sepal length (cm)", y="petal length (cm)", hue="species", data=df, palette="Set1")
plt.title("Scatter Plot: Sepal Length vs Petal Length")
plt.xlabel("Sepal Length (cm)")
plt.ylabel("Petal Length (cm)")
plt.legend(title="Species")
plt.show()

# ===============================
# Task 4: Findings
# ===============================
print("🔎 Findings:")
print("- The dataset contains 150 rows, 4 numerical features, and 1 categorical target (species).")
print("- There are no missing values, so the dataset is clean.")
print("- Setosa species tends to have the smallest petal size, Virginica the largest.")
print("- Sepal width is normally distributed, centered around 3 cm.")
print("- There is a clear positive correlation between sepal length and petal length.")
