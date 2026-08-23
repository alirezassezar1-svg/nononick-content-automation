<?php
/**
 * config.php
 * این فایل رو کپی کن به config.php و مقادیر واقعی رو بذار توش.
 * config.php هرگز نباید به گیت‌هاب push بشه یا public باشه.
 * پیشنهاد: بذارش یه پوشه بالاتر از public_html یا با .htaccess قفلش کن.
 */

// --- دیتابیس ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');

// --- گیت‌هاب ---
// توکنی که فقط دسترسی خواندن (read-only) به همین یک ریپو داشته باشه کافیه.
// از GitHub > Settings > Developer settings > Fine-grained tokens بساز
// با Repository access = Only select repositories -> nononick-content-automation
// و Permissions -> Contents: Read-only
define('GITHUB_TOKEN', 'ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
define('GITHUB_OWNER', 'alirezassezar1-svg');
define('GITHUB_REPO', 'nononick-content-automation');
define('GITHUB_DRAFTS_PATH', 'drafts');

// --- Hugging Face (ساخت عکس با FLUX.1-schnell) ---
// از huggingface.co/settings/tokens یه توکن Read بساز
define('HUGGINGFACE_TOKEN', 'hf_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');

// --- امنیت endpoint (اختیاری، اگه بخوای دستی هم صداش بزنی) ---
define('PULL_SECRET_TOKEN', 'یک-رشته-تصادفی-طولانی-اینجا-بساز');
