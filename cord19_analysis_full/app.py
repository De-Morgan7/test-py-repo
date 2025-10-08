import streamlit as st
import pandas as pd
import matplotlib.pyplot as plt
from wordcloud import WordCloud

st.title('CORD-19 Research Dashboard')
st.write('A simplified dashboard for exploring COVID-19 research papers.')

# Load dataset
df = pd.read_csv('data/metadata.csv')
df['publish_time'] = pd.to_datetime(df['publish_time'], errors='coerce')
df['year'] = df['publish_time'].dt.year

st.subheader('Sample of Data')
st.dataframe(df.head())

# Filter by year
years = sorted(df['year'].dropna().unique())
selected_year = st.selectbox('Select Year', options=years)
filtered_df = df[df['year'] == selected_year]

st.write(f'Showing papers from {selected_year}: {len(filtered_df)} records')

# Plot papers per year
papers_per_year = df['year'].value_counts().sort_index()
st.bar_chart(papers_per_year)

# Top journals
top_journals = df['journal'].value_counts().head(10)
st.bar_chart(top_journals)

# Word cloud
text = ' '.join(df['title'].dropna())
wordcloud = WordCloud(width=800, height=400, background_color='white').generate(text)

fig, ax = plt.subplots(figsize=(10, 5))
ax.imshow(wordcloud, interpolation='bilinear')
ax.axis('off')
st.pyplot(fig)