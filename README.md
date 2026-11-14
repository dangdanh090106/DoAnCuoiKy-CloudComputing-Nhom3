# 🎓 SEM – Hệ thống quản lý sự kiện sinh viên

**Đồ án cuối kỳ môn Điện toán đám mây | Nhóm 3**  
**Trường Đại học Bình Dương – Phân hiệu Cà Mau**  
**Khoa Công nghệ thông tin, Robot và Trí tuệ nhân tạo**

SEM (*Student Event Management*) là website hỗ trợ quản lý và tham gia các sự kiện sinh viên. Hệ thống hướng đến việc số hóa quy trình **thông báo – đăng ký – chọn chỗ ngồi – điểm danh – theo dõi điểm rèn luyện**, thay cho việc sử dụng các nhóm trò chuyện, biểu mẫu và bảng tính rời rạc.

> **Phạm vi:** Sản phẩm phục vụ mục đích học tập và trình diễn, triển khai cục bộ bằng Docker Compose; chưa phải hệ thống chính thức của Nhà trường.

## ✨ Chức năng chính

### 👨‍🎓 Sinh viên
- Đăng nhập và xem thông tin tài khoản.
- Xem danh sách, thời gian và địa điểm tổ chức sự kiện.
- Đăng ký sự kiện và chọn ghế trên sơ đồ chỗ ngồi.
- Theo dõi lịch sử đăng ký, tham gia và trạng thái điểm danh.
- Nhập **Passcode có hiệu lực 5 phút** để xác nhận tham dự.
- Xem điểm rèn luyện được ghi nhận trong phạm vi hệ thống.

### 🧑‍💼 Quản lý / Chủ nhiệm câu lạc bộ
- Sử dụng các chức năng dành cho sinh viên.
- Quản lý các sự kiện thuộc phạm vi câu lạc bộ được phân quyền.
- Tạo Passcode phục vụ điểm danh sự kiện.
- Theo dõi danh sách sinh viên tham gia.

### 🛡️ Quản trị viên (Admin)
- Quản lý tài khoản và các sự kiện trong hệ thống.
- Thêm, xem, chỉnh sửa, xóa sự kiện (CRUD).
- Tạo Passcode điểm danh và xem danh sách đã điểm danh theo sự kiện.

## 🧰 Công nghệ sử dụng

| Thành phần | Công nghệ | Vai trò |
|---|---|---|
| Giao diện và xử lý nghiệp vụ | PHP, HTML, CSS, JavaScript | Website SEM |
| Web server | Apache | Phục vụ ứng dụng PHP |
| Cơ sở dữ liệu | MySQL 8.0 | Lưu tài khoản, sự kiện, đăng ký, điểm danh và điểm rèn luyện |
| Lưu trữ đối tượng | MinIO | Lưu poster và tệp minh chứng qua API tương thích S3 |
| Container hóa | Docker, Docker Compose | Đóng gói và khởi chạy các dịch vụ |
| Quản lý mã nguồn | Git, GitHub | Cộng tác và quản lý phiên bản |

## 🏗️ Kiến trúc hệ thống

```text
              Người dùng / Trình duyệt
                        |
                http://localhost:8080
                        |
             +-----------------------+
             |  Application          |
             |  PHP + Apache         |
             +-----------------------+
                    |           |
               SQL  |           | S3 API
                    v           v
          +---------------+  +---------------+
          | Database      |  | MinIO         |
          | MySQL 8.0     |  | Object Storage|
          +---------------+  +---------------+
                 |                  |
          Database volume      MinIO volume
```

Ba dịch vụ được liên kết qua mạng nội bộ Docker. Dữ liệu cơ sở dữ liệu và MinIO được lưu bằng **Docker volumes** để duy trì khi container được tạo lại.

## 🚀 Cài đặt và chạy dự án

### 1. Yêu cầu
- Docker Desktop đã được cài đặt và đang chạy.
- Docker Compose (đi kèm các phiên bản Docker Desktop hiện đại).
- Git (nếu tải mã nguồn bằng lệnh `git clone`).

