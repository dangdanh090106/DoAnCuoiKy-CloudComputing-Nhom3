document.addEventListener('DOMContentLoaded', function () {

    let allEvents = [];
    let searchTimeout = null;

    const eventList = document.getElementById('event-list');
    const searchInput = document.getElementById('searchInput');
    const btnSearch = document.getElementById('btnSearch');
    const filterButtons = document.querySelectorAll(".btn-filter-custom");

    loadEventData();

    // 1. Tải dữ liệu sự kiện từ API
    function loadEventData() {
        fetch('api_su_kien.php')
            .then(response => {
                if (!response.ok) throw new Error('Không thể tải dữ liệu');
                return response.json();
            })
            .then(data => {
                allEvents = data;
                renderAndFilter();
            })
            .catch(error => {
                console.error('Lỗi khi tải danh sách sự kiện:', error);
                if (eventList) {
                    eventList.innerHTML = `<div class="event-message text-danger"><i class="bi bi-exclamation-triangle fs-2 d-block mb-2"></i> Không thể tải danh sách sự kiện lúc này.</div>`;
                }
            });
    }

    function getProp(obj, ...keys) {
        for (const key of keys) {
            if (obj && obj[key] !== undefined && obj[key] !== null && String(obj[key]).trim() !== '') {
                return obj[key];
            }
        }
        return '';
    }

    function normalizeText(value) {
        return String(value || '').normalize('NFC').toLocaleLowerCase('vi').trim();
    }

    function formatDate(value) {
        if (!value) return 'Chưa cập nhật';
        const text = String(value).trim();
        const match = text.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::\d{2})?)?$/);
        if (!match) return text;
        const date = `${match[3]}/${match[2]}/${match[1]}`;
        return match[4] !== undefined ? `${date} ${match[4]}:${match[5]}` : date;
    }

    function getEventTitle(event) {
        return getProp(event, 'ten_sukien', 'ten_su_kien', 'tensukien', 'title', 'name') || 'Sự kiện chưa có tên';
    }

    function getEventId(event) {
        return getProp(event, 'id_su_kien', 'id_sukien', 'ma_sukien', 'ma_su_kien', 'id');
    }

    function isValidEventId(eventId) {
        return eventId !== '' && Number.isInteger(Number(eventId)) && Number(eventId) > 0;
    }

    // 2. Xử lý sự kiện bấm nút lọc tab và tìm kiếm
    filterButtons.forEach(btn => {
        btn.addEventListener("click", function () {
            filterButtons.forEach(b => b.classList.remove("active"));
            this.classList.add("active");
            renderAndFilter();
        });
    });

    if (btnSearch && searchInput) {
        btnSearch.addEventListener('click', renderAndFilter);
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(renderAndFilter, 400);
        });
    }

    // Hàm global để các file PHP gọi qua lại nếu cần
    window.filterEvents = function(filterType) {
        filterButtons.forEach(b => {
            if (b.getAttribute("data-filter") === filterType) {
                b.classList.add("active");
            } else {
                b.classList.remove("active");
            }
        });
        renderAndFilter();
    };

    // 3. Hàm render giao diện Card và thực hiện lọc theo thời gian
    function renderAndFilter() {
        if (!eventList) return;
        eventList.innerHTML = '';

        const activeBtn = document.querySelector(".btn-filter-custom.active");
        const filterType = activeBtn ? activeBtn.getAttribute("data-filter") : 'all';
        const keyword = searchInput ? normalizeText(searchInput.value) : '';
        const now = new Date(); // Thời điểm hiện tại

        let filtered = allEvents.filter(event => {
            const title = normalizeText(getEventTitle(event));
            const loc = normalizeText(getProp(event, 'ten_diadiem', 'dia_diem', 'vi_tri'));
            
            // Lọc theo từ khóa tìm kiếm
            if (keyword && !title.includes(keyword) && !loc.includes(keyword)) {
                return false;
            }

            // Lọc theo thời gian bắt đầu và kết thúc
            const rawStart = getProp(event, 'thoi_gian_bat_dau', 'batdau', 'ngay_to_chuc', 'ngay', 'date');
            const rawEnd = getProp(event, 'thoi_gian_ket_thuc', 'ngay_ket_thuc');

            if (!rawStart) return filterType === 'all';

            const startDate = new Date(String(rawStart).trim().replace(' ', 'T'));
            const endDate = rawEnd ? new Date(String(rawEnd).trim().replace(' ', 'T')) : startDate;

            if (isNaN(startDate.getTime())) return filterType === 'all';

            if (filterType === 'upcoming') {
                return startDate > now;
            } else if (filterType === 'newest') {
                return startDate >= now;
            } else if (filterType === 'ended') {
                return endDate < now;
            }
            return true; // Tab 'all'
        });

        if (filtered.length === 0) {
            eventList.innerHTML = `<div class="event-message text-center text-white py-5 w-100"><i class="bi bi-calendar-x fs-1 d-block mb-2"></i>Hiện không có sự kiện phù hợp.</div>`;
            return;
        }

        filtered.forEach(function (event) {
            const title = getEventTitle(event);
            const eventId = getEventId(event);
            const description = getProp(event, 'mo_ta', 'mota', 'chitiet', 'description') || 'Không có mô tả.';
            const startDate = getProp(event, 'thoi_gian_bat_dau', 'batdau', 'ngay_to_chuc', 'ngay', 'date');
            const rawEnd = getProp(event, 'thoi_gian_ket_thuc', 'ngay_ket_thuc');
            const location = getProp(event, 'ten_diadiem', 'diadiem', 'dia_diem', 'vi_tri', 'diachi') || 'Chưa cập nhật';
            const cardIndex = allEvents.indexOf(event);

            // Kiểm tra xem sự kiện đã kết thúc hay chưa dựa trên thời gian thực
            const endDate = rawEnd ? new Date(String(rawEnd).trim().replace(' ', 'T')) : new Date(String(startDate).trim().replace(' ', 'T'));
            const isEnded = now > endDate;

            // Nút Xem Chi Tiết
            const detailButton = isValidEventId(eventId)
                ? `<button type="button" class="btn btn-outline-success btn-sm btn-open-detail fw-bold flex-fill me-1" data-index="${cardIndex}" style="border-color: #00f2c2; color: #00f2c2;"><i class="bi bi-eye me-1"></i> Chi tiết</button>`
                : `<button type="button" class="btn btn-outline-secondary btn-sm flex-fill me-1" disabled><i class="bi bi-eye me-1"></i> Chi tiết</button>`;

            // Nút Đăng Ký Tham Gia (Tự động chuyển sang trạng thái xám/vô hiệu hóa nếu đã qua thời gian)
            let registerButton = '';
            if (!isValidEventId(eventId)) {
                registerButton = `<button type="button" class="btn btn-secondary btn-sm flex-fill me-1" disabled><i class="bi bi-pencil-square me-1"></i> Đăng ký</button>`;
            } else if (isEnded) {
                registerButton = `<button type="button" class="btn btn-secondary btn-sm flex-fill me-1 disabled opacity-50" style="cursor: not-allowed; background: #334155; border-color: #475569;" disabled><i class="bi bi-slash-circle me-1"></i> Đã kết thúc</button>`;
            } else {
                registerButton = `<button type="button" class="btn btn-danger btn-sm btn-open-register fw-bold flex-fill me-1" data-index="${cardIndex}" style="background: linear-gradient(135deg, #f43f5e 0%, #e11d48 100%); border: none;"><i class="bi bi-pencil-square me-1"></i> Đăng ký</button>`;
            }

            // Nút Cài đặt dạng Dropdown (Icon bánh răng quản trị)
            const adminActionButtons = (typeof canManageEvents !== 'undefined' && canManageEvents && isValidEventId(eventId))
                ? `<div class="dropdown" style="flex: 0 0 auto;">
                       <button class="btn btn-secondary btn-sm dropdown-toggle px-3 py-2" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Cài đặt sự kiện" style="background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.25); color: #fff; border-radius: 10px;">
                           <i class="bi bi-gear-fill"></i>
                       </button>
                       <ul class="dropdown-menu dropdown-menu-dark shadow" style="background: #1a1d29; border: 1px solid #2d3142; font-size: 13px;">
                           <li>
                               <a class="dropdown-item text-warning py-2" href="user/xuly_sua.php?ma_sukien=${eventId}">
                                   <i class="bi bi-pencil-square me-2"></i> Sửa sự kiện
                               </a>
                           </li>
                           <li><hr class="dropdown-divider bg-secondary opacity-25 my-1"></li>
                           <li>
                               <a class="dropdown-item text-danger py-2 btn-delete-dropdown" href="#" data-id="${eventId}" data-title="${title}">
                                   <i class="bi bi-trash me-2"></i> Xóa sự kiện
                               </a>
                           </li>
                       </ul>
                   </div>`
                : '';

            const card = document.createElement('div');
            card.className = 'col-12 col-md-6 col-lg-4 mb-4 event-column';
            card.innerHTML = `
                <div class="card event-card h-100 text-white bg-dark" style="border: 1px solid rgba(0, 242, 194, 0.3); border-radius: 16px; overflow: hidden; background: rgba(3, 2, 62, 0.67);">
                    <div style="height: 200px; width: 100%; overflow: hidden; background: #03022e; position: relative;">
                        <img class="event-image card-img-top" alt="${title}" loading="lazy" style="width: 100%; height: 100%; object-fit: cover;">
                    </div>
                    <div class="card-body d-flex flex-column" style="padding: 20px;">
                        <h5 class="event-title font-weight-bold" style="color: #00f2c2 !important; font-size: 18px; margin-bottom: 12px;"></h5>
                        <p class="event-description text-muted small flex-grow-1" style="color: #cbd5e1 !important; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;"></p>
                        <p class="event-date text-light small mb-2"><i class="bi bi-clock text-success me-1"></i><span></span></p>
                        <p class="event-location text-light small mb-3"><i class="bi bi-geo-alt text-success me-1"></i><span></span></p>
                        <div class="event-actions mt-auto d-flex justify-content-between align-items-center w-100">
                            <div class="d-flex align-items-center w-100" style="gap: 6px;">
                                ${detailButton}
                                ${registerButton}
                                ${adminActionButtons}
                            </div>
                        </div>
                    </div>
                </div>
            `;

            card.querySelector('.event-title').textContent = title;
            card.querySelector('.event-description').textContent = description;
            card.querySelector('.event-date span').textContent = 'Bắt đầu: ' + formatDate(startDate);
            card.querySelector('.event-location span').textContent = 'Địa điểm: ' + location;
          
            // Ưu tiên ảnh được lưu trong MySQL/MinIO qua API.
            // Không gán ảnh cố định theo mã sự kiện.
            const apiImageUrl = String(getProp(event, 'anh_url') || '').trim();
            const legacyImage = String(getProp(event, 'anh_hien_thi', 'file_name', 'anh_bia', 'hinh_anh', 'anh', 'image') || '').trim();
            let imagePath = apiImageUrl || legacyImage;

            // API có thể trả về tên file cũ thay vì URL đầy đủ.
            if (!apiImageUrl && imagePath &&
                !/^(?:https?:\/\/|\/|images\/|uploads\/|data:)/i.test(imagePath)) {
                imagePath = 'images/' + imagePath;
            }

            // Dùng đường dẫn proxy MinIO khi API có metadata nhưng thiếu anh_url.
            if (!imagePath && isValidEventId(eventId) && getProp(event, 'file_path_key')) {
                imagePath = `poster_su_kien.php?ma_sukien=${encodeURIComponent(eventId)}`;
            }

            if (!imagePath) imagePath = 'images/sukien01.jpg';

            // Chống cache ảnh cũ sau khi thay ảnh trong trang quản trị.
            const uploadVersion = getProp(event, 'upload_time', 'updated_at', 'ngay_cap_nhat');
            if (uploadVersion && imagePath !== 'images/sukien01.jpg') {
                imagePath += (imagePath.includes('?') ? '&' : '?') +
                    'v=' + encodeURIComponent(String(uploadVersion));
            }

            const img = card.querySelector('.event-image');
            img.alt = title;
            img.onerror = function () {
                this.onerror = null;
                this.src = 'images/sukien01.jpg';
            };
            img.src = imagePath;

            if (isValidEventId(eventId)) {
                // Sự kiện bấm nút Chi tiết
                card.querySelector('.btn-open-detail').addEventListener('click', function() {
                    showEventDetails(allEvents[this.getAttribute('data-index')], 'view');
                });
                
                // Sự kiện bấm nút Đăng ký (Chỉ gán sự kiện nếu sự kiện chưa kết thúc)
                const regBtn = card.querySelector('.btn-open-register');
                if (regBtn) {
                    regBtn.addEventListener('click', function() {
                        showEventDetails(allEvents[this.getAttribute('data-index')], 'register');
                    });
                }

                // Xử lý sự kiện xóa từ menu dropdown cài đặt bằng Popup Robot Sci-Fi 3D
                const deleteDropdownEl = card.querySelector('.btn-delete-dropdown');
                if (deleteDropdownEl) {
                    deleteDropdownEl.addEventListener('click', function (e) {
                        e.preventDefault();
                        const id = this.getAttribute('data-id');
                        const eventTitle = this.getAttribute('data-title');

                        const existingOverlay = document.querySelector('.sci-fi-confirm-overlay');
                        if (existingOverlay) existingOverlay.remove();

                        const confirmOverlay = document.createElement('div');
                        confirmOverlay.className = 'sci-fi-confirm-overlay';
                        
                        confirmOverlay.innerHTML = `
                            <div class="sci-fi-confirm-box">
                                <div class="sci-robot-container">
                                    <div class="sci-radar-pulse"></div>
                                    <div class="sci-robot-avatar">
                                        <i class="bi bi-robot"></i>
                                        <div class="sci-robot-eye-glow"></div>
                                    </div>
                                    <div class="sci-warning-badge">
                                        <i class="bi bi-exclamation-triangle-fill"></i> SYSTEM WARNING
                                    </div>
                                </div>

                                <div class="sci-fi-confirm-title">XÁC NHẬN HỦY DIỆT SỰ KIỆN</div>
                                <div class="sci-fi-confirm-text">
                                    Cảnh báo! Bạn có chắc chắn muốn xóa vĩnh viễn dữ liệu sự kiện <br>
                                    <strong>"${eventTitle}"</strong> khỏi hệ thống chủ không?
                                </div>

                                <div class="sci-fi-confirm-actions">
                                    <button type="button" class="sci-fi-btn-cancel">
                                        <i class="bi bi-shield-lock-fill"></i> Hủy bỏ
                                    </button>
                                    <button type="button" class="sci-fi-btn-ok">
                                        <i class="bi bi-trash3-fill"></i> Tiến hành xóa
                                    </button>
                                </div>
                            </div>
                        `;

                        document.body.appendChild(confirmOverlay);

                        confirmOverlay.querySelector('.sci-fi-btn-cancel').addEventListener('click', function() {
                            confirmOverlay.remove();
                        });

                        confirmOverlay.querySelector('.sci-fi-btn-ok').addEventListener('click', function() {
                            window.location.href = `user/delete.php?ma_sukien=${id}`;
                        });
                    });
                }
            }

            eventList.appendChild(card);
        });
    }

    // 4. Quản lý nội dung Modal Chi Tiết / Đăng Ký (Có kiểm tra hạn thời gian)
    function showEventDetails(event, mode) {
        const eventId = getEventId(event);
        const title = getEventTitle(event);
        
        const description = getProp(event, 'mo_ta', 'mota', 'description') || 'Không có mô tả.';
        const startDate = getProp(event, 'thoi_gian_bat_dau', 'ngay_to_chuc', 'date');
        const endDate = getProp(event, 'thoi_gian_ket_thuc', 'ngay_ket_thuc');
        const location = getProp(event, 'ten_diadiem', 'dia_diem', 'vi_tri') || 'Chưa cập nhật';
        const address = getProp(event, 'vi_tri', 'dia_chi') || 'Chưa cập nhật';
        const audience = getProp(event, 'doi_tuong_tham_gia', 'doi_tuong') || 'Toàn trường';
        const capacity = getProp(event, 'suc_chua', 'gioi_han') || 'Không giới hạn';

        const modalForm = document.getElementById('modalRegisterForm');
        if (modalForm) modalForm.reset();
        const alertBox = document.getElementById('registerAlert');
        if (alertBox) alertBox.className = "alert d-none";

        document.getElementById("detailTitle").innerText = title;
        document.getElementById("detailId").innerText = eventId;
        document.getElementById("detailStart").innerText = formatDate(startDate);
        document.getElementById("detailEnd").innerText = formatDate(endDate);
        document.getElementById("detailLocation").innerText = location;
        document.getElementById("detailAddress").innerText = address;
        document.getElementById("detailAudience").innerText = audience;
        document.getElementById("detailCapacity").innerText = capacity;
        document.getElementById("detailDescription").innerText = description;
        document.getElementById("formEventId").value = eventId;

        const infoSection = document.getElementById("modalEventInfo");
        const registerSection = document.getElementById("modalRegisterSection");
        const registerDivider = document.getElementById("modalRegisterDivider");

        // Lấy nút đăng ký và thông báo trong Modal
        const btnRegisterModal = document.getElementById('btnRegisterModal');

        if (mode === 'view') {
            if (infoSection) infoSection.style.display = "block";
            if (registerSection) registerSection.style.display = "none";
        } else if (mode === 'register') {
            if (infoSection) infoSection.style.display = "none";
            if (registerSection) registerSection.style.display = "block";
            if (registerDivider) registerDivider.style.display = "none";

            // Kiểm tra thời hạn kết thúc sự kiện trong Modal
            const now = new Date();
            const endDateTime = endDate ? new Date(String(endDate).trim().replace(' ', 'T')) : (startDate ? new Date(String(startDate).trim().replace(' ', 'T')) : now);

            if (now > endDateTime) {
                // Khóa nút đăng ký trong Modal nếu đã hết hạn
                if (btnRegisterModal) {
                    btnRegisterModal.classList.add('disabled', 'opacity-50');
                    btnRegisterModal.style.pointerEvents = 'none';
                    btnRegisterModal.style.background = '#334155';
                    btnRegisterModal.style.borderColor = '#475569';
                    btnRegisterModal.innerHTML = '<i class="bi bi-slash-circle me-2"></i>Sự kiện đã kết thúc - Không thể đăng ký';
                }
                if (alertBox) {
                    alertBox.className = 'alert alert-danger mb-3';
                    alertBox.classList.remove('d-none');
                    alertBox.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-2"></i>Sự kiện này đã qua thời gian diễn ra. Cổng đăng ký đã đóng!';
                }
            } else {
                // Mở khóa bình thường nếu còn hạn
                if (btnRegisterModal) {
                    btnRegisterModal.classList.remove('disabled', 'opacity-50');
                    btnRegisterModal.style.pointerEvents = 'auto';
                    btnRegisterModal.style.background = '';
                    btnRegisterModal.style.borderColor = '';
                    btnRegisterModal.innerHTML = '<i class="bi bi-pencil-square me-2"></i>Đăng ký tham gia';
                    btnRegisterModal.href = `dang-ky.php?ma_sukien=${eventId}`;
                }
                if (alertBox) {
                    alertBox.className = 'alert d-none mb-3';
                }
            }
        }

        const modalElement = document.getElementById('eventDetailModal');
        if (modalElement) {
            const myModal = new bootstrap.Modal(modalElement);
            myModal.show();
        }
    }

    // 5. Chuyển hướng sang trang chọn ghế ngồi
    const btnGoToSeatMap = document.getElementById("btnGoToSeatMap");
    if (btnGoToSeatMap) {
        btnGoToSeatMap.addEventListener("click", function() {
            const eventId = document.getElementById("formEventId").value;
            if (!eventId) {
                alert("Vui lòng đợi hệ thống tải thông tin mã sự kiện!");
                return;
            }
            window.location.href = `chon-ghe.php?id_su_kien=${eventId}`;
        });
    }
});
