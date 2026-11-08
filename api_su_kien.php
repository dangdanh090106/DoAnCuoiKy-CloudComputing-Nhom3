<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once 'database.php';
$db = isset($pdo) ? $pdo : (isset($conn) ? $conn : null);

if (!$db) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Lỗi kết nối cơ sở dữ liệu."]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['ma_sukien']) || isset($_GET['id'])) {
            $id_val = $_GET['ma_sukien'] ?? $_GET['id'];
            
            // LẤY CHI TIẾT 1 SỰ KIỆN KÈM LEFT JOIN ĐỊA ĐIỂM
            $query = "SELECT sk.*, dd.ten_diadiem, dd.vi_tri, dd.suc_chua as suc_chua_diadiem 
                      FROM su_kien sk 
                      LEFT JOIN dia_diem dd ON sk.ma_diadiem = dd.ma_diadiem 
                      WHERE sk.ma_sukien = :id OR sk.id = :id LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id_val);
            $stmt->execute();

            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($data) {
                $ten_anh = '';
                if (!empty($data['file_name'])) $ten_anh = $data['file_name'];
                elseif (!empty($data['anh_bia'])) $ten_anh = $data['anh_bia'];
                elseif (!empty($data['hinh_anh'])) $ten_anh = $data['hinh_anh'];
                elseif (!empty($data['anh'])) $ten_anh = $data['anh'];

                $data['anh_hien_thi'] = trim($ten_anh);
                echo json_encode($data);
            } else {
                http_response_code(404);
                echo json_encode(["status" => "error", "message" => "Không tìm thấy sự kiện."]);
            }
        } else {
            // LẤY DANH SÁCH TẤT CẢ SỰ KIỆN KÈM LEFT JOIN ĐỊA ĐIỂM
            $query = "SELECT sk.*, dd.ten_diadiem, dd.vi_tri, dd.suc_chua as suc_chua_diadiem 
                      FROM su_kien sk 
                      LEFT JOIN dia_diem dd ON sk.ma_diadiem = dd.ma_diadiem 
                      ORDER BY sk.thoi_gian_bat_dau DESC";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($result as &$row) {
                $ten_anh = '';
                if (!empty($row['file_name'])) $ten_anh = $row['file_name'];
                elseif (!empty($row['anh_bia'])) $ten_anh = $row['anh_bia'];
                elseif (!empty($row['hinh_anh'])) $ten_anh = $row['hinh_anh'];
                elseif (!empty($row['anh'])) $ten_anh = $row['anh'];

                $row['anh_hien_thi'] = trim($ten_anh);
            }
            unset($row);

            echo json_encode($result);
        }
        break;

