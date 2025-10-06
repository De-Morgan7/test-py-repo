# Assignment: Data Analysis & Visualization with Pandas and Matplotlib

# ======================
# Task 0: Import Libraries
# ======================
import pandas as pd
import matplotlib.pyplot as plt
import seaborn as sns
from sklearn.datasets import load_iris

# Make plots look nicer
sns.set(style="whitegrid")

# ======================
# Task 1: Load and Explore Dataset
# ======================
try:
    # Load Iris dataset from sklearn
    iris = load_iris(as_frame=True)
    df = iris.frame  # Convert to Pandas DataFrame
    df['species'] = df['target'].map(dict(enumerate(iris.target_names)))  # Add species column
    
    print("✅ Dataset loaded successfully!\n")
except Exception as e:
    print(f"❌ Error loading dataset: {e}")

# Display first few rows
print("First 5 rows of dataset:")
print(df.head(), "\n")

# Dataset info
print("Dataset Info:")
print(df.info(), "\n")

# Check missing values
print("Missing Values:")
print(df.isnull().sum(), "\n")

# (No missing values in Iris, but if there were we could fill or drop them)
# df = df.dropna() or df.fillna(method="ffill")

# ======================
# Task 2: Basic Data Analysis
# ======================
print("Basic Statistics of Numerical Columns:")
print(df.describe(), "\n")

# Group by species and compute mean values
grouped = df.groupby("species").mean()
print("Average values grouped by species:")
print(grouped, "\n")

# Finding: which species has the largest average petal length?
max_species = grouped['petal length (cm)'].idxmax()
print(f"📌 Finding: The species with the largest average petal length is: {max_species}\n")

# ======================
# Task 3: Data Visualization
# ======================

# Line Chart: Sepal length over index (not time-series but for demo)
plt.figure(figsize=(8,5))
plt.plot(df.index, df['sepal length (cm)'], label="Sepal Length")
plt.title("Line Chart: Sepal Length over Samples")
plt.xlabel("Sample Index")
plt.ylabel("Sepal Length (cm)")
plt.legend()
plt.show()

# Bar Chart: Average petal length per species
plt.figure(figsize=(8,5))
sns.barplot(x="species", y="petal length (cm)", data=df, ci=None, palette="Set2")
plt.title("Bar Chart: Average Petal Length by Species")
plt.xlabel("Species")
plt.ylabel("Average Petal Length (cm)")
plt.show()

# Histogram: Distribution of Sepal Width
plt.figure(figsize=(8,5))
plt.hist(df['sepal width (cm)'], bins=15, color="skyblue", edgecolor="black")
plt.title("Histogram: Distribution of Sepal Width")
plt.xlabel("Sepal Width (cm)")
plt.ylabel("Frequency")
plt.show()

# Scatter Plot: Sepal length vs Petal length
plt.figure(figsize=(8,5))
sns.scatterplot(x="sepal length (cm)", y="petal length (cm)", hue="species", data=df, palette="Set1")
plt.title("Scatter Plot: Sepal Length vs Petal Length")
plt.xlabel("Sepal Length (cm)")
plt.ylabel("Petal Length (cm)")
plt.legend(title="Species")
plt.show()

# ======================
# Task 4: Findings
# ======================
print("🔎 Findings:")
print("- The dataset has 150 rows and no missing values.")
print("- Setosa species tends to have smaller petals, while Virginica has the largest.")
print("- There is a positive relationship between sepal length and petal length.")
print("- Sepal width is normally distributed with most values between 2.5 and 3.5 cm.")
