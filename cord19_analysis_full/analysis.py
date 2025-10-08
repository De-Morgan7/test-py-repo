import pandas as pd
import matplotlib.pyplot as plt
from wordcloud import WordCloud

# Load dataset
df = pd.read_csv('data/metadata.csv')
print('✅ Data loaded successfully!')
print('Shape:', df.shape)
print(df.head())

# Clean and prepare data
df['publish_time'] = pd.to_datetime(df['publish_time'], errors='coerce')
df['year'] = df['publish_time'].dt.year
df['abstract_word_count'] = df['abstract'].fillna('').apply(lambda x: len(x.split()))

# Analysis
papers_per_year = df['year'].value_counts().sort_index()
top_journals = df['journal'].value_counts().head(10)

# Visualizations
plt.figure()
papers_per_year.plot(kind='bar', title='Papers per Year')
plt.xlabel('Year')
plt.ylabel('Number of Papers')
plt.tight_layout()
plt.savefig('papers_per_year.png')
plt.close()

plt.figure()
top_journals.plot(kind='bar', title='Top Journals')
plt.xlabel('Journal')
plt.ylabel('Number of Papers')
plt.tight_layout()
plt.savefig('top_journals.png')
plt.close()

text = ' '.join(df['title'].dropna())
wordcloud = WordCloud(width=800, height=400, background_color='white').generate(text)
wordcloud.to_file('wordcloud_titles.png')

print('✅ Analysis complete! Charts saved as images.')