### 2. Tải mã nguồn

```bash
git clone https://github.com/dangdanh090106/DoAnCuoiKy-CloudComputing-Nhom3.git
cd DoAnCuoiKy-CloudComputing-Nhom3
```

### 3. Thiết lập biến môi trường

Tạo tệp `.env` từ `.env.example`:

**Windows PowerShell:**
```powershell
Copy-Item .env.example .env
```

**Linux/macOS:**
```bash
cp .env.example .env
```

Mở `.env`, đặt mật khẩu riêng cho MySQL và MinIO. Ví dụ cấu hình:

```dotenv
DB_HOST=database
DB_PORT=3306
DB_NAME=do_an_cuoi_ky
DB_USER=projectuser
DB_PASSWORD=your_strong_database_password
MYSQL_ROOT_PASSWORD=your_strong_root_password

MINIO_ENDPOINT=minio
MINIO_PORT=9000
MINIO_ROOT_USER=minioadmin
MINIO_ROOT_PASSWORD=your_strong_minio_password
MINIO_BUCKET_NAME=event-posters

APP_PORT=8080
MINIO_CONSOLE_PORT=9001
```

Tệp `.env` chứa thông tin cấu hình riêng của môi trường chạy và không được đưa lên kho mã nguồn công khai.

### 4. Khởi chạy

```bash
docker compose config --quiet
docker compose up -d --build
docker compose ps
```

Ở lần chạy đầu tiên, Docker có thể cần thời gian tải image, build ứng dụng và khởi tạo cơ sở dữ liệu từ tệp SQL được cấu hình trong `compose.yaml`.

### 5. Truy cập

| Dịch vụ | Địa chỉ mặc định |
|---|---|
| Website SEM | http://localhost:8080 |
| MinIO Console | http://localhost:9001 |
| MinIO API | http://localhost:9000 |

Các cổng trên áp dụng khi giữ nguyên cấu hình mặc định của dự án. MinIO Console dành cho quản trị lưu trữ, không phải trang đăng nhập SEM.

### 6. Dừng và khởi động lại

```bash
# Dừng các container, giữ lại volume dữ liệu
docker compose down

# Khởi động lại
docker compose up -d

# Xem nhật ký nếu có lỗi
docker compose logs --tail=100 application database minio
```

### 7. Xóa dữ liệu và khởi tạo lại

```bash
docker compose down -v
```

> **Chú ý:** Tùy chọn `-v` xóa các named volume do Compose quản lý, bao gồm dữ liệu MySQL và MinIO. Thư mục bind mount như `./uploads` không bị xóa bởi lệnh này. Để giữ dữ liệu, sử dụng `docker compose down` không kèm `-v`.

## 🧪 Quy trình demo đề xuất

1. Khởi chạy ba container bằng Docker Compose.
2. Đăng nhập bằng tài khoản sinh viên được nhóm chuẩn bị cho buổi demo.
3. Xem sự kiện, đăng ký và chọn vị trí ghế.
4. Đăng nhập bằng tài khoản quản lý hoặc Admin, tạo Passcode điểm danh.
5. Quay lại tài khoản sinh viên, nhập Passcode trong thời hạn hiệu lực.
6. Kiểm tra trạng thái tham dự và điểm rèn luyện trên tài khoản sinh viên.
7. Trình bày Docker Desktop, cấu trúc dịch vụ và MinIO Console.

### Tài khoản trình diễn

Tài khoản quản trị viên và sinh viên được sử dụng để minh họa các luồng nghiệp vụ trong buổi trình diễn. Thông tin đăng nhập được cung cấp riêng cho người đánh giá.

## 📁 Một số tệp và thư mục quan trọng

