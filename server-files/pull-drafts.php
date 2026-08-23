<?php
/**
 * pull-drafts.php
 * -----------------------------------------------------------------
 * این اسکریپت رو یا با Cron Job رو سرور اجرا کن (هر ساعت مثلاً)،
 * یا دستی از مرورگر باز کن (با پارامتر ?token=PULL_SECRET_TOKEN).
 *
 * کارش: پوشه drafts/ رو تو ریپوی گیت‌هاب چک می‌کنه، فایل‌های JSON
 * جدید رو می‌خونه، به‌عنوان مقاله پیش‌نویس تو دیتابیس درج می‌کنه،
 * و بعد از درج موفق، فایل رو از گیت‌هاب پاک می‌کنه تا دوباره پردازش نشه.
 *
 * فرمت هر فایل JSON تو drafts/:
 * {
 *   "title": "عنوان مقاله",
 *   "slug": "an-optional-slug",   (اختیاری - اگه نباشه از عنوان ساخته می‌شه)
 *   "content": "<p>محتوای HTML مقاله...</p>",
 *   "excerpt": "خلاصه کوتاه",      (اختیاری)
 *   "meta_description": "..."      (اختیاری)
 * }
 * -----------------------------------------------------------------
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0'); // خطاها فقط تو لاگ، نه رو صفحه (امنیت)

$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    die('config.php پیدا نشد. اول config.example.php رو کپی کن.');
}
require_once $configPath;

// --- احراز هویت ساده برای اجرای دستی از مرورگر ---
// اگه از Cron اجرا می‌شه (CLI)، نیازی به توکن نیست.
if (php_sapi_name() !== 'cli') {
    $providedToken = $_GET['token'] ?? '';
    if (!hash_equals(PULL_SECRET_TOKEN, $providedToken)) {
        http_response_code(403);
        die('دسترسی غیرمجاز.');
    }
}

function logMsg(string $msg): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    echo $line;
    error_log($line, 3, __DIR__ . '/pull-drafts.log');
}

function githubRequest(string $url, string $method = 'GET', ?array $body = null)
{
    $ch = curl_init($url);
    $headers = [
        'Authorization: token ' . GITHUB_TOKEN,
        'Accept: application/vnd.github+json',
        'User-Agent: nononick-content-automation',
    ];
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException("GitHub request failed: $error");
    }
    return ['code' => $httpCode, 'body' => json_decode($response, true)];
}

/**
 * یه عکس با هوش مصنوعی از Pollinations.ai می‌سازه و دانلودش می‌کنه.
 * رایگان، بدون نیاز به API key.
 */
/**
 * یه عکس با مدل FLUX.1-schnell از طریق Hugging Face Inference API می‌سازه.
 * رایگان، فقط نیاز به یه توکن Hugging Face (hf_...) داره.
 */
function generateAndDownloadImage(string $prompt, string $slug): ?string
{
    $apiUrl = 'https://api-inference.huggingface.co/models/black-forest-labs/FLUX.1-schnell';

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . HUGGINGFACE_TOKEN,
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['inputs' => $prompt]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $imageData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    // مدل ممکنه بار اول "در حال بارگذاری" برگردونه (کد 503)؛ یه بار دیگه امتحان می‌کنیم
    if ($httpCode === 503) {
        sleep(15);
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . HUGGINGFACE_TOKEN,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['inputs' => $prompt]));
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        $imageData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
    }

    if ($imageData === false || $httpCode !== 200 || strpos((string) $contentType, 'image/') !== 0) {
        return null; // شکست خورد؛ مقاله بدون عکس ذخیره می‌شه
    }

    $uploadsDir = __DIR__ . '/../../uploads/articles'; // مسیر رو با ساختار واقعی سایتت تطبیق بده
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }

    $filename = $slug . '-' . time() . '.jpg';
    $fullPath = $uploadsDir . '/' . $filename;
    file_put_contents($fullPath, $imageData);

    return '/uploads/articles/' . $filename; // مسیر نسبی که تو دیتابیس ذخیره می‌شه
}

function slugify(string $text): string
{
    // اسلاگ ساده لاتین؛ اگه عنوان فارسیه و می‌خوای اسلاگ یونیکد بمونه،
    // این تابع رو با منطق فعلی سایتت هماهنگ کن.
    $text = trim($text);
    $text = preg_replace('/[^\p{L}\p{N}]+/u', '-', $text);
    $text = trim($text, '-');
    $text = mb_strtolower($text, 'UTF-8');
    if ($text === '') {
        $text = 'article-' . time();
    }
    return $text;
}

// --- اتصال به دیتابیس ---
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    logMsg('خطای اتصال دیتابیس: ' . $e->getMessage());
    exit(1);
}

// --- گرفتن لیست فایل‌های پوشه drafts از گیت‌هاب ---
$listUrl = sprintf(
    'https://api.github.com/repos/%s/%s/contents/%s',
    GITHUB_OWNER,
    GITHUB_REPO,
    GITHUB_DRAFTS_PATH
);

