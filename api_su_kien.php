<?php
declare(strict_types=1);
/**
 * Bản API đề xuất, thiết kế theo schema do_an_cuoi_ky.sql (18/10/2026).
 * Thay thế api_su_kien.php SAU KHI backup và đối chiếu DB đang chạy.
 * Yêu cầu: database.php tạo $pdo, minio_storage.php, vendor/autoload.php.
 * GET công khai; POST/PUT/DELETE yêu cầu vai trò admin/quan_ly_su_kien và CSRF.
 * Với ảnh, chỉ upload bằng POST multipart/form-data (action=update để sửa).
 */

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/minio_storage.php';
$db = $pdo ?? $conn ?? null;
if (!$db instanceof PDO) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Không kết nối được database.'], JSON_UNESCAPED_UNICODE);
    exit;
}

function replyJson(int $http, array $data): never
{
    http_response_code($http);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function isEventManager(): bool
{
    return in_array($_SESSION['vai_tro'] ?? '', ['admin', 'quan_ly_su_kien'], true);
}
function requireEventManager(): void
{
    if (empty($_SESSION['vai_tro'])) replyJson(401, ['status' => 'error', 'message' => 'Bạn chưa đăng nhập.']);
    if (!isEventManager()) replyJson(403, ['status' => 'error', 'message' => 'Bạn không có quyền quản lý sự kiện.']);
}
function eventCsrfToken(): string
{
    if (empty($_SESSION['event_csrf_token'])) $_SESSION['event_csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['event_csrf_token'];
}
function checkEventCsrf(array $input): void
{
    $token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf_token'] ?? '');
    if (!$token || !hash_equals(eventCsrfToken(), $token)) {
        replyJson(403, ['status' => 'error', 'message' => 'CSRF token không hợp lệ. Hãy tải lại trang.']);
    }
}
function validPositiveId(mixed $value): ?int
{
    $id = filter_var($value, FILTER_VALIDATE_INT);
    return $id !== false && $id > 0 ? $id : null;
}
function dateSql(mixed $value): string
{
    $candidate = trim((string)$value);
    $candidate = str_replace('T', ' ', $candidate);
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/D', $candidate)) $candidate .= ':00';
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $candidate)) {
        throw new InvalidArgumentException('Thời gian phải có định dạng YYYY-MM-DD HH:MM[:SS].');
    }
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $candidate);
    if (!$dt || $dt->format('Y-m-d H:i:s') !== $candidate) {
        throw new InvalidArgumentException('Ngày giờ không hợp lệ.');
    }
    return $candidate;
}
function locationId(PDO $db, array $input): ?int
{
    $raw = trim((string)($input['ma_diadiem'] ?? $input['dia_diem'] ?? ''));
    if ($raw === '') return null;
    if (ctype_digit($raw)) {
        $stmt = $db->prepare('SELECT ma_diadiem FROM dia_diem WHERE ma_diadiem = ?');
        $stmt->execute([(int)$raw]);
    } else {
        $stmt = $db->prepare('SELECT ma_diadiem FROM dia_diem WHERE ten_diadiem = ? LIMIT 1');
        $stmt->execute([$raw]);
    }
    $result = $stmt->fetchColumn();
    if ($result === false) throw new InvalidArgumentException('Địa điểm không tồn tại trong bảng dia_diem.');
    return (int)$result;
}
function eventData(PDO $db, array $input): array
{
    $title = trim((string)($input['ten_su_kien'] ?? $input['ten_sukien'] ?? $input['tieu_de'] ?? ''));
    if ($title === '' || (function_exists('mb_strlen') ? mb_strlen($title, 'UTF-8') : strlen($title)) > 255) throw new InvalidArgumentException('Tên sự kiện phải có từ 1 đến 255 ký tự.');
    $start = dateSql($input['thoi_gian_bat_dau'] ?? '');
    $end = dateSql($input['thoi_gian_ket_thuc'] ?? '');
    if ($end <= $start) throw new InvalidArgumentException('Thời gian kết thúc phải sau thời gian bắt đầu.');
    return [
        'ten_sukien' => $title,
        'mo_ta' => trim((string)($input['mo_ta'] ?? '')),
        'thoi_gian_bat_dau' => $start,
        'thoi_gian_ket_thuc' => $end,
        'ma_diadiem' => locationId($db, $input),
    ];
}
function fileMetadata(?array $poster): array
{
    if ($poster === null) return [];
    return [
        'file_name' => $poster['original_name'],
        'file_path_key' => $poster['object_key'],
        'file_type' => $poster['mime_type'],
        'file_size' => $poster['file_size'],
        'upload_time' => $poster['uploaded_at'],
    ];
}
function imageUrl(array $row): array
{
unset($row['passcode'], $row['passcode_expired_at']);
    $id = (int)$row['ma_sukien'];
    if (!empty($row['file_path_key'])) {
        $row['anh_url'] = 'poster_su_kien.php?ma_sukien=' . $id;
        $row['anh_hien_thi'] = (string)($row['file_name'] ?? '');
    } elseif (!empty($row['file_name'])) {
        // Tương thích các poster cũ vẫn còn trong images/.
        $row['anh_url'] = 'images/' . rawurlencode(basename((string)$row['file_name']));
        $row['anh_hien_thi'] = (string)$row['file_name'];
    } else {
        $row['anh_url'] = null;
        $row['anh_hien_thi'] = '';
    }
    return $row;
}
function uploadFromPost(): ?array
{
    if (!isset($_FILES['anh_su_kien']) || ($_FILES['anh_su_kien']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    return eventUploadPoster($_FILES['anh_su_kien']);
}
function cleanupNewPoster(?array $poster): void
{
    if ($poster !== null) {
        try { eventDeletePoster((string)$poster['object_key']); }
        catch (Throwable $exception) { error_log('MinIO orphan after DB error: ' . $exception->getMessage()); }
    }
}
function cleanupOldKey(?string $key): void
{
    if ($key) {
        try { eventDeletePoster($key); }
        catch (Throwable $exception) { error_log('MinIO old poster cleanup needed: ' . $exception->getMessage()); }
    }
}
function saveEvent(PDO $db, array $input, bool $update, ?int $id, bool $allowFile): never
{
    $newPoster = null;
    $oldKey = null;
    try {
        $fields = eventData($db, $input);
        // Chỉ POST multipart/form-data hỗ trợ upload ảnh trong PHP thông thường.
        if ($allowFile) $newPoster = uploadFromPost();
        $fields = array_merge($fields, fileMetadata($newPoster));
        $db->beginTransaction();
        if ($update) {
            if (!$id) throw new InvalidArgumentException('Thiếu mã sự kiện hợp lệ.');
            $find = $db->prepare('SELECT file_path_key FROM su_kien WHERE ma_sukien=? FOR UPDATE');
            $find->execute([$id]);
            $old = $find->fetch(PDO::FETCH_ASSOC);
            if (!$old) {
                $db->rollBack();
                cleanupNewPoster($newPoster);
                replyJson(404, ['status'=>'error','message'=>'Không tìm thấy sự kiện.']);
            }
            $oldKey = (string)($old['file_path_key'] ?? '');
            $parts = [];
            $values = [];
            foreach ($fields as $name => $value) { $parts[] = "`$name` = ?"; $values[] = $value; }
            $values[] = $id;
            $stmt = $db->prepare('UPDATE su_kien SET ' . implode(', ', $parts) . ' WHERE ma_sukien = ?');
            $stmt->execute($values);
        } else {
            $names = array_keys($fields);
            $columns = implode(', ', array_map(static fn($n) => "`$n`", $names));
            $holders = implode(', ', array_fill(0, count($names), '?'));
            $stmt = $db->prepare("INSERT INTO su_kien ($columns) VALUES ($holders)");
            $stmt->execute(array_values($fields));
            $id = (int)$db->lastInsertId();
        }
        $db->commit();
        // Xóa poster cũ sau khi commit, không xóa trước khi DB cập nhật thành công.
        if ($update && $newPoster !== null && $oldKey !== $newPoster['object_key']) cleanupOldKey($oldKey);
        replyJson($update ? 200 : 201, [
            'status' => 'success',
            'message' => $update ? 'Cập nhật sự kiện thành công!' : 'Thêm sự kiện thành công!',
            'ma_sukien' => $id,
            'anh_url' => $newPoster ? ('poster_su_kien.php?ma_sukien=' . $id) : null,
        ]);
    } catch (InvalidArgumentException $exception) {
        if ($db->inTransaction()) $db->rollBack();
        cleanupNewPoster($newPoster);
        replyJson(400, ['status'=>'error', 'message'=>$exception->getMessage()]);
    } catch (Throwable $exception) {
        if ($db->inTransaction()) $db->rollBack();
        cleanupNewPoster($newPoster);
        error_log('Save event error: ' . $exception->getMessage());
        replyJson(500, ['status'=>'error', 'message'=>'Lỗi lưu sự kiện. Kiểm tra log PHP/Docker.']);
    }
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
try {
    if ($method === 'GET') {
        if (($_GET['action'] ?? '') === 'csrf') {
            requireEventManager();
            replyJson(200, ['status'=>'success', 'csrf_token'=>eventCsrfToken()]);
        }
        if (isset($_GET['ma_sukien']) || isset($_GET['id'])) {
            $id = validPositiveId($_GET['ma_sukien'] ?? $_GET['id']);
            if (!$id) replyJson(400, ['status'=>'error','message'=>'Mã sự kiện không hợp lệ.']);
            $stmt = $db->prepare('SELECT sk.*, dd.ten_diadiem, dd.vi_tri, dd.suc_chua AS suc_chua_diadiem FROM su_kien sk LEFT JOIN dia_diem dd ON sk.ma_diadiem=dd.ma_diadiem WHERE sk.ma_sukien=? LIMIT 1');
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) replyJson(404, ['status'=>'error','message'=>'Không tìm thấy sự kiện.']);
            replyJson(200, imageUrl($row));
        }
        $search = trim((string)($_GET['q'] ?? ''));
        $sql = 'SELECT sk.*, dd.ten_diadiem, dd.vi_tri, dd.suc_chua AS suc_chua_diadiem FROM su_kien sk LEFT JOIN dia_diem dd ON sk.ma_diadiem=dd.ma_diadiem';
        $params = [];
        if ($search !== '') { $sql .= ' WHERE sk.ten_sukien LIKE ?'; $params[] = '%' . $search . '%'; }
        $sql .= ' ORDER BY sk.thoi_gian_bat_dau DESC';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        replyJson(200, array_map('imageUrl', $stmt->fetchAll(PDO::FETCH_ASSOC)));
    }
    requireEventManager();
    if ($method === 'POST') {
        checkEventCsrf($_POST);
        $update = ($_POST['action'] ?? '') === 'update';
        $id = $update ? validPositiveId($_POST['ma_sukien'] ?? $_POST['id'] ?? null) : null;
        saveEvent($db, $_POST, $update, $id, true);
    }
    if ($method === 'PUT') {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!is_array($data)) parse_str($raw, $data);
        checkEventCsrf($data);
        saveEvent($db, $data, true, validPositiveId($data['ma_sukien'] ?? $data['id'] ?? null), false);
    }
    if ($method === 'DELETE') {
        checkEventCsrf([]);
        $id = validPositiveId($_GET['ma_sukien'] ?? $_GET['id'] ?? null);
        if (!$id) replyJson(400, ['status'=>'error','message'=>'Mã sự kiện không hợp lệ.']);
        $db->beginTransaction();
        $find = $db->prepare('SELECT file_path_key FROM su_kien WHERE ma_sukien=? FOR UPDATE');
        $find->execute([$id]);
        $row = $find->fetch(PDO::FETCH_ASSOC);
        if (!$row) { $db->rollBack(); replyJson(404, ['status'=>'error','message'=>'Không tìm thấy sự kiện.']); }
        // Bảng dat_ghe trong SQL dump không khai báo FK vào su_kien, nên xóa thủ công.
        $stmt = $db->prepare('DELETE FROM dat_ghe WHERE su_kien_id=?');
        $stmt->execute([$id]);
        // dang_ky/dang_ky_su_kien có ON DELETE CASCADE tới su_kien.
        $stmt = $db->prepare('DELETE FROM su_kien WHERE ma_sukien=?');
        $stmt->execute([$id]);
        $db->commit();
        cleanupOldKey((string)($row['file_path_key'] ?? ''));
        replyJson(200, ['status'=>'success', 'message'=>'Xóa sự kiện thành công.']);
    }
    header('Allow: GET, POST, PUT, DELETE');
    replyJson(405, ['status'=>'error','message'=>'HTTP method không được hỗ trợ.']);
} catch (Throwable $exception) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('api_su_kien error: ' . $exception->getMessage());
    replyJson(500, ['status'=>'error','message'=>'Lỗi hệ thống. Kiểm tra log ứng dụng.']);
}
