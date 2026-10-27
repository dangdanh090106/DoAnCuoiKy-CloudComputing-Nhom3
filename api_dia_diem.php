<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once 'database.php';

$db = $pdo; // Sử dụng trực tiếp biến $pdo từ file database.php
$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'GET':
        if(isset($_GET['ma_diadiem'])) {
            $query = "SELECT * FROM dia_diem WHERE ma_diadiem = :ma_diadiem LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':ma_diadiem', $_GET['ma_diadiem']);
            $stmt->execute();

            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            if($data) {
                echo json_encode($data);
            } else {
                http_response_code(404);
                echo json_encode(["message" => "Không tìm thấy địa điểm."]);
            }
        } else {
            $query = "SELECT * FROM dia_diem ORDER BY ma_diadiem DESC";
            $stmt = $db->prepare($query);
            $stmt->execute();
            
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($result);
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents("php://input"));
        if(!empty($data->ten_diadiem) && !empty($data->suc_chua) && !empty($data->vi_tri)) {
            $query = "INSERT INTO dia_diem (ten_diadiem, suc_chua, vi_tri) VALUES (:ten_diadiem, :suc_chua, :vi_tri)";
            $stmt = $db->prepare($query);

            $stmt->bindParam(':ten_diadiem', $data->ten_diadiem);
            $stmt->bindParam(':suc_chua', $data->suc_chua);
            $stmt->bindParam(':vi_tri', $data->vi_tri);

            if($stmt->execute()) {
                http_response_code(201);
                echo json_encode(["message" => "Tạo địa điểm thành công."]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Không thể tạo địa điểm."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Dữ liệu không hợp lệ. Vui lòng điền đủ thông tin."]);
        }
        break;

    case 'PUT':
        $data = json_decode(file_get_contents("php://input"));
        if(!empty($data->ma_diadiem) && !empty($data->ten_diadiem) && !empty($data->suc_chua) && !empty($data->vi_tri)) {
            $query = "UPDATE dia_diem SET ten_diadiem = :ten_diadiem, suc_chua = :suc_chua, vi_tri = :vi_tri WHERE ma_diadiem = :ma_diadiem";
            $stmt = $db->prepare($query);

            $stmt->bindParam(':ma_diadiem', $data->ma_diadiem);
            $stmt->bindParam(':ten_diadiem', $data->ten_diadiem);
            $stmt->bindParam(':suc_chua', $data->suc_chua);
            $stmt->bindParam(':vi_tri', $data->vi_tri);

            if($stmt->execute()) {
                echo json_encode(["message" => "Cập nhật địa điểm thành công."]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Không thể cập nhật địa điểm."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Dữ liệu cập nhật không đầy đủ."]);
        }
        break;

    case 'DELETE':
        if(isset($_GET['ma_diadiem'])) {
            $query = "DELETE FROM dia_diem WHERE ma_diadiem = :ma_diadiem";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':ma_diadiem', $_GET['ma_diadiem']);

            if($stmt->execute()) {
                echo json_encode(["message" => "Xóa địa điểm thành công."]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Không thể xóa địa điểm."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Thiếu mã địa điểm cần xóa."]);
        }
        break;
}
?>