try {
    $result = githubRequest($listUrl);
} catch (RuntimeException $e) {
    logMsg('خطا در دریافت لیست فایل‌ها: ' . $e->getMessage());
    exit(1);
}

if ($result['code'] === 404) {
    logMsg('پوشه drafts پیدا نشد یا خالیه. کاری برای انجام نیست.');
    exit(0);
}

if ($result['code'] !== 200) {
    logMsg('خطای غیرمنتظره از گیت‌هاب: کد ' . $result['code']);
    exit(1);
}

$files = $result['body'];
if (!is_array($files) || count($files) === 0) {
    logMsg('هیچ فایل جدیدی تو drafts نیست.');
    exit(0);
}

$importedCount = 0;

foreach ($files as $file) {
    if (($file['type'] ?? '') !== 'file') {
        continue;
    }
    if (!str_ends_with($file['name'], '.json')) {
        continue;
    }

    $filePath = $file['path'];
    $fileSha = $file['sha'];

    logMsg("در حال پردازش: $filePath");

    // چک کن قبلاً import نشده باشه
    $checkStmt = $pdo->prepare('SELECT id FROM articles WHERE github_source_file = ? LIMIT 1');
    $checkStmt->execute([$filePath]);
    if ($checkStmt->fetch()) {
        logMsg("قبلاً import شده، رد می‌شیم: $filePath");
        continue;
    }

    // محتوای فایل رو بگیر (base64)
    try {
        $fileResult = githubRequest($file['url']);
    } catch (RuntimeException $e) {
        logMsg("خطا در خواندن فایل $filePath: " . $e->getMessage());
        continue;
    }

    if ($fileResult['code'] !== 200 || !isset($fileResult['body']['content'])) {
        logMsg("محتوای فایل $filePath قابل خواندن نبود.");
        continue;
    }

    $rawContent = base64_decode($fileResult['body']['content']);
    $article = json_decode($rawContent, true);

    if (!is_array($article) || empty($article['title']) || empty($article['content'])) {
        logMsg("فرمت فایل $filePath نامعتبره (title یا content نداره).");
        continue;
    }

    $title = trim($article['title']);
    $slug = !empty($article['slug']) ? slugify($article['slug']) : slugify($title);
    $content = $article['content'];
    $excerpt = $article['excerpt'] ?? null;
    $metaDescription = $article['meta_description'] ?? null;

    // --- عکس شاخص ---
    $featuredImage = null;
    if (!empty($article['image_prompt'])) {
        logMsg("در حال ساخت عکس برای: $title");
        $featuredImage = generateAndDownloadImage($article['image_prompt'], $slug);
        if ($featuredImage === null) {
            logMsg("ساخت عکس ناموفق بود، مقاله بدون عکس ذخیره می‌شه.");
        }
    } elseif (!empty($article['featured_image'])) {
        // اگه مستقیم یه URL عکس تو JSON بود، همون استفاده می‌شه
        $featuredImage = $article['featured_image'];
    }

    // اگه اسلاگ تکراریه، یه پسوند بهش اضافه کن
    $slugCheckStmt = $pdo->prepare('SELECT COUNT(*) FROM articles WHERE slug = ?');
    $originalSlug = $slug;
    $suffix = 1;
    do {
        $slugCheckStmt->execute([$slug]);
        $exists = (int) $slugCheckStmt->fetchColumn() > 0;
        if ($exists) {
            $suffix++;
            $slug = $originalSlug . '-' . $suffix;
        }
    } while ($exists);

    // درج تو دیتابیس به‌صورت پیش‌نویس
    try {
        $insertStmt = $pdo->prepare(
            'INSERT INTO articles (title, slug, content, excerpt, meta_description, featured_image, status, source, github_source_file)
             VALUES (?, ?, ?, ?, ?, ?, "draft", "ai", ?)'
        );
        $insertStmt->execute([$title, $slug, $content, $excerpt, $metaDescription, $featuredImage, $filePath]);
        logMsg("مقاله با موفقیت به‌عنوان پیش‌نویس ثبت شد: $title (slug: $slug)");
        $importedCount++;
    } catch (PDOException $e) {
        logMsg("خطا در درج مقاله $filePath: " . $e->getMessage());
        continue;
    }

    // فایل رو از گیت‌هاب پاک کن تا دوباره پردازش نشه
    try {
        $deleteUrl = sprintf(
            'https://api.github.com/repos/%s/%s/contents/%s',
            GITHUB_OWNER,
            GITHUB_REPO,
            $filePath
        );
        $deleteResult = githubRequest($deleteUrl, 'DELETE', [
            'message' => "Imported: $filePath",
            'sha' => $fileSha,
        ]);
        if ($deleteResult['code'] !== 200) {
            logMsg("هشدار: فایل $filePath درج شد ولی از گیت‌هاب پاک نشد (کد {$deleteResult['code']}). دستی پاکش کن.");
        }
    } catch (RuntimeException $e) {
        logMsg("هشدار: خطا در پاک کردن فایل از گیت‌هاب: " . $e->getMessage());
    }
}

logMsg("پایان اجرا. تعداد مقالات import شده: $importedCount");
