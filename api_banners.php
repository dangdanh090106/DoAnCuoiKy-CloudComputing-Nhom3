<?php
declare(strict_types=1);

/**
 * REST API for banners in do_an_cuoi_ky.
 * GET /api_banners.php or ?id=... : public read
 * GET /api_banners.php?action=csrf : CSRF token for logged-in managers
 * POST /api_banners.php : create (JSON with existing image_url, or multipart with banner_image)
 * PUT /api_banners.php : update title (JSON: {id,title})
 * DELETE /api_banners.php?id=... : delete banner, clean up unreferenced local file
 * All writes require an authenticated admin/event manager plus X-CSRF-Token.
 * Does not alter the existing user/banners.php page.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Allow: GET, POST, PUT, DELETE');
// Session-based write API: intentionally no Access-Control-Allow-Origin: *.

require_once __DIR__ . '/database.php';
$db = $pdo ?? $conn ?? null;

function bannerReply(int $httpCode, array $payload): never
{
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function bannerRequireManager(): void
{
    if (empty($_SESSION['user_id']) || empty($_SESSION['vai_tro'])) {
        bannerReply(401, ['status' => 'error', 'message' => 'Bạn chưa đăng nhập.']);
    }
    if (!in_array($_SESSION['vai_tro'], ['admin', 'quan_ly_su_kien'], true)) {
        bannerReply(403, ['status' => 'error', 'message' => 'Bạn không có quyền quản lý Banner.']);
    }
}

function bannerCsrfToken(): string
{
    // Keep the same token as api_su_kien.php and api_dia_diem.php.
    if (empty($_SESSION['event_csrf_token'])) {
        $_SESSION['event_csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['event_csrf_token'];
}

function bannerRequireCsrf(array $body = []): void
{
    $supplied = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($body['csrf_token'] ?? '');
    if (!is_string($supplied) || $supplied === '' || !hash_equals(bannerCsrfToken(), $supplied)) {
        bannerReply(403, ['status' => 'error', 'message' => 'CSRF token không hợp lệ.']);
    }
}

function bannerId(mixed $value): ?int
{
    if (!is_int($value) && !is_string($value)) return null;
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
    return $id === false ? null : (int) $id;
}

function bannerJson(): array
{
    $raw = file_get_contents('php://input');
    $body = json_decode($raw === false ? '' : $raw, true);
    if (!is_array($body) || array_is_list($body)) {
        bannerReply(400, ['status' => 'error', 'message' => 'Body phải là một đối tượng JSON hợp lệ.']);
    }
    return $body;
}

function bannerTitle(mixed $value): string
{
    if (!is_string($value)) {
        bannerReply(400, ['status' => 'error', 'message' => 'Tiêu đề Banner phải là chuỗi.']);
    }
    $title = trim($value);
    $length = function_exists('mb_strlen') ? mb_strlen($title, 'UTF-8') : strlen($title);
    if ($title === '' || $length > 255) {
        bannerReply(400, ['status' => 'error', 'message' => 'Tiêu đề Banner phải có từ 1 đến 255 ký tự.']);
    }
    return $title;
}

function bannerLocalFile(string $relative): ?string
{
    // Restrict to a single filename inside the Banner upload directory.
    if (strlen($relative) > 255 ||
        !preg_match('~\Auploads/banners/[A-Za-z0-9._() -]+\.(?:jpg|jpeg|png|webp)\z~iD', $relative)) {
        return null;
    }
    $root = realpath(__DIR__ . '/uploads/banners');
    if ($root === false) return null;
    $absolute = realpath(__DIR__ . '/' . $relative);
    if ($absolute === false || !str_starts_with($absolute, $root . DIRECTORY_SEPARATOR) ||
        !is_file($absolute) || is_link(__DIR__ . '/' . $relative)) {
        return null;
    }
    return $absolute;
}

function bannerUpload(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK ||
        empty($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
        bannerReply(400, ['status' => 'error', 'message' => 'Vui lòng chọn ảnh Banner hợp lệ.']);
    }
    $size = (int) ($file['size'] ?? 0);
    if ($size < 1 || $size > 5 * 1024 * 1024) {
        bannerReply(400, ['status' => 'error', 'message' => 'Ảnh phải có dung lượng từ 1 byte đến 5 MB.']);
    }
    $tmp = (string) $file['tmp_name'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp);
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!is_string($mime) || !isset($types[$mime]) || getimagesize($tmp) === false) {
        bannerReply(400, ['status' => 'error', 'message' => 'Chỉ chấp nhận ảnh JPEG, PNG hoặc WebP thực sự.']);
    }
    $dir = __DIR__ . '/uploads/banners';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Không tạo được thư mục uploads/banners.');
    }
    $name = bin2hex(random_bytes(16)) . '.' . $types[$mime];
    $absolute = $dir . '/' . $name;
    if (!move_uploaded_file($tmp, $absolute)) {
        throw new RuntimeException('Không lưu được ảnh Banner.');
    }
    return 'uploads/banners/' . $name;
}

function bannerCleanupUnreferenced(PDO $db, string $imageUrl): void
{
    // Never delete a file still used by another banner.
    $stmt = $db->prepare('SELECT COUNT(*) FROM banners WHERE image_url = ?');
    $stmt->execute([$imageUrl]);
    if ((int) $stmt->fetchColumn() !== 0) return;

    $file = bannerLocalFile($imageUrl);
    if ($file !== null && !unlink($file)) {
        error_log('Banner image cleanup failed: ' . $imageUrl);
    }
}

if (!$db instanceof PDO) {
    bannerReply(500, ['status' => 'error', 'message' => 'Không tìm thấy kết nối MySQL.']);
}
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$newImage = null;

try {
    if ($method === 'GET') {
        if (($_GET['action'] ?? '') === 'csrf') {
            bannerRequireManager();
            bannerReply(200, ['status' => 'success', 'csrf_token' => bannerCsrfToken()]);
        }
        if (isset($_GET['id'])) {
            $id = bannerId($_GET['id']);
            if ($id === null) bannerReply(400, ['status' => 'error', 'message' => 'ID Banner không hợp lệ.']);
            $stmt = $db->prepare('SELECT id, title, image_url, event_id, importance, created_at FROM banners WHERE id = ?');
            $stmt->execute([$id]);
            $banner = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$banner) bannerReply(404, ['status' => 'error', 'message' => 'Không tìm thấy Banner.']);
            bannerReply(200, $banner);
        }
        $stmt = $db->query('SELECT id, title, image_url, event_id, importance, created_at FROM banners ORDER BY id DESC');
        bannerReply(200, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    if (!in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
        bannerReply(405, ['status' => 'error', 'message' => 'Phương thức HTTP không được hỗ trợ.']);
    }
    bannerRequireManager();

    if ($method === 'POST') {
        $isMultipart = str_contains(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'multipart/form-data');
        $body = $isMultipart ? $_POST : bannerJson();
        bannerRequireCsrf($body);
        $title = bannerTitle($body['title'] ?? null);

        if ($isMultipart) {
            $newImage = bannerUpload($_FILES['banner_image'] ?? []);
            $imageUrl = $newImage;
        } else {
            // For testing with existing Banner images (no extra uploads).
            // Do not allow arbitrary local paths or remote URLs.
            $imageUrl = $body['image_url'] ?? null;
            if (!is_string($imageUrl) || bannerLocalFile($imageUrl) === null) {
                bannerReply(400, ['status' => 'error', 'message' => 'image_url phải trỏ đến một ảnh Banner hiện có trong uploads/banners.']);
            }
        }

        $stmt = $db->prepare('INSERT INTO banners (title, image_url) VALUES (?, ?)');
        $stmt->execute([$title, $imageUrl]);
        bannerReply(201, [
            'status' => 'success',
            'message' => 'Tạo Banner thành công.',
            'id' => (int) $db->lastInsertId(),
            'image_url' => $imageUrl,
        ]);
    }

    if ($method === 'PUT') {
        $body = bannerJson();
        bannerRequireCsrf($body);
        $id = bannerId($body['id'] ?? null);
        if ($id === null) bannerReply(400, ['status' => 'error', 'message' => 'Thiếu ID Banner hợp lệ.']);
        $title = bannerTitle($body['title'] ?? null);
        $stmt = $db->prepare('SELECT id FROM banners WHERE id = ?');
        $stmt->execute([$id]);
        if (!$stmt->fetchColumn()) bannerReply(404, ['status' => 'error', 'message' => 'Không tìm thấy Banner.']);
        $stmt = $db->prepare('UPDATE banners SET title = ? WHERE id = ?');
        $stmt->execute([$title, $id]);
        bannerReply(200, ['status' => 'success', 'message' => 'Cập nhật Banner thành công.']);
    }

    // DELETE /api_banners.php?id=... with authenticated session and CSRF header.
    bannerRequireCsrf();
    $id = bannerId($_GET['id'] ?? null);
    if ($id === null) bannerReply(400, ['status' => 'error', 'message' => 'Thiếu ID Banner hợp lệ để xóa.']);
    $db->beginTransaction();
    $stmt = $db->prepare('SELECT image_url FROM banners WHERE id = ? FOR UPDATE');
    $stmt->execute([$id]);
    $imageUrl = $stmt->fetchColumn();
    if ($imageUrl === false) {
        $db->rollBack();
        bannerReply(404, ['status' => 'error', 'message' => 'Không tìm thấy Banner.']);
    }
    $stmt = $db->prepare('DELETE FROM banners WHERE id = ?');
    $stmt->execute([$id]);
    $db->commit();
    try {
        bannerCleanupUnreferenced($db, (string) $imageUrl);
    } catch (Throwable $cleanupError) {
        error_log('Banner cleanup deferred: ' . $cleanupError->getMessage());
    }
    bannerReply(200, ['status' => 'success', 'message' => 'Xóa Banner thành công.']);
} catch (Throwable $error) {
    if ($db->inTransaction()) $db->rollBack();
    if ($newImage !== null) {
        try { bannerCleanupUnreferenced($db, $newImage); }
        catch (Throwable $cleanupError) { error_log('Banner upload orphan: ' . $cleanupError->getMessage()); }
    }
    error_log('api_banners.php: ' . $error->getMessage());
    bannerReply(500, ['status' => 'error', 'message' => 'Lỗi hệ thống Banner. Kiểm tra log PHP/Docker.']);
}
