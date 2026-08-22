-- جدول مقالات نون‌انیک
-- اگه از قبل جدول articles داری با ساختار متفاوت، این رو اجرا نکن؛
-- به‌جاش ساختار جدول فعلیت رو بفرست تا اسکریپت رو باهاش تطبیق بدم.

CREATE TABLE IF NOT EXISTS articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    content LONGTEXT NOT NULL,
    excerpt TEXT NULL,
    meta_description VARCHAR(320) NULL,
    featured_image VARCHAR(500) NULL,
    status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    source ENUM('manual', 'ai') NOT NULL DEFAULT 'manual',
    author VARCHAR(100) NOT NULL DEFAULT 'Nononick AI',
    github_source_file VARCHAR(255) NULL COMMENT 'مسیر فایل اصلی تو گیت‌هاب، برای جلوگیری از درج تکراری',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    published_at DATETIME NULL,
    INDEX idx_status (status),
    INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;
