import os
import requests
from urllib.parse import urlparse
from datetime import datetime

def fetch_image():
    # Prompt user for image URL
    url = input("Enter the image URL: ").strip()

    # Create directory if it doesn't exist
    folder = "Fetched_Images"
    os.makedirs(folder, exist_ok=True)

    try:
        # Fetch image from the web
        response = requests.get(url, timeout=10)  # 10-second timeout
        response.raise_for_status()  # Raise an error for HTTP issues

        # Extract filename from URL
        parsed_url = urlparse(url)
        filename = os.path.basename(parsed_url.path)

        # If no filename in URL, generate one
        if not filename:
            filename = f"image_{datetime.now().strftime('%Y%m%d_%H%M%S')}.jpg"

        # Full path to save
        filepath = os.path.join(folder, filename)

        # Save image in binary mode
        with open(filepath, "wb") as file:
            file.write(response.content)

        print(f"✅ Image successfully fetched and saved as: {filepath}")

    except requests.exceptions.MissingSchema:
        print("⚠️ Invalid URL. Please include http:// or https://")
    except requests.exceptions.Timeout:
        print("⏳ The request timed out. Try again later.")
    except requests.exceptions.HTTPError as e:
        print(f"❌ HTTP Error: {e}")
    except requests.exceptions.RequestException as e:
        print(f"⚠️ A network error occurred: {e}")
    except Exception as e:
        print(f"⚠️ Unexpected error: {e}")

if __name__ == "__main__":
    fetch_image()
