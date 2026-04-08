
import requests
from bs4 import BeautifulSoup
import json
import logging
import re
import cloudscraper

# --- Configuration ---
CRICHD_BASE_URL = "https://vf.crichd.tv/"
OUTPUT_JSON_FILE = "matches.json"
USER_AGENT = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36"
REQUESTS_TIMEOUT = 10

# --- Logging Setup ---
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')

# --- Session Initialization ---
scraper = cloudscraper.create_scraper(
    browser={'browser': 'chrome', 'platform': 'windows', 'desktop': True}
)
scraper.headers.update({'User-Agent': USER_AGENT})

def get_page_content(url):
    """Fetches and parses content for a given URL."""
    try:
        response = scraper.get(url, timeout=REQUESTS_TIMEOUT)
        response.raise_for_status()
        return BeautifulSoup(response.text, 'html.parser')
    except requests.exceptions.RequestException as e:
        logging.error(f"Error fetching {url}: {e}")
        return None

def get_match_channels(match_url):
    """Extracts channel names from a match page."""
    logging.info(f"Fetching channels for: {match_url}")
    soup = get_page_content(match_url)
    if not soup:
        return []

    # Method 1: Find channels from the stream tabs
    channel_tabs = soup.select('#stream-tabs li a.video-btn')
    if channel_tabs:
        channels = [tab.text.strip() for tab in channel_tabs]
        logging.info(f"  -> Found channels via tabs: {channels}")
        return channels

    # Method 2: Fallback to finding channels from the script tag
    script_tag = soup.find('script', string=re.compile(r"var embeds = \[];"))
    if script_tag:
        titles_js = re.findall(r"titles\[\d+\]\s*=\s*'([^']+)';", script_tag.string)
        if titles_js:
            logging.info(f"  -> Found channels via script: {titles_js}")
            return titles_js
    
    logging.warning(f"  -> No channels found for {match_url}")
    return []

def get_all_matches():
    """Scrapes the main page to get all match details."""
    logging.info("--- Starting Match Scraping Process ---")
    soup = get_page_content(CRICHD_BASE_URL)
    if not soup:
        logging.critical("Failed to fetch the main page. Aborting.")
        return []

    matches_table = soup.find("table", class_="table-striped")
    if not matches_table:
        logging.critical("Could not find the matches table on the main page. Aborting.")
        return []

    all_matches = []
    for row in matches_table.find("tbody").find_all("tr"):
        cells = row.find_all("td")
        if len(cells) < 7:
            continue

        status_img = cells[6].find("img")['src']
        status = "Live" if "live.gif" in status_img else "Upcoming"
        title = cells[4].text.strip()
        competition = cells[3].text.strip()
        match_link_tag = cells[5].find("a")
        match_url = match_link_tag['href'] if match_link_tag else None

        if not match_url:
            logging.warning(f"Skipping match '{title}' due to missing link.")
            continue

        match_data = {
            "title": title,
            "competition": competition,
            "status": status,
            "match_url": match_url,
            "channels": get_match_channels(match_url)
        }
        all_matches.append(match_data)

    logging.info(f"--- Finished Scraping: Found {len(all_matches)} matches. ---")
    return all_matches

if __name__ == "__main__":
    matches = get_all_matches()
    if matches:
        try:
            with open(OUTPUT_JSON_FILE, 'w', encoding='utf-8') as f:
                json.dump(matches, f, indent=4)
            logging.info(f"Successfully wrote match data to {OUTPUT_JSON_FILE}")
        except Exception as e:
            logging.critical(f"Failed to write JSON file: {e}")
    else:
        logging.warning("No matches found or an error occurred during scraping.")
