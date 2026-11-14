<?php
declare(strict_types=1);

/**
 * REST API dia_diem - compatible with the existing Student Event Management schema.
 * GET public. POST/PUT/DELETE require an authenticated admin/event manager and CSRF.
 * Reuses $_SESSION['event_csrf_token'] from api_su_kien.php.
 */
session_start();
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Allow: GET, POST, PUT, DELETE');
// This API uses same-origin PHP sessions. Do not enable wildcard CORS for writes.

require_once __DIR__ . '/database.php';
$db = $pdo ?? $conn ?? null;

function locationReply(int $code, array $body): never
{
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function locationRequireManager(): void
{
    if (empty($_SESSION['user_id']) || empty($_SESSION['vai_tro'])) {
        locationReply(401, ['status' => 'error', 'message' => 'Bạn chưa đăng nhập.']);
    }
    if (!in_array($_SESSION['vai_tro'], ['admin', 'quan_ly_su_kien'], true)) {
        locationReply(403, ['status' => 'error', 'message' => 'Bạn không có quyền quản lý địa điểm.']);
    }
}

function locationCsrfToken(): string
{
    // The event API already issues this token to the same authenticated session.
    if (empty($_SESSION['event_csrf_token'])) {
        $_SESSION['event_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['event_csrf_token'];
}

function locationRequireCsrf(array $input): void
{
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? '');
    if (!is_string($provided) || $provided === '' ||
        !hash_equals(locationCsrfToken(), $provided)) {
        locationReply(403, ['status' => 'error', 'message' => 'CSRF token không hợp lệ. Hãy tải lại trang.']);
    }
}

function locationPositiveInt(mixed $value): ?int
{
    if (!is_int($value) && !is_string($value)) {
        return null;
    }
    $validated = filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 2147483647],
    ]);
    return $validated === false ? null : (int) $validated;
}

function locationReadJson(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw === false ? '' : $raw, true);
    if (!is_array($data) || array_is_list($data)) {
        locationReply(400, ['status' => 'error', 'message' => 'Body phải là một đối tượng JSON hợp lệ.']);
    }
    return $data;
}

function locationValidateFields(array $data): array
{
    if (!is_string($data['ten_diadiem'] ?? null) ||
        !is_string($data['vi_tri'] ?? null)) {
        locationReply(400, ['status' => 'error', 'message' => 'Tên địa điểm và vị trí phải là chuỗi.']);
    }

    $name = trim($data['ten_diadiem']);
    $position = trim($data['vi_tri']);
    $nameLength = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
    $positionLength = function_exists('mb_strlen') ? mb_strlen($position, 'UTF-8') : strlen($position);
    if ($name === '' || $nameLength > 255 || $position === '' || $positionLength > 255) {
        locationReply(400, ['status' => 'error', 'message' => 'Tên địa điểm và vị trí phải có 1 đến 255 ký tự.']);
    }

    $capacity = locationPositiveInt($data['suc_chua'] ?? null);
    if ($capacity === null) {
        locationReply(400, ['status' => 'error', 'message' => 'Sức chứa phải là số nguyên dương hợp lệ.']);
    }
    return ['ten_diadiem' => $name, 'suc_chua' => $capacity, 'vi_tri' => $position];
}

