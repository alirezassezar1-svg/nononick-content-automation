import json
import os
import smtplib
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText
from pathlib import Path

files = sorted(Path('sent-content').glob('content-*.json'))
if not files:
    raise SystemExit('No generated content package found.')

content = json.loads(files[-1].read_text(encoding='utf-8'))

def section(title, value):
    if isinstance(value, dict):
        value = '\n'.join(f'{k}:\n{v}' for k, v in value.items())
    return f'\n===== {title} =====\n{value}\n'

body = (
    f"Nononick Content Package\nTheme: {content.get('theme')}\n"
    + section('WEBSITE ARTICLE', content.get('article', {}))
    + section('INSTAGRAM', content.get('instagram', {}))
    + section('YOUTUBE', content.get('youtube', {}))
    + section('IMAGE PROMPT', content.get('image_prompt', ''))
    + section('WEBSITE GENERATION PROMPT', content.get('website_prompt', ''))
)

msg = MIMEMultipart()
msg['From'] = os.environ['SMTP_USER']
msg['To'] = os.environ['CONTENT_EMAIL_TO']
msg['Subject'] = f"Nononick | محتوای جدید | {content.get('theme')}"
msg.attach(MIMEText(body, 'plain', 'utf-8'))

host = os.environ.get('SMTP_HOST', 'smtp.office365.com')
port = int(os.environ.get('SMTP_PORT', '587'))
with smtplib.SMTP(host, port, timeout=30) as server:
    server.starttls()
    server.login(os.environ['SMTP_USER'], os.environ['SMTP_PASSWORD'])
    server.sendmail(os.environ['SMTP_USER'], [os.environ['CONTENT_EMAIL_TO']], msg.as_string())

print('Email sent successfully.')
