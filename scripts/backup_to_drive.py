#!/usr/bin/env python3
"""
بکاپ خودکار فایل‌های drafts/ (و عکس‌های تولیدشده) به گوگل درایو.

طرز کار:
- این اسکریپت با یه سرویس‌اکانت گوگل (JSON) که تو سکرت
  GOOGLE_SERVICE_ACCOUNT_JSON نگه‌داری می‌شه، وارد میشه.
- فایل‌هایی که به عنوان آرگومان بهش داده میشه (مثلاً فایل‌های تازه
  push شده تو drafts/) رو تو یه فولدر مشخص از گوگل درایو آپلود می‌کنه.
- فولدر مقصد با شناسه GDRIVE_FOLDER_ID مشخص میشه (باید از قبل با
  ایمیل سرویس‌اکانت به اشتراک گذاشته بشه - پایین توضیح داده شده).

استفاده:
    python scripts/backup_to_drive.py drafts/article-2026-08-29.json [فایل‌های بیشتر...]

متغیرهای محیطی مورد نیاز:
    GOOGLE_SERVICE_ACCOUNT_JSON  -> کل محتوای JSON سرویس‌اکانت (نه مسیر فایل)
    GDRIVE_FOLDER_ID             -> شناسه فولدر مقصد تو گوگل درایو

⚠️ راه‌اندازی یک‌بار قبل از استفاده:
    1. تو Google Drive یه فولدر بساز (مثلاً "Nononick Content Backup").
    2. فولدر رو Share کن با ایمیل سرویس‌اکانت:
       nononick-drive-bot@creat-506915.iam.gserviceaccount.com
       (نقش: Editor کافیه)
    3. شناسه فولدر رو از URL درایو کپی کن (بعد از /folders/) و به‌عنوان
       سکرت GDRIVE_FOLDER_ID تو گیت‌هاب اضافه کن.
"""

import json
import os
import sys
from pathlib import Path

from google.oauth2 import service_account
from googleapiclient.discovery import build
from googleapiclient.http import MediaFileUpload

SCOPES = ["https://www.googleapis.com/auth/drive.file"]


def get_drive_service():
    raw_key = os.environ.get("GOOGLE_SERVICE_ACCOUNT_JSON")
    if not raw_key:
        sys.exit("خطا: متغیر محیطی GOOGLE_SERVICE_ACCOUNT_JSON ست نشده.")

    info = json.loads(raw_key)
    creds = service_account.Credentials.from_service_account_info(
        info, scopes=SCOPES
    )
    return build("drive", "v3", credentials=creds)


def upload_file(service, folder_id: str, file_path: Path):
    mime_map = {
        ".json": "application/json",
        ".png": "image/png",
        ".jpg": "image/jpeg",
        ".jpeg": "image/jpeg",
        ".webp": "image/webp",
    }
    mime_type = mime_map.get(file_path.suffix.lower(), "application/octet-stream")

    file_metadata = {
        "name": file_path.name,
        "parents": [folder_id],
    }
    media = MediaFileUpload(str(file_path), mimetype=mime_type, resumable=False)

    uploaded = (
        service.files()
        .create(body=file_metadata, media_body=media, fields="id, webViewLink")
        .execute()
    )
    print(f"✅ {file_path} -> {uploaded.get('webViewLink')}")


def main():
    if len(sys.argv) < 2:
        sys.exit("هیچ فایلی برای بکاپ داده نشده. مثال: backup_to_drive.py drafts/x.json")

    folder_id = os.environ.get("GDRIVE_FOLDER_ID")
    if not folder_id:
        sys.exit("خطا: متغیر محیطی GDRIVE_FOLDER_ID ست نشده.")

    service = get_drive_service()

    for arg in sys.argv[1:]:
        path = Path(arg)
        if not path.exists():
            print(f"⚠️ رد شد (وجود نداره، احتمالاً حذف شده): {path}")
            continue
        upload_file(service, folder_id, path)


if __name__ == "__main__":
    main()