case 'POST':
        $data = $_POST;
        
        // KIỂM TRA HÀNH ĐỘNG: Đây là SỬA hay THÊM MỚI?
        $is_update = isset($data['action']) && $data['action'] === 'update';
        
        // -----------------------------------------------------
        // 1. XỬ LÝ UPLOAD ẢNH & BẮT LỖI CHI TIẾT
        // -----------------------------------------------------
        $anh_cap_nhat = null;
        $loi_anh = ""; // Biến lưu lỗi để báo ra màn hình nếu ảnh hỏng
        
        if (isset($_FILES['anh_su_kien'])) {
            $file_error = $_FILES['anh_su_kien']['error'];
            
            if ($file_error == 0) { // === UPLOAD_ERR_OK
                $target_dir = __DIR__ . DIRECTORY_SEPARATOR . "images" . DIRECTORY_SEPARATOR;
                if (!is_dir($target_dir) && !mkdir($target_dir, 0777, true) && !is_dir($target_dir)) {
                    $loi_anh = "Không thể tạo thư mục images/ để lưu ảnh.";
                }

                $file_ext = strtolower(pathinfo($_FILES["anh_su_kien"]["name"], PATHINFO_EXTENSION));
                $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if ($loi_anh === "" && in_array($file_ext, $allowed_types, true)) {
                    if ($_FILES["anh_su_kien"]["size"] <= 5242880) { // 5MB
                        $new_name = time() . '_' . uniqid() . '.' . $file_ext;
                        if (move_uploaded_file($_FILES["anh_su_kien"]["tmp_name"], $target_dir . $new_name)) {
                            $anh_cap_nhat = $new_name; // Mọi thứ hoàn hảo, lưu tên ảnh
                        } else {
                            $loi_anh = "Không thể lưu file vào thư mục images/ (Có thể do lỗi phân quyền XAMPP).";
                        }
                    } else {
                        $loi_anh = "Dung lượng ảnh vượt quá 5MB.";
                    }
                } elseif ($loi_anh === "") {
                    $loi_anh = "Định dạng file (.$file_ext) không được hỗ trợ.";
                }
            } elseif ($file_error != 4) { // Lỗi số 4 là do người dùng KHÔNG chọn ảnh -> Bỏ qua không báo lỗi
                $loi_anh = "Lỗi hệ thống tải file (Mã lỗi PHP: $file_error).";
            }
        }

        if ($loi_anh !== "") {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => $loi_anh]);
            break;
        }

        // Tự động quét danh sách các cột có thật trong DB
        $stmt_cols = $db->query("SHOW COLUMNS FROM su_kien");
        $columns = $stmt_cols->fetchAll(PDO::FETCH_COLUMN);

        $col_title = in_array('ten_sukien', $columns) ? 'ten_sukien' : 'ten_su_kien';
        $col_img = in_array('file_name', $columns) ? 'file_name' : (in_array('anh_bia', $columns) ? 'anh_bia' : '');

        if ($is_update) {
            // ==========================================
            // HÀNH ĐỘNG 1: SỬA SỰ KIỆN (UPDATE)
            // ==========================================
            $id_val = $data['ma_sukien'] ?? null;
            if (!$id_val) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "Thiếu mã sự kiện."]);
                break;
            }

            try {
                $update_fields = [];
                $update_values = [];

                $update_fields[] = "$col_title = ?";
                $update_values[] = $data['ten_su_kien'] ?? $data['tieu_de'] ?? '';

                if (in_array('thoi_gian_bat_dau', $columns)) {
                    $update_fields[] = "thoi_gian_bat_dau = ?";
                    $update_values[] = str_replace('T', ' ', $data['thoi_gian_bat_dau'] ?? '');
                }
                if (in_array('thoi_gian_ket_thuc', $columns)) {
                    $update_fields[] = "thoi_gian_ket_thuc = ?";
                    $update_values[] = str_replace('T', ' ', $data['thoi_gian_ket_thuc'] ?? '');
                }

                if (in_array('dia_diem', $columns)) {
                    $update_fields[] = "dia_diem = ?";
                    $update_values[] = $data['dia_diem'] ?? '';
                }
                if (in_array('vi_tri', $columns)) {
                    $update_fields[] = "vi_tri = ?";
                    $update_values[] = $data['vi_tri'] ?? '';
                }
                if (in_array('mo_ta', $columns)) {
                    $update_fields[] = "mo_ta = ?";
                    $update_values[] = $data['mo_ta'] ?? '';
                }
                // Hỗ trợ thêm nếu có các cột này
                if (in_array('doi_tuong_tham_gia', $columns)) {
                    $update_fields[] = "doi_tuong_tham_gia = ?";
                    $update_values[] = $data['doi_tuong'] ?? '';
                }
                if (in_array('suc_chua', $columns)) {
                    $update_fields[] = "suc_chua = ?";
                    $update_values[] = $data['suc_chua'] ?? null;
                }

                // NẾU CÓ CHỌN ẢNH VÀ UPLOAD THÀNH CÔNG THÌ MỚI GHI ĐÈ ẢNH
                if (!empty($col_img) && !empty($anh_cap_nhat)) {
                    $update_fields[] = "$col_img = ?";
                    $update_values[] = $anh_cap_nhat;
                }

                $update_values[] = $id_val; // Đẩy ID vào cuối mảng cho WHERE

                $sql = "UPDATE su_kien SET " . implode(', ', $update_fields) . " WHERE ma_sukien = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($update_values);

                // PHẢN HỒI THÔNG BÁO CHI TIẾT
                $msg_success = "Cập nhật thông tin thành công!";
                if ($loi_anh !== "") {
                    $msg_success .= "\n⚠️ NHƯNG LỖI ẢNH: " . $loi_anh;
                } elseif ($anh_cap_nhat !== null) {
                    $msg_success .= " (Đã kèm ảnh mới).";
                }

                echo json_encode(["status" => "success", "message" => $msg_success]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => "Lỗi CSDL: " . $e->getMessage()]);
            }
        } else {
            // ==========================================
            // HÀNH ĐỘNG 2: THÊM MỚI (INSERT) 
            // ==========================================
            try {
                $insert_data = [];
                $insert_data[$col_title] = $data['ten_su_kien'] ?? $data['tieu_de'] ?? 'Sự kiện mới';

                if (in_array('thoi_gian_bat_dau', $columns)) $insert_data['thoi_gian_bat_dau'] = str_replace('T', ' ', $data['thoi_gian_bat_dau'] ?? '');
                if (in_array('thoi_gian_ket_thuc', $columns)) $insert_data['thoi_gian_ket_thuc'] = str_replace('T', ' ', $data['thoi_gian_ket_thuc'] ?? '');
                if (in_array('mo_ta', $columns)) $insert_data['mo_ta'] = $data['mo_ta'] ?? null;
                if (in_array('dia_diem', $columns)) $insert_data['dia_diem'] = $data['dia_diem'] ?? null;
                if (in_array('vi_tri', $columns)) $insert_data['vi_tri'] = $data['vi_tri'] ?? null;
                
                if (in_array($col_img, $columns) && $anh_cap_nhat !== null) {
                    $insert_data[$col_img] = $anh_cap_nhat;
                }

                $fields = array_keys($insert_data);
                $placeholders = array_fill(0, count($fields), '?');

                $sql = "INSERT INTO su_kien (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
                $stmt = $db->prepare($sql);
                $success = $stmt->execute(array_values($insert_data));

                if ($success) {
                    $msg_success = "Thêm sự kiện thành công!";
                    if ($loi_anh !== "") $msg_success .= "\n⚠️ NHƯNG LỖI ẢNH: " . $loi_anh;
                    echo json_encode(["status" => "success", "message" => $msg_success]);
                } else {
                    http_response_code(500);
                    echo json_encode(["status" => "error", "message" => "Không thể thêm vào CSDL."]);
                }
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => $e->getMessage()]);
            }
        }
        break;

    case 'PUT':
        // 1. Lấy dữ liệu Text
        // Ưu tiên đọc JSON (nếu sửa nhanh ngoài trang chủ)
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Nếu không phải JSON, thử lấy từ $_POST (trong trường hợp JS fetch dùng POST nhưng giả lập PUT)
        if (!$data) {
             $data = $_POST;
        }

        // Nếu vẫn trống, thử parse raw string (dành cho urlencoded)
        if (empty($data)) {
            parse_str(file_get_contents("php://input"), $put_vars);
            $data = $put_vars;
        }

        $id_val = $data['ma_sukien'] ?? $data['id'] ?? null;

        if ($id_val) {
            try {
                $anh_cap_nhat = "";

                // 2. KIỂM TRA & UPLOAD ẢNH (Chỉ chạy nếu $_FILES tồn tại)
                if (isset($_FILES['anh_su_kien']) && $_FILES['anh_su_kien']['error'] === UPLOAD_ERR_OK) {
                    $target_dir = __DIR__ . DIRECTORY_SEPARATOR . "images" . DIRECTORY_SEPARATOR;
                    if (!is_dir($target_dir) && !mkdir($target_dir, 0777, true) && !is_dir($target_dir)) {
                        throw new RuntimeException("Không thể tạo thư mục images/ để lưu ảnh.");
                    }

                    $file_extension = strtolower(pathinfo($_FILES["anh_su_kien"]["name"], PATHINFO_EXTENSION));
                    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (in_array($file_extension, $allowed_types) && $_FILES["anh_su_kien"]["size"] <= 5242880) {
                        $new_file_name = time() . '_' . uniqid() . '.' . $file_extension;
                        $target_file = $target_dir . $new_file_name;
                        
                        if (move_uploaded_file($_FILES["anh_su_kien"]["tmp_name"], $target_file)) {
                            $anh_cap_nhat = $new_file_name;
                        }
                    }
                }

                // 3. THỰC THI CÂU LỆNH UPDATE
                if (!empty($anh_cap_nhat)) {
                    // CÓ ĐỔI ẢNH BÌA MỚI
                    $query = "UPDATE su_kien SET 
                                ten_sukien = :ten_su_kien, 
                                thoi_gian_bat_dau = :thoi_gian_bat_dau, 
                                thoi_gian_ket_thuc = :thoi_gian_ket_thuc,
                                ma_diadiem = :ma_diadiem, 
                                mo_ta = :mo_ta,
                                file_name = :file_name 
                              WHERE ma_sukien = :id OR id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->execute([
                        ':ten_su_kien'      => $data['ten_su_kien'] ?? $data['ten_sukien'] ?? '',
                        ':thoi_gian_bat_dau'=> $data['thoi_gian_bat_dau'] ?? null,
                        ':thoi_gian_ket_thuc'=> $data['thoi_gian_ket_thuc'] ?? null,
                        ':ma_diadiem'       => $data['ma_diadiem'] ?? null,
                        ':mo_ta'            => $data['mo_ta'] ?? '',
                        ':file_name'        => $anh_cap_nhat,
                        ':id'               => $id_val
                    ]);
                } else {
                    // KHÔNG ĐỔI ẢNH (Giữ nguyên ảnh cũ)
                    $query = "UPDATE su_kien SET 
                                ten_sukien = :ten_su_kien, 
                                thoi_gian_bat_dau = :thoi_gian_bat_dau, 
                                thoi_gian_ket_thuc = :thoi_gian_ket_thuc,
                                ma_diadiem = :ma_diadiem, 
                                mo_ta = :mo_ta 
                              WHERE ma_sukien = :id OR id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->execute([
                        ':ten_su_kien'      => $data['ten_su_kien'] ?? $data['ten_sukien'] ?? '',
                        ':thoi_gian_bat_dau'=> $data['thoi_gian_bat_dau'] ?? null,
                        ':thoi_gian_ket_thuc'=> $data['thoi_gian_ket_thuc'] ?? null,
                        ':ma_diadiem'       => $data['ma_diadiem'] ?? null,
                        ':mo_ta'            => $data['mo_ta'] ?? '',
                        ':id'               => $id_val
                    ]);
                }

                echo json_encode(["status" => "success", "message" => "Cập nhật sự kiện thành công!"]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => $e->getMessage()]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Thiếu mã sự kiện cần cập nhật."]);
        }
        break;

    case 'DELETE':
        $id_val = $_GET['ma_sukien'] ?? $_GET['id'] ?? null;
        if ($id_val) {
            $query = "DELETE FROM su_kien WHERE ma_sukien = :id OR id = :id";
            $stmt = $db->prepare($query);
            if ($stmt->execute([':id' => $id_val])) {
                echo json_encode(["status" => "success", "message" => "Xóa sự kiện thành công!"]);
            } else {
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => "Không thể xóa sự kiện."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Thiếu mã sự kiện để xóa."]);
        }
        break;
}
?>