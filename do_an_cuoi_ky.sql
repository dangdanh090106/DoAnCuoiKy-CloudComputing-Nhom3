-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Oct 18, 2026 at 10:40 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `do_an_cuoi_ky`
--

-- --------------------------------------------------------

--
-- Table structure for table `dang_ky`
--

CREATE TABLE `dang_ky` (
  `ma_dangky` int(11) NOT NULL,
  `ma_sv` varchar(50) NOT NULL,
  `ma_sukien` int(11) NOT NULL,
  `thoi_gian_dang_ky` timestamp NOT NULL DEFAULT current_timestamp(),
  `trang_thai` varchar(50) DEFAULT 'Đã đăng ký'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `dang_ky`
--

INSERT INTO `dang_ky` (`ma_dangky`, `ma_sv`, `ma_sukien`, `thoi_gian_dang_ky`, `trang_thai`) VALUES
(1, 'SV001', 1, '2026-10-06 04:36:56', 'Đã đăng ký'),
(2, 'SV002', 2, '2026-10-06 04:36:56', 'Đã đăng ký');

-- --------------------------------------------------------

--
-- Table structure for table `dat_ghe`
--

CREATE TABLE `dat_ghe` (
  `id` int(11) NOT NULL,
  `su_kien_id` int(11) NOT NULL,
  `ghe_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `trang_thai` enum('DA_DAT','DA_HUY') DEFAULT 'DA_DAT',
  `ngay_dat` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dia_diem`
--

CREATE TABLE `dia_diem` (
  `ma_diadiem` int(11) NOT NULL,
  `ten_diadiem` varchar(255) NOT NULL,
  `suc_chua` int(11) NOT NULL,
  `vi_tri` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `dia_diem`
--

INSERT INTO `dia_diem` (`ma_diadiem`, `ten_diadiem`, `suc_chua`, `vi_tri`) VALUES
(1, 'Hội trường A', 500, 'Khu nhà trung tâm, Tầng 1'),
(2, 'Phòng Hội thảo B1', 100, 'Dãy nhà B, Tầng 2');

-- --------------------------------------------------------

--
-- Table structure for table `ghe`
--

CREATE TABLE `ghe` (
  `id` int(11) NOT NULL,
  `phong_id` int(11) NOT NULL,
  `hang` varchar(5) NOT NULL,
  `so_ghe` int(11) NOT NULL,
  `loai_ghe` enum('VIP','THUONG','LOI_DI') DEFAULT 'THUONG'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ghe`
--

INSERT INTO `ghe` (`id`, `phong_id`, `hang`, `so_ghe`, `loai_ghe`) VALUES
(1, 1, 'A', 1, 'VIP'),
(2, 1, 'A', 2, 'VIP'),
(3, 1, 'A', 3, 'VIP'),
(4, 1, 'A', 4, 'VIP'),
(5, 1, 'A', 5, 'VIP'),
(6, 1, 'A', 6, 'VIP'),
(7, 1, 'A', 7, 'VIP'),
(8, 1, 'A', 8, 'VIP'),
(9, 1, 'A', 9, 'VIP'),
(10, 1, 'A', 10, 'VIP'),
(11, 1, 'A', 11, 'VIP'),
(12, 1, 'A', 12, 'VIP'),
(13, 1, 'A', 13, 'VIP'),
(14, 1, 'A', 14, 'VIP'),
(15, 1, 'A', 15, 'VIP'),
(16, 1, 'A', 16, 'VIP'),
(17, 1, 'A', 17, 'VIP'),
(18, 1, 'A', 18, 'VIP'),
(19, 1, 'A', 19, 'VIP'),
(20, 1, 'A', 20, 'VIP'),
(21, 1, 'B', 1, 'VIP'),
(22, 1, 'B', 2, 'VIP'),
(23, 1, 'B', 3, 'VIP'),
(24, 1, 'B', 4, 'VIP'),
(25, 1, 'B', 5, 'VIP'),
(26, 1, 'B', 6, 'VIP'),
(27, 1, 'B', 7, 'VIP'),
(28, 1, 'B', 8, 'VIP'),
(29, 1, 'B', 9, 'VIP'),
(30, 1, 'B', 10, 'VIP'),
(31, 1, 'B', 11, 'VIP'),
(32, 1, 'B', 12, 'VIP'),
(33, 1, 'B', 13, 'VIP'),
(34, 1, 'B', 14, 'VIP'),
(35, 1, 'B', 15, 'VIP'),
(36, 1, 'B', 16, 'VIP'),
(37, 1, 'B', 17, 'VIP'),
(38, 1, 'B', 18, 'VIP'),
(39, 1, 'B', 19, 'VIP'),
(40, 1, 'B', 20, 'VIP'),
(41, 1, 'C', 1, 'THUONG'),
(42, 1, 'C', 2, 'THUONG'),
(43, 1, 'C', 3, 'THUONG'),
(44, 1, 'C', 4, 'THUONG'),
(45, 1, 'C', 5, 'THUONG'),
(46, 1, 'C', 6, 'THUONG'),
(47, 1, 'C', 7, 'THUONG'),
(48, 1, 'C', 8, 'THUONG'),
(49, 1, 'C', 9, 'THUONG'),
(50, 1, 'C', 10, 'THUONG'),
(51, 1, 'C', 11, 'THUONG'),
(52, 1, 'C', 12, 'THUONG'),
(53, 1, 'C', 13, 'THUONG'),
(54, 1, 'C', 14, 'THUONG'),
(55, 1, 'C', 15, 'THUONG'),
(56, 1, 'C', 16, 'THUONG'),
(57, 1, 'C', 17, 'THUONG'),
(58, 1, 'C', 18, 'THUONG'),
(59, 1, 'C', 19, 'THUONG'),
(60, 1, 'C', 20, 'THUONG'),
(61, 1, 'D', 1, 'THUONG'),
(62, 1, 'D', 2, 'THUONG'),
(63, 1, 'D', 3, 'THUONG'),
(64, 1, 'D', 4, 'THUONG'),
(65, 1, 'D', 5, 'THUONG'),
(66, 1, 'D', 6, 'THUONG'),
(67, 1, 'D', 7, 'THUONG'),
(68, 1, 'D', 8, 'THUONG'),
(69, 1, 'D', 9, 'THUONG'),
(70, 1, 'D', 10, 'THUONG'),
(71, 1, 'D', 11, 'THUONG'),
(72, 1, 'D', 12, 'THUONG'),
(73, 1, 'D', 13, 'THUONG'),
(74, 1, 'D', 14, 'THUONG'),
(75, 1, 'D', 15, 'THUONG'),
(76, 1, 'D', 16, 'THUONG'),
(77, 1, 'D', 17, 'THUONG'),
(78, 1, 'D', 18, 'THUONG'),
(79, 1, 'D', 19, 'THUONG'),
(80, 1, 'D', 20, 'THUONG'),
(81, 1, 'E', 1, 'THUONG'),
(82, 1, 'E', 2, 'THUONG'),
(83, 1, 'E', 3, 'THUONG'),
(84, 1, 'E', 4, 'THUONG'),
(85, 1, 'E', 5, 'THUONG'),
(86, 1, 'E', 6, 'THUONG'),
(87, 1, 'E', 7, 'THUONG'),
(88, 1, 'E', 8, 'THUONG'),
(89, 1, 'E', 9, 'THUONG'),
(90, 1, 'E', 10, 'THUONG'),
(91, 1, 'E', 11, 'THUONG'),
(92, 1, 'E', 12, 'THUONG'),
(93, 1, 'E', 13, 'THUONG'),
(94, 1, 'E', 14, 'THUONG'),
(95, 1, 'E', 15, 'THUONG'),
(96, 1, 'E', 16, 'THUONG'),
(97, 1, 'E', 17, 'THUONG'),
(98, 1, 'E', 18, 'THUONG'),
(99, 1, 'E', 19, 'THUONG'),
(100, 1, 'E', 20, 'THUONG'),
(101, 1, 'F', 1, 'THUONG'),
(102, 1, 'F', 2, 'THUONG'),
(103, 1, 'F', 3, 'THUONG'),
(104, 1, 'F', 4, 'THUONG'),
(105, 1, 'F', 5, 'THUONG'),
(106, 1, 'F', 6, 'THUONG'),
(107, 1, 'F', 7, 'THUONG'),
(108, 1, 'F', 8, 'THUONG'),
(109, 1, 'F', 9, 'THUONG'),
(110, 1, 'F', 10, 'THUONG'),
(111, 1, 'F', 11, 'THUONG'),
(112, 1, 'F', 12, 'THUONG'),
(113, 1, 'F', 13, 'THUONG'),
(114, 1, 'F', 14, 'THUONG'),
(115, 1, 'F', 15, 'THUONG'),
(116, 1, 'F', 16, 'THUONG'),
(117, 1, 'F', 17, 'THUONG'),
(118, 1, 'F', 18, 'THUONG'),
(119, 1, 'F', 19, 'THUONG'),
(120, 1, 'F', 20, 'THUONG'),
(121, 1, 'G', 1, 'THUONG'),
(122, 1, 'G', 2, 'THUONG'),
(123, 1, 'G', 3, 'THUONG'),
(124, 1, 'G', 4, 'THUONG'),
(125, 1, 'G', 5, 'THUONG'),
(126, 1, 'G', 6, 'THUONG'),
(127, 1, 'G', 7, 'THUONG'),
(128, 1, 'G', 8, 'THUONG'),
(129, 1, 'G', 9, 'THUONG'),
(130, 1, 'G', 10, 'THUONG'),
(131, 1, 'G', 11, 'THUONG'),
(132, 1, 'G', 12, 'THUONG'),
(133, 1, 'G', 13, 'THUONG'),
(134, 1, 'G', 14, 'THUONG'),
(135, 1, 'G', 15, 'THUONG'),
(136, 1, 'G', 16, 'THUONG'),
(137, 1, 'G', 17, 'THUONG'),
(138, 1, 'G', 18, 'THUONG'),
(139, 1, 'G', 19, 'THUONG'),
(140, 1, 'G', 20, 'THUONG'),
(141, 1, 'H', 1, 'THUONG'),
(142, 1, 'H', 2, 'THUONG'),
(143, 1, 'H', 3, 'THUONG'),
(144, 1, 'H', 4, 'THUONG'),
(145, 1, 'H', 5, 'THUONG'),
(146, 1, 'H', 6, 'THUONG'),
(147, 1, 'H', 7, 'THUONG'),
(148, 1, 'H', 8, 'THUONG'),
(149, 1, 'H', 9, 'THUONG'),
(150, 1, 'H', 10, 'THUONG'),
(151, 1, 'H', 11, 'THUONG'),
(152, 1, 'H', 12, 'THUONG'),
(153, 1, 'H', 13, 'THUONG'),
(154, 1, 'H', 14, 'THUONG'),
(155, 1, 'H', 15, 'THUONG'),
(156, 1, 'H', 16, 'THUONG'),
(157, 1, 'H', 17, 'THUONG'),
(158, 1, 'H', 18, 'THUONG'),
(159, 1, 'H', 19, 'THUONG'),
(160, 1, 'H', 20, 'THUONG'),
(161, 1, 'I', 1, 'THUONG'),
(162, 1, 'I', 2, 'THUONG'),
(163, 1, 'I', 3, 'THUONG'),
(164, 1, 'I', 4, 'THUONG'),
(165, 1, 'I', 5, 'THUONG'),
(166, 1, 'I', 6, 'THUONG'),
(167, 1, 'I', 7, 'THUONG'),
(168, 1, 'I', 8, 'THUONG'),
(169, 1, 'I', 9, 'THUONG'),
(170, 1, 'I', 10, 'THUONG'),
(171, 1, 'I', 11, 'THUONG'),
(172, 1, 'I', 12, 'THUONG'),
(173, 1, 'I', 13, 'THUONG'),
(174, 1, 'I', 14, 'THUONG'),
(175, 1, 'I', 15, 'THUONG'),
(176, 1, 'I', 16, 'THUONG'),
(177, 1, 'I', 17, 'THUONG'),
(178, 1, 'I', 18, 'THUONG'),
(179, 1, 'I', 19, 'THUONG'),
(180, 1, 'I', 20, 'THUONG'),
(181, 1, 'J', 1, 'THUONG'),
(182, 1, 'J', 2, 'THUONG'),
(183, 1, 'J', 3, 'THUONG'),
(184, 1, 'J', 4, 'THUONG'),
(185, 1, 'J', 5, 'THUONG'),
(186, 1, 'J', 6, 'THUONG'),
(187, 1, 'J', 7, 'THUONG'),
(188, 1, 'J', 8, 'THUONG'),
(189, 1, 'J', 9, 'THUONG'),
(190, 1, 'J', 10, 'THUONG'),
(191, 1, 'J', 11, 'THUONG'),
(192, 1, 'J', 12, 'THUONG'),
(193, 1, 'J', 13, 'THUONG'),
(194, 1, 'J', 14, 'THUONG'),
(195, 1, 'J', 15, 'THUONG'),
(196, 1, 'J', 16, 'THUONG'),
(197, 1, 'J', 17, 'THUONG'),
(198, 1, 'J', 18, 'THUONG'),
(199, 1, 'J', 19, 'THUONG'),
(200, 1, 'J', 20, 'THUONG');

-- --------------------------------------------------------

--
-- Table structure for table `phong_hoc`
--

CREATE TABLE `phong_hoc` (
  `id` int(11) NOT NULL,
  `ten_phong` varchar(100) NOT NULL,
  `so_hang` int(11) NOT NULL,
  `so_cot` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `phong_hoc`
--

INSERT INTO `phong_hoc` (`id`, `ten_phong`, `so_hang`, `so_cot`) VALUES
(1, 'Hội trường chính', 10, 20);

-- --------------------------------------------------------

--
-- Table structure for table `sinh_vien`
--

CREATE TABLE `sinh_vien` (
  `ma_sv` varchar(50) NOT NULL,
  `ho_ten` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `mat_khau` varchar(255) NOT NULL,
  `so_dien_thoai` varchar(15) DEFAULT NULL,
  `ngay_tao` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sinh_vien`
--

INSERT INTO `sinh_vien` (`ma_sv`, `ho_ten`, `email`, `mat_khau`, `so_dien_thoai`, `ngay_tao`) VALUES
('SV001', 'Võ Khánh Băng', 'khanhbang@student.edu.vn', 'password_hash_1', '0912345678', '2026-10-06 04:36:56'),
('SV002', 'Châu Tiến Đạt', 'tiendat@student.edu.vn', 'password_hash_2', '0987654321', '2026-10-06 04:36:56');

-- --------------------------------------------------------

--
-- Table structure for table `su_kien`
--

CREATE TABLE `su_kien` (
  `ma_sukien` int(11) NOT NULL,
  `ten_sukien` varchar(255) NOT NULL,
  `mo_ta` text DEFAULT NULL,
  `thoi_gian_bat_dau` datetime NOT NULL,
  `thoi_gian_ket_thuc` datetime NOT NULL,
  `ma_diadiem` int(11) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_path_key` varchar(500) DEFAULT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `upload_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `su_kien`
--

INSERT INTO `su_kien` (`ma_sukien`, `ten_sukien`, `mo_ta`, `thoi_gian_bat_dau`, `thoi_gian_ket_thuc`, `ma_diadiem`, `file_name`, `file_path_key`, `file_type`, `file_size`, `upload_time`) VALUES
(1, 'Chào tân sinh viên 2026', 'Sự kiện giao lưu đầu khóa.', '2026-10-15 08:00:00', '2026-10-15 11:30:00', 1, NULL, NULL, NULL, NULL, NULL),
(2, 'Workshop Kỹ năng mềm', 'Hướng dẫn viết CV và phỏng vấn.', '2026-10-20 14:00:00', '2026-10-20 16:30:00', 2, NULL, NULL, NULL, NULL, NULL),
(3, 'Hội thảo ứng dụng trí tuệ nhân tạo trong học tập', 'Chia sẻ kinh nghiệm và kiến thức mới.', '2026-10-25 09:00:00', '2026-10-25 12:00:00', 1, NULL, NULL, NULL, NULL, NULL),
(4, 'Hội thao Sinh viên BDU 2026', 'Tổ chức các hoạt động thể thao như bóng chuyền,cầu lông và bóng bàn, tạo sân chơi lành mạnh giúp sinh viên rèn luyện sức khỏe.', '2026-10-20 07:00:44.000000', '2026-10-20 11:00:44.000000', 3, NULL, NULL, NULL, NULL, NULL),
(5, 'Giao lưu văn nghệ sinh viên','Chương trình giao lưu văn nghệ, biểu diễn tài năng và kết nối sinh viên giữa các lớp, tạo không khí học tập vui tươi và đoàn kết.','2026-10-30 18:00:00','2026-10-30 21:00:00',1,NULL,NULL,NULL,NULL,NULL),
(6, 'Ngày hội hiến máu tình nguyện', 'Khuyến khích sinh viên tham gia hoạt động thiện nguyện, lan tỏa tinh thần tương thân tương ái và đóng góp cho cộng đồng.', '2026-10-30 09:00:05.000000', '2026-10-30 011:30:05.000000', '3', NULL, NULL, NULL, NULL, NULL);
-- --------------------------------------------------------
--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `ma_sv` varchar(20) NOT NULL,
  `ho_ten` varchar(100) NOT NULL,
  `mat_khau` varchar(255) NOT NULL,
  `vai_tro` enum('admin','sinh_vien') DEFAULT 'sinh_vien',
  `email` varchar(100) DEFAULT NULL,
  `sdt` varchar(15) DEFAULT NULL,
  `ngay_sinh` date DEFAULT NULL,
  `gioi_tinh` varchar(10) DEFAULT NULL,
  `trang_thai` varchar(50) DEFAULT 'Đang học',
  `ma_lop` varchar(50) DEFAULT '242101TH001'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `ma_sv`, `ho_ten`, `mat_khau`, `vai_tro`, `email`, `sdt`, `ngay_sinh`, `gioi_tinh`, `trang_thai`, `ma_lop`) VALUES
(1, '24210501011', 'Châu Tiến Đạt', '24210501011@', 'sinh_vien', '24210501011@student.bdu.edu.vn', '0832795704', '2006-02-08', 'Nam', 'Đang học', '242101TH001'),
(2, 'admin', 'Quản Trị Viên', '123456', 'admin', 'admin@bdu.edu.vn', NULL, NULL, NULL, 'Đang học', 'ADMIN'),
(3, '24210501052', 'Võ Khánh Băng', '24210501052@', 'sinh_vien', '24210501052@student.bdu.edu.vn', '0949759114', '2006-05-22', 'Nữ', 'Đang học', '242101TH001'),
(4, '24210501026', 'Nguyễn Thanh Nhàn', '24210501026@', 'sinh_vien', '24210501026@student.bdu.edu.vn', '0853430787', '2006-01-01', 'Nam', 'Đang học', '242101TH001');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `dang_ky`
--
ALTER TABLE `dang_ky`
  ADD PRIMARY KEY (`ma_dangky`),
  ADD UNIQUE KEY `duy_nhat_dang_ky` (`ma_sv`,`ma_sukien`),
  ADD KEY `ma_sukien` (`ma_sukien`);

--
-- Indexes for table `dat_ghe`
--
ALTER TABLE `dat_ghe`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_dat_ghe` (`su_kien_id`,`ghe_id`);

--
-- Indexes for table `dia_diem`
--
ALTER TABLE `dia_diem`
  ADD PRIMARY KEY (`ma_diadiem`);

--
-- Indexes for table `ghe`
--
ALTER TABLE `ghe`
  ADD PRIMARY KEY (`id`),
  ADD KEY `phong_id` (`phong_id`);

--
-- Indexes for table `phong_hoc`
--
ALTER TABLE `phong_hoc`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sinh_vien`
--
ALTER TABLE `sinh_vien`
  ADD PRIMARY KEY (`ma_sv`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `su_kien`
--
ALTER TABLE `su_kien`
  ADD PRIMARY KEY (`ma_sukien`),
  ADD KEY `ma_diadiem` (`ma_diadiem`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ma_sv` (`ma_sv`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `dang_ky`
--
ALTER TABLE `dang_ky`
  MODIFY `ma_dangky` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `dat_ghe`
--
ALTER TABLE `dat_ghe`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dia_diem`
--
ALTER TABLE `dia_diem`
  MODIFY `ma_diadiem` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `ghe`
--
ALTER TABLE `ghe`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=201;

--
-- AUTO_INCREMENT for table `phong_hoc`
--
ALTER TABLE `phong_hoc`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `su_kien`
--
ALTER TABLE `su_kien`
  MODIFY `ma_sukien` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `dang_ky`
--
ALTER TABLE `dang_ky`
  ADD CONSTRAINT `dang_ky_ibfk_1` FOREIGN KEY (`ma_sv`) REFERENCES `sinh_vien` (`ma_sv`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `dang_ky_ibfk_2` FOREIGN KEY (`ma_sukien`) REFERENCES `su_kien` (`ma_sukien`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `ghe`
--
ALTER TABLE `ghe`
  ADD CONSTRAINT `ghe_ibfk_1` FOREIGN KEY (`phong_id`) REFERENCES `phong_hoc` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `su_kien`
--
ALTER TABLE `su_kien`
  ADD CONSTRAINT `su_kien_ibfk_1` FOREIGN KEY (`ma_diadiem`) REFERENCES `dia_diem` (`ma_diadiem`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;