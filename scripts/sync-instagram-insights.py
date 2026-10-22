import json
import os
import sys
from datetime import datetime, timezone

try:
    import yt_dlp
except ImportError:
    print("yt_dlp not found, skipping live sync")
    sys.exit(0)

REELS = [
    {"id": "reel-1", "url": "https://www.instagram.com/reel/DePQdXiSOYb/"},
    {"id": "reel-2", "url": "https://www.instagram.com/reel/Dd6g1tusQ01/"},
    {"id": "reel-3", "url": "https://www.instagram.com/reel/DYpCQd2SFBT/"},
    {"id": "reel-4", "url": "https://www.instagram.com/reel/DYWBGpExvoW/"},
    {"id": "reel-5", "url": "https://www.instagram.com/reel/DdCOf4lSN8a/"},
    {"id": "reel-6", "url": "https://www.instagram.com/reel/DZR3Wn_yitM/"},
    {"id": "reel-7", "url": "https://www.instagram.com/reel/DZAZHbtyemr/"},
    {"id": "reel-8", "url": "https://www.instagram.com/reel/DY3tZ4_AdAN/"},
]

ydl_opts = {
    "extract_flat": False,
    "quiet": True,
    "skip_download": True,
    "no_warnings": True,
}

insights = {}

print("Fetching real-time Instagram insights...")
with yt_dlp.YoutubeDL(ydl_opts) as ydl:
    for item in REELS:
        rid = item["id"]
        url = item["url"]
        try:
            info = ydl.extract_info(url, download=False)
            insights[rid] = {
                "likes": info.get("like_count") or 0,
                "comments": info.get("comment_count") or 0,
                "uploader": info.get("uploader") or "ohmygudness.in",
                "handle": "@ohmygudness.in",
                "timestamp": info.get("timestamp"),
            }
            print(f"[{rid}] Likes: {insights[rid]['likes']}, Comments: {insights[rid]['comments']}")
        except Exception as e:
            print(f"[{rid}] Error fetching: {e}")

output_data = {
    "updatedAt": datetime.now(timezone.utc).isoformat(),
    "officialAccount": {
        "handle": "@ohmygudness.in",
        "url": "https://www.instagram.com/ohmygudness.in/",
        "title": "Oh My Gudness !! Bengaluru Gifts"
    },
    "insights": insights
}

out_dir = os.path.join(os.path.dirname(__file__), "..", "public", "data")
os.makedirs(out_dir, exist_ok=True)
out_file = os.path.join(out_dir, "instagramInsights.json")

with open(out_file, "w", encoding="utf-8") as f:
    json.dump(output_data, f, indent=2)

print(f"Successfully saved real-time insights to {out_file}")