```text
DoAnCuoiKy-CloudComputing-Nhom3/
├── admin/               # Chức năng quản trị
├── user/                # Chức năng người dùng
├── css/                 # Giao diện
├── js/                  # JavaScript
├── images/              # Tài nguyên hình ảnh
├── uploads/             # Tệp tải lên cục bộ (nếu sử dụng)
├── Dockerfile           # Đóng gói ứng dụng PHP/Apache
├── compose.yaml         # Cấu hình các dịch vụ Docker
├── .env.example         # Mẫu cấu hình môi trường
├── database.php         # Kết nối cơ sở dữ liệu
├── minio_storage.php    # Xử lý lưu trữ MinIO
├── do_an_cuoi_ky.sql    # Dữ liệu/cấu trúc CSDL ban đầu
└── index.php            # Trang chính
```

## 🔍 Kiểm thử và xác minh hệ thống

### 1. Kiểm tra trạng thái dịch vụ

Chạy trong thư mục chứa `compose.yaml`:

```bash
docker compose config --quiet
docker compose ps
docker compose logs --tail=50 application database minio
```

Ba dịch vụ `application`, `database` và `minio` phải ở trạng thái hoạt động; MySQL có thể hiển thị trạng thái `healthy` theo cấu hình healthcheck.

### 2. Kiểm tra cơ sở dữ liệu MySQL

```bash
docker compose exec database mysql -u projectuser -p do_an_cuoi_ky
```

Nhập **mật khẩu `DB_PASSWORD` trong `.env` cục bộ** khi được hỏi. Tại dấu nhắc `mysql>`:

```sql
SHOW TABLES;
SELECT COUNT(*) AS so_su_kien FROM su_kien;
```



### 3. Kiểm tra API bằng Postman

Ứng dụng có các tệp xử lý API như `api_su_kien.php`, `api_dia_diem.php`, `api_checkin.php` và `api_banners.php`. Quy trình kiểm thử:

1. Khởi động Docker Compose và mở Postman.
2. Đặt **Base URL** là `http://localhost:8080`.
3. Chọn API và phương thức HTTP tương ứng, thiết lập tham số, body và phiên đăng nhập (nếu cần).
4. Gửi request tương ứng, kiểm tra mã trạng thái HTTP, dữ liệu phản hồi và thay đổi trong MySQL (nếu có).
5. Với luồng điểm danh, thử Passcode hợp lệ, hết hạn, sai mã và điểm danh lặp.

Các API được kiểm thử theo phương thức HTTP, tham số và quyền truy cập được định nghĩa trong mã nguồn. Kết quả được đối chiếu với dữ liệu trong MySQL và trạng thái hiển thị trên website.

### 4. Kiểm tra MinIO

- Truy cập `http://localhost:9001` và đăng nhập bằng tài khoản MinIO cấu hình **trong `.env` cục bộ**.
- Kiểm tra bucket được ứng dụng sử dụng (mặc định `event-posters`).
- Tải poster hoặc tệp minh chứng qua chức năng tương ứng của ứng dụng; kiểm tra đối tượng trong bucket và xác minh khả năng truy cập tệp.
- Không công khai Access Key, Secret Key hoặc liên kết đối tượng có quyền truy cập riêng tư.

### 5. Kiểm chứng dữ liệu bền vững (persistence)

1. Khởi chạy bằng `docker compose up -d --build`, tạo một đăng ký hoặc sự kiện demo và tải một poster lên MinIO.
2. Ghi lại mã sự kiện/đăng ký và tên đối tượng trong bucket; xác nhận chúng xuất hiện trong MySQL/MinIO.
3. Chạy lệnh **không có `-v`**:

   ```bash
   docker compose down
   docker compose up -d
   ```

4. Mở lại website, kiểm tra bản ghi MySQL và poster MinIO vẫn còn. Chụp ảnh trước/sau để đưa vào báo cáo và video demo.

> Trong phép thử này, không dùng `docker compose down -v` vì lệnh đó xóa các volume chứa dữ liệu.

### 6. Các trường hợp kiểm thử

