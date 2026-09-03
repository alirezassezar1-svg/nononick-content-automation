import json
import os
from datetime import datetime, timezone
from pathlib import Path
from openai import OpenAI

index_file = Path('.content-index')
idx = int(index_file.read_text().strip()) if index_file.exists() else 1
themes = [
    'Futuristic', 'Luxury', 'Modern Minimal', 'Glassmorphism', '3D Interactive',
    'Cyberpunk', 'Neo-Brutalism', 'Aurora Gradient', 'Dark Premium', 'Editorial',
    'Swiss Minimal', 'Soft UI', 'Claymorphism', 'Liquid Metal', 'Immersive Parallax',
    'AI Native', 'Neon Tech', 'Organic Modern', 'Monochrome Luxury', 'Cinematic'
]
theme = themes[(idx - 1) % len(themes)]

prompt = f'''Create one unified Persian content package for Nononick.ir about the web design theme: {theme}.
Audience: Iranian business owners and people buying website design.
Do not use the word «حرفه‌ای» anywhere.
The package must contain:
1) Website article: 400-700 Persian words, SEO-oriented, with H1/H2/H3, practical teaching, CTA, meta title and meta description.
2) Instagram: Reel or carousel concept, hook, full script/text, caption, CTA, hashtags, plus an AI image/video generation prompt.
3) YouTube: clickable Persian title, opening hook, full video script outline, description, CTA, thumbnail prompt, and B-roll/video prompt.
4) Image-generation prompt for a striking visual demonstrating the theme.
5) Website-generation prompt in English for generating a complete sample responsive website using this theme, including typography, layout, animation, interactions, accessibility and mobile behavior.
Return strict JSON with keys: theme, article, instagram, youtube, image_prompt, website_prompt.
All prose except website_prompt and AI prompts should be Persian.'''

client = OpenAI(api_key=os.environ['OPENAI_API_KEY'])
res = client.chat.completions.create(
    model=os.environ.get('OPENAI_MODEL', 'gpt-5.6'),
    messages=[{'role': 'user', 'content': prompt}],
    temperature=0.8,
    response_format={'type': 'json_object'}
)
content = json.loads(res.choices[0].message.content)
content['generated_at_utc'] = datetime.now(timezone.utc).isoformat()
content['content_index'] = idx

Path('sent-content').mkdir(exist_ok=True)
Path('drafts').mkdir(exist_ok=True)
out = Path(f'sent-content/content-{datetime.now().strftime("%Y%m%d-%H%M%S")}.json')
out.write_text(json.dumps(content, ensure_ascii=False, indent=2), encoding='utf-8')
Path('drafts/latest.json').write_text(json.dumps({
    'title': content['article'].get('meta_title', f"{theme} Web Design"),
    'slug': f"{theme.lower().replace(' ', '-')}-web-design",
    'content': content['article'].get('body', ''),
    'excerpt': content['article'].get('excerpt', ''),
    'meta_description': content['article'].get('meta_description', '')
}, ensure_ascii=False, indent=2), encoding='utf-8')

index_file.write_text(str(idx + 1), encoding='utf-8')