if (!$db instanceof PDO) {
    locationReply(500, ['status' => 'error', 'message' => 'Không có kết nối cơ sở dữ liệu.']);
}
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
try {
    if ($method === 'GET') {
        if (($_GET['action'] ?? '') === 'csrf') {
            locationRequireManager();
            locationReply(200, ['status' => 'success', 'csrf_token' => locationCsrfToken()]);
        }
        if (isset($_GET['ma_diadiem'])) {
            $id = locationPositiveInt($_GET['ma_diadiem']);
            if ($id === null) {
                locationReply(400, ['status' => 'error', 'message' => 'Mã địa điểm không hợp lệ.']);
            }
            $stmt = $db->prepare('SELECT ma_diadiem, ten_diadiem, suc_chua, vi_tri FROM dia_diem WHERE ma_diadiem = ? LIMIT 1');
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                locationReply(404, ['status' => 'error', 'message' => 'Không tìm thấy địa điểm.']);
            }
            locationReply(200, $row);
        }
        $stmt = $db->query('SELECT ma_diadiem, ten_diadiem, suc_chua, vi_tri FROM dia_diem ORDER BY ma_diadiem DESC');
        locationReply(200, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    if (!in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
        locationReply(405, ['status' => 'error', 'message' => 'Phương thức HTTP không được hỗ trợ.']);
    }

    locationRequireManager();

    if ($method === 'POST') {
        $input = locationReadJson();
        locationRequireCsrf($input);
        $fields = locationValidateFields($input);
        $stmt = $db->prepare('INSERT INTO dia_diem (ten_diadiem, suc_chua, vi_tri) VALUES (?, ?, ?)');
        $stmt->execute([$fields['ten_diadiem'], $fields['suc_chua'], $fields['vi_tri']]);
        locationReply(201, [
            'status' => 'success',
            'message' => 'Tạo địa điểm thành công.',
            'ma_diadiem' => (int) $db->lastInsertId(),
        ]);
    }

    if ($method === 'PUT') {
        $input = locationReadJson();
        locationRequireCsrf($input);
        $id = locationPositiveInt($input['ma_diadiem'] ?? null);
        if ($id === null) {
            locationReply(400, ['status' => 'error', 'message' => 'Mã địa điểm không hợp lệ.']);
        }
        $fields = locationValidateFields($input);
        $stmt = $db->prepare('SELECT ma_diadiem FROM dia_diem WHERE ma_diadiem = ?');
        $stmt->execute([$id]);
        if (!$stmt->fetchColumn()) {
            locationReply(404, ['status' => 'error', 'message' => 'Không tìm thấy địa điểm.']);
        }
        $stmt = $db->prepare('UPDATE dia_diem SET ten_diadiem = ?, suc_chua = ?, vi_tri = ? WHERE ma_diadiem = ?');
        $stmt->execute([$fields['ten_diadiem'], $fields['suc_chua'], $fields['vi_tri'], $id]);
        locationReply(200, ['status' => 'success', 'message' => 'Cập nhật địa điểm thành công.']);
    }

    // Keep the original DELETE ?ma_diadiem=... format for compatibility.
    locationRequireCsrf([]);
    $id = locationPositiveInt($_GET['ma_diadiem'] ?? null);
    if ($id === null) {
        locationReply(400, ['status' => 'error', 'message' => 'Thiếu mã địa điểm hợp lệ để xóa.']);
    }

    $db->beginTransaction();
    $stmt = $db->prepare('SELECT ma_diadiem FROM dia_diem WHERE ma_diadiem = ? FOR UPDATE');
    $stmt->execute([$id]);
    if (!$stmt->fetchColumn()) {
        $db->rollBack();
        locationReply(404, ['status' => 'error', 'message' => 'Không tìm thấy địa điểm.']);
    }

    // In the supplied schema the FK would SET NULL; refuse deletion instead
    // so an existing event does not silently lose its venue.
    $stmt = $db->prepare('SELECT COUNT(*) FROM su_kien WHERE ma_diadiem = ?');
    $stmt->execute([$id]);
    if ((int) $stmt->fetchColumn() > 0) {
        $db->rollBack();
        locationReply(409, ['status' => 'error', 'message' => 'Địa điểm đang được sử dụng bởi sự kiện, không thể xóa.']);
    }

    $stmt = $db->prepare('DELETE FROM dia_diem WHERE ma_diadiem = ?');
    $stmt->execute([$id]);
    $db->commit();
    locationReply(200, ['status' => 'success', 'message' => 'Xóa địa điểm thành công.']);
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('api_dia_diem failed: ' . $e->getMessage());
    locationReply(500, ['status' => 'error', 'message' => 'Lỗi hệ thống địa điểm. Kiểm tra PHP/Docker log.']);
}