| Nhóm kiểm thử | Tình huống | Kết quả mong đợi |
|---|---|---|
| Xác thực | Đăng nhập đúng/sai thông tin | Cho phép hoặc từ chối phù hợp |
| Phân quyền | Sinh viên truy cập chức năng Admin | Bị chặn theo quyền |
| Sự kiện | Xem danh sách và chi tiết | Hiển thị dữ liệu từ MySQL |
| Đăng ký | Đăng ký sự kiện, chọn ghế | Lưu đúng trạng thái đăng ký |
| Điểm danh | Passcode hợp lệ và không hợp lệ | Ghi nhận hoặc từ chối theo quy tắc |
| Điểm rèn luyện | Mở trang chi tiết sau khi điểm danh | Tổng điểm và lịch sử hiển thị nhất quán |
| Lưu trữ | Tải poster lên MinIO | Tệp được lưu và hiển thị đúng |



## 🛠️ Xử lý lỗi thường gặp

| Hiện tượng | Hướng kiểm tra |
|---|---|
| `localhost:8080` không truy cập được | Chạy `docker compose ps`, kiểm tra log `application` và cổng 8080 có bị chiếm không |
| MySQL báo `Access denied` | Kiểm tra `.env`, tài khoản trong MySQL và volume cũ; đổi `.env` không tự đổi mật khẩu đã khởi tạo |
| Website có tổng điểm nhưng trang chi tiết trống | Đối chiếu dữ liệu tổng điểm với bảng ghi nhận điểm danh, bảng đăng ký và truy vấn trong trang chi tiết |
| Không hiển thị poster | Kiểm tra bucket MinIO, đường dẫn đối tượng, quyền truy cập và log ứng dụng |
| Thay đổi mã nguồn chưa xuất hiện | Nếu mã nguồn được đóng gói vào image, chạy `docker compose up -d --build` |

**Lưu ý dữ liệu:** Không sử dụng `docker compose down -v` để sửa lỗi thông thường. Nên sao lưu dữ liệu trước khi thay đổi cấu trúc hoặc khởi tạo lại MySQL.

## 👥 Thành viên Nhóm 3

| Thành viên | MSSV | Công việc chính |
|---|---|---|
| **Đặng Công Danh** (Nhóm trưởng) | 24210501002 | Triển khai Docker và cấu hình môi trường; điều phối nhóm |
| Võ Khánh Băng | 24210501052 | Khởi tạo cơ sở dữ liệu, Backend và phân hệ Admin |
| Châu Tiến Đạt | 24210501011 | Khung giao diện và chức năng CRUD sự kiện |
| Nguyễn Thanh Nhàn | 24210501026 | Chức năng sinh viên và đăng ký sự kiện |
| Trần Vĩnh Thịnh | 24210501042 | Xử lý tệp tin và lưu trữ MinIO |

**Giảng viên hướng dẫn:** ThS. Dương Thái Bảo.

## 📌 Phạm vi hệ thống

- Hệ thống triển khai **cục bộ**, chưa có địa chỉ website công khai.
- Điểm rèn luyện hiển thị trong SEM **chưa tự động đồng bộ** với hệ thống chính thức của trường.
- Passcode dùng chung có thời hạn giúp giới hạn thời gian điểm danh nhưng chưa xác minh chắc chắn vị trí thực tế của người nhập.

## 🔮 Hướng phát triển

- Tăng cường bảo mật, kiểm soát quyền truy cập và sao lưu dữ liệu.
- Cải tiến phương thức điểm danh để hạn chế chia sẻ mã.
- Đánh giá hiệu năng với số lượng người dùng lớn hơn và xem xét triển khai lên hạ tầng đám mây.
- Nghiên cứu khả năng tích hợp hệ thống quản lý chính thức của Nhà trường khi được cấp quyền.

## 🔗 Mã nguồn

**GitHub:** https://github.com/dangdanh090106/DoAnCuoiKy-CloudComputing-Nhom3

---

*Đồ án học phần Điện toán đám mây – Nhóm 3 – Tháng 11/2026.*
