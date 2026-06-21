-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql111.infinityfree.com
-- Generation Time: Jun 20, 2026 at 08:13 AM
-- Server version: 11.4.12-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_42152040_bidders_platform`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_logs`
--

CREATE TABLE `admin_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_logs`
--

INSERT INTO `admin_logs` (`id`, `admin_id`, `action`, `details`, `created_at`) VALUES
(174, 1, 'approve_project', 'ID: 85', '2026-06-20 01:11:55'),
(175, 1, 'admin_approve_bid', 'ID: 41', '2026-06-20 01:15:13'),
(176, 1, 'approve_project', 'ID: 86', '2026-06-20 01:22:33'),
(177, 1, 'verify_contractor', 'ID: 57', '2026-06-20 01:24:54'),
(178, 1, 'toggle_featured', 'ID: 57', '2026-06-20 01:26:46'),
(179, 1, 'admin_approve_bid', 'ID: 42', '2026-06-20 01:37:31'),
(180, 1, 'approve_project', 'ID: 87', '2026-06-20 02:42:43'),
(181, 1, 'admin_approve_bid', 'ID: 43', '2026-06-20 02:44:04'),
(182, 1, 'admin_approve_bid', 'ID: 44', '2026-06-20 11:32:46');

-- --------------------------------------------------------

--
-- Table structure for table `bids`
--

CREATE TABLE `bids` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `contractor_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `proposal` text NOT NULL,
  `duration_days` int(11) DEFAULT NULL,
  `status` enum('pending','pending_employer','accepted','rejected') DEFAULT 'pending',
  `admin_approved` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bids`
--

INSERT INTO `bids` (`id`, `project_id`, `contractor_id`, `amount`, `proposal`, `duration_days`, `status`, `admin_approved`, `created_at`) VALUES
(41, 85, 57, '150000.00', 'تسليم في الوقت المحدد', 120, 'accepted', 1, '2026-06-20 01:14:47'),
(42, 86, 57, '50000.00', 'Hdhd', 60, 'accepted', 1, '2026-06-20 01:37:06'),
(43, 87, 57, '6655.00', 'ةااال', 665, 'rejected', 1, '2026-06-20 02:43:33'),
(44, 87, 58, '4000.00', 'Test', 6, 'accepted', 1, '2026-06-20 11:32:21');

-- --------------------------------------------------------

--
-- Table structure for table `bid_attachments`
--

CREATE TABLE `bid_attachments` (
  `id` int(11) NOT NULL,
  `bid_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_size` int(11) NOT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `file_ext` varchar(20) DEFAULT NULL,
  `uploaded_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bid_attachments`
--

INSERT INTO `bid_attachments` (`id`, `bid_id`, `filename`, `original_name`, `file_size`, `file_type`, `file_ext`, `uploaded_at`) VALUES
(11, 41, 'bid_41_1781918087_0.jpeg', 'images (8).jpeg', 27954, 'image/jpeg', 'jpeg', '2026-06-20 04:14:47');

-- --------------------------------------------------------

--
-- Table structure for table `cities`
--

CREATE TABLE `cities` (
  `id` int(11) NOT NULL,
  `country_id` int(11) NOT NULL,
  `name_ar` varchar(100) NOT NULL,
  `name_en` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cities`
--

INSERT INTO `cities` (`id`, `country_id`, `name_ar`, `name_en`) VALUES
(1, 1, 'الرياض', 'Riyadh'),
(2, 1, 'جدة', 'Jeddah'),
(3, 1, 'مكة المكرمة', 'Mecca'),
(4, 1, 'المدينة المنورة', 'Medina'),
(5, 1, 'الدمام', 'Dammam'),
(6, 1, 'الخبر', 'Khobar'),
(7, 1, 'الظهران', 'Dhahran'),
(8, 1, 'تبوك', 'Tabuk'),
(9, 1, 'بريدة', 'Buraidah'),
(10, 1, 'أبها', 'Abha'),
(11, 1, 'نجران', 'Najran'),
(12, 1, 'الجبيل', 'Jubail'),
(13, 1, 'ينبع', 'Yanbu'),
(14, 1, 'حائل', 'Hail'),
(15, 1, 'الطائف', 'Taif'),
(16, 1, 'الباحة', 'Bahah'),
(17, 1, 'عرعر', 'Arar'),
(18, 1, 'سكاكا', 'Sakaka'),
(19, 1, 'الأحساء', 'Al Ahsa'),
(20, 1, 'القطيف', 'Qatif'),
(21, 2, 'أبوظبي', 'Abu Dhabi'),
(22, 2, 'دبي', 'Dubai'),
(23, 2, 'الشارقة', 'Sharjah'),
(24, 2, 'عجمان', 'Ajman'),
(25, 2, 'رأس الخيمة', 'Ras Al Khaimah'),
(26, 2, 'الفجيرة', 'Fujairah'),
(27, 2, 'أم القيوين', 'Umm Al Quwain'),
(28, 2, 'العين', 'Al Ain'),
(29, 3, 'العاصمة', 'Capital'),
(30, 3, 'حولي', 'Hawally'),
(31, 3, 'الفروانية', 'Farwaniya'),
(32, 3, 'الأحمدي', 'Ahmadi'),
(33, 3, 'الجهراء', 'Jahra'),
(34, 3, 'مبارك الكبير', 'Mubarak Al Kabeer'),
(35, 4, 'المحرق', 'Muharraq'),
(36, 4, 'المنامة', 'Manama'),
(37, 4, 'الشمالية', 'Northern'),
(38, 4, 'الوسطى', 'Central'),
(39, 4, 'الجنوبية', 'Southern'),
(40, 5, 'الدوحة', 'Doha'),
(41, 5, 'الوكرة', 'Al Wakrah'),
(42, 5, 'الخور', 'Al Khor'),
(43, 5, 'الريان', 'Al Rayyan'),
(44, 5, 'أم صلال', 'Umm Salal'),
(45, 5, 'الضخيرة', 'Al Daayen'),
(46, 5, 'الشمال', 'Al Shamal'),
(47, 6, 'مسقط', 'Muscat'),
(48, 6, 'صلالة', 'Salalah'),
(49, 6, 'صحار', 'Sohar'),
(50, 6, 'نزوى', 'Nizwa'),
(51, 6, 'السيب', 'Seeb'),
(52, 6, 'بركاء', 'Barka'),
(53, 6, 'الرستاق', 'Rustaq'),
(54, 6, 'عبري', 'Ibri'),
(55, 6, 'سمائل', 'Samail'),
(56, 6, 'الخابورة', 'Khaburah'),
(57, 7, 'عمّان', 'Amman'),
(58, 7, 'الزرقاء', 'Zarqa'),
(59, 7, 'إربد', 'Irbid'),
(60, 7, 'العقبة', 'Aqaba'),
(61, 7, 'الكرك', 'Karak'),
(62, 7, 'المفرق', 'Mafraq'),
(63, 7, 'معان', 'Maan'),
(64, 7, 'الطفيلة', 'Tafila'),
(65, 7, 'جرش', 'Jerash'),
(66, 7, 'عجلون', 'Ajloun'),
(67, 7, 'مادبا', 'Madaba'),
(68, 7, 'السلط', 'Salt');

-- --------------------------------------------------------

--
-- Table structure for table `contractor_alerts`
--

CREATE TABLE `contractor_alerts` (
  `id` int(11) NOT NULL,
  `contractor_id` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `alert_sound` tinyint(1) DEFAULT 1,
  `alert_email` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `contractor_alerts`
--

INSERT INTO `contractor_alerts` (`id`, `contractor_id`, `is_active`, `alert_sound`, `alert_email`, `created_at`, `updated_at`) VALUES
(6, 57, 1, 0, 0, '2026-06-20 02:54:21', '2026-06-20 04:53:01'),
(7, 58, 1, 1, 1, '2026-06-20 11:30:57', '2026-06-20 11:30:57');

-- --------------------------------------------------------

--
-- Table structure for table `contractor_alerts_log`
--

CREATE TABLE `contractor_alerts_log` (
  `id` int(11) NOT NULL,
  `contractor_id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `alert_type` enum('push','email','sms') DEFAULT 'push',
  `sent_at` timestamp NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contractor_certificates`
--

CREATE TABLE `contractor_certificates` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `uploaded_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contractor_documents`
--

CREATE TABLE `contractor_documents` (
  `id` int(11) NOT NULL,
  `contractor_id` int(11) NOT NULL,
  `document_type` enum('commercial_register','tax_card','professional_license','bank_account','previous_work','other') NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `document_path` varchar(500) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `uploaded_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contractor_specs`
--

CREATE TABLE `contractor_specs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `spec` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contractor_specs`
--

INSERT INTO `contractor_specs` (`id`, `user_id`, `spec`) VALUES
(75, 57, 'أرضيات'),
(76, 57, 'جبس بورد'),
(77, 57, 'زجاج'),
(78, 57, 'عزل'),
(79, 57, 'تنسيق حدائق'),
(80, 57, 'نظافة'),
(81, 57, 'أبواب ونوافذ'),
(82, 58, 'بناء'),
(83, 58, 'كهرباء'),
(84, 58, 'تكييف'),
(85, 58, 'أرضيات');

-- --------------------------------------------------------

--
-- Table structure for table `conversations`
--

CREATE TABLE `conversations` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `employer_id` int(11) NOT NULL,
  `contractor_id` int(11) NOT NULL,
  `status` enum('active','closed') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `employer_unread` int(11) DEFAULT 0,
  `contractor_unread` int(11) DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `countries`
--

CREATE TABLE `countries` (
  `id` int(11) NOT NULL,
  `name_ar` varchar(100) NOT NULL,
  `name_en` varchar(100) NOT NULL,
  `currency_ar` varchar(50) NOT NULL,
  `currency_code` varchar(10) NOT NULL,
  `phone_code` varchar(10) NOT NULL,
  `flag` varchar(10) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `countries`
--

INSERT INTO `countries` (`id`, `name_ar`, `name_en`, `currency_ar`, `currency_code`, `phone_code`, `flag`, `created_at`) VALUES
(1, 'المملكة العربية السعودية', 'Saudi Arabia', 'ريال سعودي', 'SAR', '+966', '🇸🇦', '2026-06-13 17:36:49'),
(2, 'الإمارات العربية المتحدة', 'UAE', 'درهم إماراتي', 'AED', '+971', '🇦🇪', '2026-06-13 17:36:49'),
(3, 'دولة الكويت', 'Kuwait', 'دينار كويتي', 'KWD', '+965', '🇰🇼', '2026-06-13 17:36:49'),
(4, 'مملكة البحرين', 'Bahrain', 'دينار بحريني', 'BHD', '+973', '🇧🇭', '2026-06-13 17:36:49'),
(5, 'دولة قطر', 'Qatar', 'ريال قطري', 'QAR', '+974', '🇶🇦', '2026-06-13 17:36:49'),
(6, 'سلطنة عمان', 'Oman', 'ريال عماني', 'OMR', '+968', '🇴🇲', '2026-06-13 17:36:49'),
(7, 'المملكة الأردنية الهاشمية', 'Jordan', 'دينار أردني', 'JOD', '+962', '🇯🇴', '2026-06-13 17:36:49');

-- --------------------------------------------------------

--
-- Table structure for table `employer_alerts`
--

CREATE TABLE `employer_alerts` (
  `id` int(11) NOT NULL,
  `employer_id` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `alert_sound` tinyint(1) DEFAULT 1,
  `alert_email` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `employer_alerts`
--

INSERT INTO `employer_alerts` (`id`, `employer_id`, `is_active`, `alert_sound`, `alert_email`, `created_at`, `updated_at`) VALUES
(1, 55, 1, 0, 0, '2026-06-20 03:08:17', '2026-06-20 04:53:55'),
(2, 1, 1, 1, 1, '2026-06-20 03:57:28', '2026-06-20 03:57:28'),
(3, 56, 1, 1, 1, '2026-06-20 11:31:21', '2026-06-20 11:31:21');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `project_id` int(11) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `link`, `is_read`, `created_at`) VALUES
(385, 55, '✅ تمت الموافقة على مشروعك!', 'تمت الموافقة الإدارية على مشروعك: بناء عمارة', 'success', 'http://www.quantity-pro.gt.tc/project_detail.php?id=85', 1, '2026-06-20 01:11:55'),
(386, 57, 'تمت الموافقة الإدارية على عطائك', 'تمت الموافقة المبدئية على عطائك في مشروع: بناء عمارة', 'info', 'http://www.quantity-pro.gt.tc/project_detail.php?id=85', 1, '2026-06-20 01:15:13'),
(387, 55, '📩 عطاء جديد يحتاج موافقتك', 'هناك عطاء جديد من المقاول احمد محمد على مشروعك: بناء عمارة', 'warning', 'http://www.quantity-pro.gt.tc/project_detail.php?id=85', 1, '2026-06-20 01:15:13'),
(388, 57, '✅ تم قبول عطائك نهائياً', 'صاحب العمل احمد قبل عطائك على مشروع: بناء عمارة بمبلغ 150,000 ر.س', 'bid', 'http://www.quantity-pro.gt.tc/my_bids.php', 1, '2026-06-20 01:15:46'),
(389, 55, '✅ تم إنشاء غرفة العمل', 'تم إنشاء غرفة العمل للمشروع: بناء عمارة', 'success', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=23', 1, '2026-06-20 01:15:46'),
(390, 57, '✅ تم إنشاء غرفة العمل', 'تم إنشاء غرفة العمل للمشروع: بناء عمارة', 'success', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=23', 1, '2026-06-20 01:15:46'),
(391, 55, '✅ تمت الموافقة على مشروعك!', 'تمت الموافقة الإدارية على مشروعك: 555', 'success', 'http://www.quantity-pro.gt.tc/project_detail.php?id=86', 1, '2026-06-20 01:22:33'),
(392, 57, 'تحديث حالة التوثيق', '🎉 تم توثيق حسابك كمقاول معتمد!', 'success', '', 1, '2026-06-20 01:24:54'),
(393, 57, 'تحديث حالة التمييز', '⭐ تم ترقية حسابك إلى مميز', 'info', '', 1, '2026-06-20 01:26:46'),
(394, 57, 'تمت الموافقة الإدارية على عطائك', 'تمت الموافقة المبدئية على عطائك في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_detail.php?id=86', 1, '2026-06-20 01:37:31'),
(395, 55, '📩 عطاء جديد يحتاج موافقتك', 'هناك عطاء جديد من المقاول احمد محمد على مشروعك: 555', 'warning', 'http://www.quantity-pro.gt.tc/project_detail.php?id=86', 1, '2026-06-20 01:37:31'),
(396, 57, '✅ تم قبول عطائك نهائياً', 'صاحب العمل احمد قبل عطائك على مشروع: 555 بمبلغ 50,000 ر.س', 'bid', 'http://www.quantity-pro.gt.tc/my_bids.php', 1, '2026-06-20 01:37:45'),
(397, 55, '✅ تم إنشاء غرفة العمل', 'تم إنشاء غرفة العمل للمشروع: 555', 'success', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 01:37:45'),
(398, 57, '✅ تم إنشاء غرفة العمل', 'تم إنشاء غرفة العمل للمشروع: 555', 'success', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 01:37:45'),
(399, 57, '📋 مطالبة جديدة في المشروع', 'احمد قدم مطالبة: اتاتننتتنن في مشروع: بناء عمارة', 'warning', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=23', 1, '2026-06-20 02:23:22'),
(400, 55, '✅ تم قبول مطالبتك', 'تم قبول مطالبتك: اتاتننتتنن في مشروع: بناء عمارة', 'success', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=23', 1, '2026-06-20 02:23:58'),
(401, 57, '📋 مطالبة جديدة في المشروع', 'احمد قدم مطالبة: صرف بدل تعطل في مشروع: 555', 'warning', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 02:36:15'),
(402, 55, '❌ تم رفض مطالبتك', 'تم رفض مطالبتك: صرف بدل تعطل في مشروع: 555 | تعليق: مرفوض', 'error', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 02:36:44'),
(403, 55, '✅ تمت الموافقة على مشروعك!', 'تمت الموافقة الإدارية على مشروعك: بناء 2222', 'success', 'http://www.quantity-pro.gt.tc/project_detail.php?id=87', 1, '2026-06-20 02:42:43'),
(404, 57, '🔔 مشروع جديد في منطقتك', 'تم نشر مشروع جديد: بناء 2222', 'info', 'http://www.quantity-pro.gt.tc/project_detail.php?id=87', 1, '2026-06-20 02:42:43'),
(405, 57, 'تمت الموافقة الإدارية على عطائك', 'تمت الموافقة المبدئية على عطائك في مشروع: بناء 2222', 'info', 'http://www.quantity-pro.gt.tc/project_detail.php?id=87', 1, '2026-06-20 02:44:04'),
(406, 55, '📩 عطاء جديد يحتاج موافقتك', 'هناك عطاء جديد من المقاول احمد محمد على مشروعك: بناء 2222', 'warning', 'http://www.quantity-pro.gt.tc/project_detail.php?id=87', 1, '2026-06-20 02:44:04'),
(407, 57, '📋 مطالبة جديدة في المشروع', 'احمد قدم مطالبة: تنانات في مشروع: 555', 'warning', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 03:10:09'),
(408, 57, '❌ تم رفض عطائك', 'صاحب العمل احمد رفض عطائك على مشروع: بناء 2222 بمبلغ 6,655 ر.س', 'bid', 'http://www.quantity-pro.gt.tc/my_bids.php', 1, '2026-06-20 03:32:35'),
(409, 57, '📋 مطالبة جديدة في المشروع', 'احمد قدم مطالبة: ؤؤؤؤؤؤ في مشروع: 555', 'warning', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 03:40:13'),
(410, 55, '✅ تم قبول مطالبتك', 'تم قبول مطالبتك: ؤؤؤؤؤؤ في مشروع: 555', 'success', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 03:46:19'),
(411, 55, '✅ تم قبول مطالبتك', 'تم قبول مطالبتك: تنانات في مشروع: 555', 'success', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 03:46:23'),
(412, 55, '📋 مطالبة جديدة في المشروع', 'احمد محمد قدم مطالبة: Hvffhh في مشروع: 555', 'warning', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 03:47:06'),
(413, 55, '📋 مطالبة جديدة في المشروع', 'احمد محمد قدم مطالبة: Hvffhh في مشروع: 555', 'warning', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 03:47:21'),
(414, 57, '✅ تم قبول مطالبتك', 'تم قبول مطالبتك: Hvffhh في مشروع: 555', 'success', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 03:50:32'),
(415, 57, '✅ تم قبول مطالبتك', 'تم قبول مطالبتك: Hvffhh في مشروع: 555', 'success', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 03:50:46'),
(416, 55, 'ااالات', 'ااتلتالات', 'info', 'http://www.quantity-pro.gt.tc', 1, '2026-06-20 03:51:11'),
(417, 56, 'ااالات', 'ااتلتالات', 'info', 'http://www.quantity-pro.gt.tc', 1, '2026-06-20 03:51:11'),
(418, 57, 'ااالات', 'ااتلتالات', 'info', 'http://www.quantity-pro.gt.tc', 1, '2026-06-20 03:51:11'),
(419, 55, 'بفللبللبلبللللب', 'لغعلغعلغعلغع', 'info', 'http://www.quantity-pro.gt.tc', 1, '2026-06-20 03:51:44'),
(420, 56, 'بفللبللبلبللللب', 'لغعلغعلغعلغع', 'info', 'http://www.quantity-pro.gt.tc', 1, '2026-06-20 03:51:44'),
(421, 57, 'بفللبللبلبللللب', 'لغعلغعلغعلغع', 'info', 'http://www.quantity-pro.gt.tc', 1, '2026-06-20 03:51:44'),
(422, 57, '💬 رسالة جديدة في غرفة العمل', 'احمد أرسل رسالة في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 03:53:07'),
(423, 55, '💬 رسالة جديدة في غرفة العمل', 'احمد محمد أرسل رسالة في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 03:53:34'),
(424, 55, '💬 رسالة جديدة في غرفة العمل', 'احمد محمد أرسل رسالة في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 03:53:56'),
(425, 55, '💬 رسالة جديدة في غرفة العمل', 'احمد محمد أرسل رسالة في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 03:54:20'),
(426, 57, '💬 رسالة جديدة في غرفة العمل', 'احمد أرسل رسالة في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 03:54:50'),
(427, 57, '💬 رسالة جديدة في غرفة العمل', 'احمد أرسل رسالة في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 03:55:16'),
(428, 55, '💬 رسالة جديدة في غرفة العمل', 'احمد محمد أرسل رسالة في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 03:58:16'),
(429, 57, '💬 رسالة جديدة في غرفة العمل', 'احمد أرسل رسالة في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 03:58:37'),
(430, 57, '💬 رسالة جديدة في غرفة العمل', 'احمد أرسل رسالة في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 04:02:03'),
(431, 57, '💬 رسالة جديدة في غرفة العمل', 'احمد أرسل رسالة في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 04:02:39'),
(432, 57, '💬 رسالة جديدة في غرفة العمل', 'احمد أرسل رسالة في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 04:05:25'),
(433, 55, '💬 رسالة جديدة في غرفة العمل', 'احمد محمد أرسل رسالة في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 04:05:39'),
(434, 55, '💬 رسالة جديدة في غرفة العمل', 'احمد محمد أرسل رسالة في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 04:06:35'),
(435, 55, '💬 رسالة جديدة في غرفة العمل', 'احمد محمد أرسل رسالة في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 04:07:04'),
(436, 55, '💬 رسالة جديدة في غرفة العمل', 'احمد محمد أرسل رسالة في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 04:07:13'),
(437, 55, '💬 رسالة جديدة في غرفة العمل', 'احمد محمد أرسل رسالة في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 04:07:32'),
(438, 55, '💬 رسالة جديدة في غرفة العمل', 'احمد محمد أرسل رسالة في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 04:07:48'),
(439, 55, '💬 رسالة جديدة في غرفة العمل', 'احمد محمد أرسل رسالة في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 04:07:59'),
(440, 57, '💬 رسالة جديدة في غرفة العمل', 'احمد أرسل رسالة في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 04:09:16'),
(441, 55, 'dvvdvd', 'effefef', 'info', 'http://www.quantity-pro.gt.tc', 1, '2026-06-20 04:13:31'),
(442, 56, 'dvvdvd', 'effefef', 'info', 'http://www.quantity-pro.gt.tc', 1, '2026-06-20 04:13:31'),
(443, 57, 'dvvdvd', 'effefef', 'info', 'http://www.quantity-pro.gt.tc', 1, '2026-06-20 04:13:31'),
(444, 55, 'hjbhjbhjb', 'jhbhjbhjbhj', 'info', 'http://www.quantity-pro.gt.tc', 1, '2026-06-20 04:14:04'),
(445, 56, 'hjbhjbhjb', 'jhbhjbhjbhj', 'info', 'http://www.quantity-pro.gt.tc', 1, '2026-06-20 04:14:04'),
(446, 57, 'hjbhjbhjb', 'jhbhjbhjbhj', 'info', 'http://www.quantity-pro.gt.tc', 1, '2026-06-20 04:14:04'),
(447, 55, 'cvcvdvc', 'dddc', 'info', 'http://www.quantity-pro.gt.tc', 1, '2026-06-20 04:14:19'),
(448, 56, 'cvcvdvc', 'dddc', 'info', 'http://www.quantity-pro.gt.tc', 1, '2026-06-20 04:14:19'),
(449, 57, 'cvcvdvc', 'dddc', 'info', 'http://www.quantity-pro.gt.tc', 1, '2026-06-20 04:14:19'),
(450, 55, 'ييبي', 'قبقبثي', 'info', 'http://www.quantity-pro.gt.tc', 1, '2026-06-20 04:15:04'),
(451, 56, 'ييبي', 'قبقبثي', 'info', 'http://www.quantity-pro.gt.tc', 1, '2026-06-20 04:15:04'),
(452, 57, 'ييبي', 'قبقبثي', 'info', 'http://www.quantity-pro.gt.tc', 1, '2026-06-20 04:15:04'),
(453, 55, 'ققبببي', 'بيبيببي', 'info', 'http://www.quantity-pro.gt.tc', 1, '2026-06-20 04:15:25'),
(454, 56, 'ققبببي', 'بيبيببي', 'info', 'http://www.quantity-pro.gt.tc', 1, '2026-06-20 04:15:25'),
(455, 57, 'ققبببي', 'بيبيببي', 'info', 'http://www.quantity-pro.gt.tc', 1, '2026-06-20 04:15:25'),
(456, 55, 'يبيبيب', 'يبيبيب', 'info', 'http://www.quantity-pro.gt.tc', 1, '2026-06-20 04:15:38'),
(457, 56, 'يبيبيب', 'يبيبيب', 'info', 'http://www.quantity-pro.gt.tc', 1, '2026-06-20 04:15:38'),
(458, 57, 'يبيبيب', 'يبيبيب', 'info', 'http://www.quantity-pro.gt.tc', 1, '2026-06-20 04:15:38'),
(459, 55, 'يبيبيبييبي', 'سيببيبي', 'info', 'http://www.quantity-pro.gt.tc', 1, '2026-06-20 04:17:04'),
(460, 56, 'يبيبيبييبي', 'سيببيبي', 'info', 'http://www.quantity-pro.gt.tc', 1, '2026-06-20 04:17:04'),
(461, 57, 'يبيبيبييبي', 'سيببيبي', 'info', 'http://www.quantity-pro.gt.tc', 1, '2026-06-20 04:17:04'),
(462, 55, 'تاتتات', 'تلغلتات', 'info', 'http://www.quantity-pro.gt.tc', 1, '2026-06-20 04:17:31'),
(463, 56, 'تاتتات', 'تلغلتات', 'info', 'http://www.quantity-pro.gt.tc', 1, '2026-06-20 04:17:31'),
(464, 57, 'تاتتات', 'تلغلتات', 'info', 'http://www.quantity-pro.gt.tc', 1, '2026-06-20 04:17:31'),
(465, 55, 'تاتتناتناتن', 'تانتناتناتن', 'info', 'http://www.quantity-pro.gt.tc', 1, '2026-06-20 04:21:54'),
(466, 56, 'تاتتناتناتن', 'تانتناتناتن', 'info', 'http://www.quantity-pro.gt.tc', 1, '2026-06-20 04:21:54'),
(467, 57, 'تاتتناتناتن', 'تانتناتناتن', 'info', 'http://www.quantity-pro.gt.tc', 1, '2026-06-20 04:21:54'),
(468, 55, 'تناعناناع', 'تاتاعتا', 'info', 'http://www.quantity-pro.gt.tc', 1, '2026-06-20 04:22:34'),
(469, 56, 'تناعناناع', 'تاتاعتا', 'info', 'http://www.quantity-pro.gt.tc', 1, '2026-06-20 04:22:34'),
(470, 57, 'تناعناناع', 'تاتاعتا', 'info', 'http://www.quantity-pro.gt.tc', 1, '2026-06-20 04:22:34'),
(471, 55, 'مهتهتهنتنت', 'نانعانتانت', 'info', 'http://www.quantity-pro.gt.tc', 1, '2026-06-20 04:22:55'),
(472, 56, 'مهتهتهنتنت', 'نانعانتانت', 'info', 'http://www.quantity-pro.gt.tc', 1, '2026-06-20 04:22:55'),
(473, 57, 'مهتهتهنتنت', 'نانعانتانت', 'info', 'http://www.quantity-pro.gt.tc', 1, '2026-06-20 04:22:55'),
(474, 57, '💬 رسالة جديدة في غرفة العمل', 'احمد أرسل رسالة في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 04:26:07'),
(475, 57, '💬 رسالة جديدة في غرفة العمل', 'احمد أرسل رسالة في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 04:26:29'),
(476, 57, '💬 رسالة جديدة في غرفة العمل', 'احمد أرسل رسالة في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 04:27:33'),
(477, 57, '💬 رسالة جديدة في غرفة العمل', 'احمد أرسل رسالة في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 04:27:55'),
(478, 55, '💬 رسالة جديدة في غرفة العمل', 'احمد محمد أرسل رسالة في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 04:33:21'),
(479, 55, '💬 رسالة جديدة في غرفة العمل', 'احمد محمد أرسل رسالة في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 04:34:05'),
(480, 55, '💬 رسالة جديدة في غرفة العمل', 'احمد محمد أرسل رسالة في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 04:35:19'),
(481, 55, '💬 رسالة جديدة في غرفة العمل', 'احمد محمد أرسل رسالة في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 04:35:39'),
(482, 57, '💬 رسالة جديدة في غرفة العمل', 'احمد أرسل رسالة في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 04:38:53'),
(483, 57, '💬 رسالة جديدة في غرفة العمل', 'احمد أرسل رسالة في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 04:39:42'),
(484, 57, '💬 رسالة جديدة في غرفة العمل', 'احمد أرسل رسالة في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 04:47:54'),
(485, 57, '💬 رسالة جديدة في غرفة العمل', 'احمد أرسل رسالة في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 04:48:19'),
(486, 57, '💬 رسالة جديدة في غرفة العمل', 'احمد أرسل رسالة في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 04:48:30'),
(487, 57, '💬 رسالة جديدة في غرفة العمل', 'احمد أرسل رسالة في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 04:51:33'),
(488, 57, '💬 رسالة جديدة في غرفة العمل', 'احمد أرسل رسالة في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 04:51:45'),
(489, 57, '💬 رسالة جديدة في غرفة العمل', 'احمد أرسل رسالة في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 04:52:10'),
(490, 57, '💬 رسالة جديدة في غرفة العمل', 'احمد أرسل رسالة في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 04:52:31'),
(491, 57, '💬 رسالة جديدة في غرفة العمل', 'احمد أرسل رسالة في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 04:52:47'),
(492, 57, '💬 رسالة جديدة في غرفة العمل', 'احمد أرسل رسالة في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 04:52:54'),
(493, 57, '💬 رسالة جديدة في غرفة العمل', 'احمد أرسل رسالة في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 04:53:05'),
(494, 55, '💬 رسالة جديدة في غرفة العمل', 'احمد محمد أرسل رسالة في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 04:53:31'),
(495, 55, '💬 رسالة جديدة في غرفة العمل', 'احمد محمد أرسل رسالة في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 04:53:44'),
(496, 55, '💬 رسالة جديدة في غرفة العمل', 'احمد محمد أرسل رسالة في مشروع: 555', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=24', 1, '2026-06-20 04:53:59'),
(497, 58, 'تمت الموافقة الإدارية على عطائك', 'تمت الموافقة المبدئية على عطائك في مشروع: بناء 2222', 'info', 'http://www.quantity-pro.gt.tc/project_detail.php?id=87', 1, '2026-06-20 11:32:46'),
(498, 55, '📩 عطاء جديد يحتاج موافقتك', 'هناك عطاء جديد من المقاول Ghadeer على مشروعك: بناء 2222', 'warning', 'http://www.quantity-pro.gt.tc/project_detail.php?id=87', 1, '2026-06-20 11:32:46'),
(499, 58, '🏗️ تم فتح غرفة عمل للمشروع', 'صاحب العمل احمد فتح غرفة عمل لمشروع: بناء 2222', 'success', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=25', 1, '2026-06-20 11:35:24'),
(500, 58, '🎉 تم قبول عطائك نهائياً!', 'صاحب العمل احمد قبل عطائك نهائياً على مشروع: بناء 2222 - تم نقل المشروع إلى قسم المشاريع المحالة قيد التنفيذ', 'success', 'http://www.quantity-pro.gt.tc/my_bids.php', 1, '2026-06-20 11:35:24'),
(501, 58, '💬 رسالة جديدة في غرفة العمل', 'احمد أرسل رسالة في مشروع: بناء 2222', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=25', 1, '2026-06-20 11:35:59'),
(502, 58, '💬 رسالة جديدة في غرفة العمل', 'احمد أرسل رسالة في مشروع: بناء 2222', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=25', 1, '2026-06-20 11:36:30'),
(503, 55, '💬 رسالة جديدة في غرفة العمل', 'Ghadeer أرسل رسالة في مشروع: بناء 2222', 'info', 'http://www.quantity-pro.gt.tc/project_workspace.php?id=25', 1, '2026-06-20 11:37:12');

-- --------------------------------------------------------

--
-- Table structure for table `otp_codes`
--

CREATE TABLE `otp_codes` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `code` varchar(10) NOT NULL,
  `type` varchar(50) DEFAULT 'register',
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `otp_codes`
--

INSERT INTO `otp_codes` (`id`, `email`, `code`, `type`, `expires_at`, `created_at`) VALUES
(1, 'albdour.abed@gmail.com', '846635', 'register', '2026-06-15 13:40:20', '2026-06-15 20:35:21'),
(2, 'MAPYABOOD@GMAIL.COM', '110858', 'register', '2026-06-15 13:40:31', '2026-06-15 20:35:32'),
(3, 'mapyccvte@gmail.com', '150916', 'register', '2026-06-15 13:41:32', '2026-06-15 20:36:33'),
(4, 'mapyccvte@gmail.com', '328087', 'register', '2026-06-15 13:41:44', '2026-06-15 20:36:45'),
(5, 'mapyccvte@gmail.com', '672561', 'register', '2026-06-15 13:44:35', '2026-06-15 20:39:36'),
(6, 'mapyccvte@gmail.com', '928905', 'register', '2026-06-15 13:45:06', '2026-06-15 20:40:07'),
(7, 'mapyccvte@gmail.com', '613556', 'register', '2026-06-15 13:48:49', '2026-06-15 20:43:50');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `shop_id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(15,2) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `is_main` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `employer_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `category` varchar(100) NOT NULL,
  `budget_min` decimal(15,2) DEFAULT NULL,
  `budget_max` decimal(15,2) DEFAULT NULL,
  `country_id` int(11) DEFAULT NULL,
  `city_id` int(11) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` enum('pending','open','in_progress','completed','cancelled') DEFAULT 'pending',
  `deadline` date DEFAULT NULL,
  `views` int(11) DEFAULT 0,
  `bids_count` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `progress` decimal(5,2) DEFAULT 0.00,
  `total_price` decimal(15,2) DEFAULT 0.00,
  `end_date` date DEFAULT NULL,
  `start_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `employer_id`, `title`, `description`, `category`, `budget_min`, `budget_max`, `country_id`, `city_id`, `address`, `status`, `deadline`, `views`, `bids_count`, `created_at`, `updated_at`, `progress`, `total_price`, `end_date`, `start_date`) VALUES
(85, 55, 'بناء عمارة', 'بناء عمارة مساحة 1500 متر', 'بناء', NULL, NULL, 7, 57, 'يةيبينبيبنةي', 'in_progress', '2026-06-28', 0, 0, '2026-06-20 01:11:18', '2026-06-20 01:15:46', '0.00', '0.00', NULL, NULL),
(86, 55, '555', 'منممن', 'أرضيات', '50000.00', '60000.00', 4, NULL, '', 'in_progress', NULL, 0, 0, '2026-06-20 01:22:19', '2026-06-20 01:37:45', '0.00', '0.00', NULL, NULL),
(87, 55, 'بناء 2222', 'تتتتتتتت', 'بناء', '10000.00', '20000.00', 7, 60, 'يةيبينبيبنةي', 'in_progress', NULL, 0, 0, '2026-06-20 02:42:26', '2026-06-20 11:35:24', '0.00', '0.00', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `project_attachments`
--

CREATE TABLE `project_attachments` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_size` int(11) NOT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `file_ext` varchar(20) DEFAULT NULL,
  `uploaded_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_attachments`
--

INSERT INTO `project_attachments` (`id`, `project_id`, `filename`, `original_name`, `file_size`, `file_type`, `file_ext`, `uploaded_at`) VALUES
(29, 85, 'project_85_1781917879_0.pdf', 'gfgfgf.pdf', 1719510, 'application/pdf', 'pdf', '2026-06-20 04:11:18'),
(30, 85, 'project_85_1781917879_1.png', 'ReportTester-106753.png', 7023, 'image/png', 'png', '2026-06-20 04:11:18');

-- --------------------------------------------------------

--
-- Table structure for table `project_files`
--

CREATE TABLE `project_files` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `bid_id` int(11) NOT NULL,
  `uploader_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_size` int(11) NOT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `file_ext` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `uploaded_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `project_images`
--

CREATE TABLE `project_images` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `is_main` tinyint(1) DEFAULT 0,
  `uploaded_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `project_images`
--

INSERT INTO `project_images` (`id`, `project_id`, `image_path`, `is_main`, `uploaded_at`) VALUES
(36, 85, 'assets/uploads/projects/main_85_1781917879.jpg', 1, '2026-06-20 04:11:18');

-- --------------------------------------------------------

--
-- Table structure for table `project_workspace`
--

CREATE TABLE `project_workspace` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `bid_id` int(11) NOT NULL,
  `employer_id` int(11) NOT NULL,
  `contractor_id` int(11) NOT NULL,
  `status` enum('active','completed','on_hold') DEFAULT 'active',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_workspace`
--

INSERT INTO `project_workspace` (`id`, `project_id`, `bid_id`, `employer_id`, `contractor_id`, `status`, `created_at`, `updated_at`) VALUES
(23, 85, 41, 55, 57, 'active', '2026-06-20 04:15:46', NULL),
(24, 86, 42, 55, 57, 'active', '2026-06-20 04:37:45', '2026-06-20 07:53:59'),
(25, 87, 44, 55, 58, 'active', '2026-06-20 14:35:24', '2026-06-20 14:37:12');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--
-- ✅ تم التعديل هنا: إضافة AUTO_INCREMENT و PRIMARY KEY
CREATE TABLE `reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reviewer_id` int(11) NOT NULL,
  `reviewed_id` int(11) NOT NULL,
  `project_id` int(11) DEFAULT NULL,
  `rating` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shops`
--

CREATE TABLE `shops` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `shop_name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `address` text DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(10,8) DEFAULT NULL,
  `country_id` int(11) DEFAULT NULL,
  `city_id` int(11) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `whatsapp` varchar(20) DEFAULT NULL,
  `working_hours` varchar(100) DEFAULT NULL,
  `rating` decimal(3,2) DEFAULT 0.00,
  `status` enum('active','suspended','pending') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `user_type` enum('employer','contractor','shop','admin') NOT NULL DEFAULT 'employer',
  `contractor_class` enum('A','B','C','D','pending') DEFAULT 'pending',
  `verification_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `verification_date` datetime DEFAULT NULL,
  `verification_expiry` datetime DEFAULT NULL,
  `employer_verification_status` enum('pending','approved','rejected','none') DEFAULT 'none',
  `employer_verification_date` datetime DEFAULT NULL,
  `is_featured` tinyint(1) DEFAULT 0,
  `admin_notes` text DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `featured_until` date DEFAULT NULL,
  `verification_reject_reason` text DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `entity_type` enum('individual','company') DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `whatsapp` varchar(20) DEFAULT NULL,
  `country_id` int(11) DEFAULT NULL,
  `city_id` int(11) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT 'default-avatar.png',
  `bio` text DEFAULT NULL,
  `classification` varchar(50) DEFAULT NULL,
  `rating` decimal(3,2) DEFAULT 0.00,
  `reviews_count` int(11) DEFAULT 0,
  `is_verified` tinyint(1) DEFAULT 0,
  `status` enum('active','suspended','pending') DEFAULT 'active',
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expiry` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` datetime DEFAULT NULL,
  `remember_token` varchar(255) DEFAULT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `otp_code` varchar(10) DEFAULT NULL,
  `otp_expires` datetime DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `user_type`, `contractor_class`, `verification_status`, `verification_date`, `verification_expiry`, `employer_verification_status`, `employer_verification_date`, `is_featured`, `admin_notes`, `verified_by`, `featured_until`, `verification_reject_reason`, `verified_at`, `entity_type`, `phone`, `whatsapp`, `country_id`, `city_id`, `avatar`, `bio`, `classification`, `rating`, `reviews_count`, `is_verified`, `status`, `reset_token`, `reset_expiry`, `created_at`, `updated_at`, `last_login`, `remember_token`, `email_verified_at`, `otp_code`, `otp_expires`, `cover_image`, `address`) VALUES
(1, 'مدير النظام', 'admin@bidders.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'pending', 'pending', NULL, NULL, 'none', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'default-avatar.png', NULL, NULL, '0.00', 0, 1, 'active', NULL, NULL, '2026-06-13 17:36:49', '2026-06-19 12:40:12', '2026-06-19 15:40:12', 'f990634d055b7a658e31db7b02b1213e3474d7292a08a21368b463ea4f57fed1', NULL, NULL, NULL, NULL, NULL),
(55, 'احمد', 'albdour.abed@gmail.com', '$2y$10$jdk.VBUzVgCTS0IxVUmGmexD6GhdckTgo5kpo4sCAsybXUXILILOa', 'employer', 'pending', 'pending', NULL, NULL, 'none', NULL, 0, NULL, NULL, NULL, NULL, NULL, 'company', '556656565', '65455544', 7, 66, 'assets/uploads/avatars/user_55_1781924146.jpg', '', NULL, '0.00', 0, 0, 'active', NULL, NULL, '2026-06-20 01:07:21', '2026-06-20 11:34:56', '2026-06-20 14:34:56', NULL, NULL, NULL, NULL, 'assets/uploads/covers/cover_55_1781924146.png', ''),
(56, 'محمد', 'mapyabd@gmail.com', '$2y$10$W5Fymfg3p8SrfKcPB1dBJ.LUaFScYLBIXDYk2idq95suysmsXlJDW', 'employer', 'pending', 'pending', NULL, NULL, 'none', NULL, 0, NULL, NULL, NULL, NULL, NULL, 'company', '659595', '944949', 7, 64, 'default-avatar.png', NULL, NULL, '0.00', 0, 0, 'active', NULL, NULL, '2026-06-20 01:08:53', '2026-06-20 11:31:21', '2026-06-20 14:31:21', NULL, NULL, NULL, NULL, NULL, NULL),
(57, 'احمد محمد', 'mapyabood@gmail.com', '$2y$10$VUrXlT/c3KB5yWRZyUWfwOUR.zFbW1v0JvZ9IoTszIOGlmWyV15iO', 'contractor', 'A', 'approved', '2026-06-20 04:24:54', '2027-06-20 04:24:54', 'none', NULL, 1, NULL, NULL, NULL, NULL, NULL, 'company', '989779797', '65565959', 7, 62, 'default-avatar.png', NULL, NULL, '0.00', 0, 0, 'active', NULL, NULL, '2026-06-20 01:09:59', '2026-06-20 04:12:30', '2026-06-20 07:12:30', NULL, NULL, NULL, NULL, NULL, NULL),
(58, 'Ghadeer', 'galbdour90@gmail.com', '$2y$10$Kkvl7T9O09DXOYgRHqynQeEMoxV1EeplST5Qvs6KPJk91nqU5Qoq6', 'contractor', 'pending', 'pending', NULL, NULL, 'none', NULL, 0, NULL, NULL, NULL, NULL, NULL, 'individual', '0797890097', '', 7, 59, 'default-avatar.png', NULL, NULL, '0.00', 0, 0, 'active', NULL, NULL, '2026-06-20 11:30:57', '2026-06-20 11:36:50', '2026-06-20 14:36:50', 'cc0d08600c270e06e1141b9149ae9b54746e56d6871d836a400d865bbc42bc5a', NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `workspace_activity`
--

CREATE TABLE `workspace_activity` (
  `id` int(11) NOT NULL,
  `workspace_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `workspace_change_orders`
--

CREATE TABLE `workspace_change_orders` (
  `id` int(11) NOT NULL,
  `workspace_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `additional_cost` decimal(15,2) DEFAULT NULL,
  `additional_days` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `response_comment` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  `responded_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `workspace_claims`
--

CREATE TABLE `workspace_claims` (
  `id` int(11) NOT NULL,
  `workspace_id` int(11) NOT NULL,
  `claimed_by` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `amount` decimal(15,2) DEFAULT NULL,
  `status` enum('pending','approved','rejected','paid') DEFAULT 'pending',
  `response_comment` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  `responded_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `workspace_claims`
--

INSERT INTO `workspace_claims` (`id`, `workspace_id`, `claimed_by`, `title`, `description`, `amount`, `status`, `response_comment`, `file_path`, `file_name`, `created_at`, `updated_at`, `responded_at`) VALUES
(6, 23, 55, 'اتاتننتتنن', 'لااتتتنانت', '565665.00', 'approved', '', NULL, NULL, '2026-06-20 05:23:22', '2026-06-20 05:23:58', '2026-06-20 05:23:58'),
(7, 24, 55, 'صرف بدل تعطل', 'صرف', '2500.00', 'rejected', 'مرفوض', 'assets/uploads/workspace_claims/claim_24_1781922974_9bfad25d.png', 'غغغغغ.png', '2026-06-20 05:36:15', '2026-06-20 05:36:44', '2026-06-20 05:36:44'),
(8, 24, 55, 'تنانات', 'الات', '2000.00', 'approved', '', NULL, NULL, '2026-06-20 06:10:09', '2026-06-20 06:46:23', '2026-06-20 06:46:23'),
(9, 24, 55, 'ؤؤؤؤؤؤ', 'ىنتىن', '500.00', 'approved', '', NULL, NULL, '2026-06-20 06:40:13', '2026-06-20 06:46:19', '2026-06-20 06:46:19'),
(10, 24, 57, 'Hvffhh', 'Bfgbv', '69.00', 'approved', '', NULL, NULL, '2026-06-20 06:47:06', '2026-06-20 06:50:46', '2026-06-20 06:50:46'),
(11, 24, 57, 'Hvffhh', 'Bfgbv', '69.00', 'approved', '', NULL, NULL, '2026-06-20 06:47:21', '2026-06-20 06:50:32', '2026-06-20 06:50:32');

-- --------------------------------------------------------

--
-- Table structure for table `workspace_clarifications`
--

CREATE TABLE `workspace_clarifications` (
  `id` int(11) NOT NULL,
  `workspace_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `file_path` varchar(500) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `status` enum('pending','answered','rejected') DEFAULT 'pending',
  `response_comment` text DEFAULT NULL,
  `responded_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `workspace_deliveries`
--

CREATE TABLE `workspace_deliveries` (
  `id` int(11) NOT NULL,
  `workspace_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `delivery_percentage` decimal(5,2) NOT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `response_comment` text DEFAULT NULL,
  `responded_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `workspace_materials`
--

CREATE TABLE `workspace_materials` (
  `id` int(11) NOT NULL,
  `workspace_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `material_type` varchar(100) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `response_comment` text DEFAULT NULL,
  `responded_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `workspace_messages`
--

CREATE TABLE `workspace_messages` (
  `id` int(11) NOT NULL,
  `workspace_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `message_type` enum('text','file','claim','change_order','status_update') DEFAULT 'text',
  `file_path` varchar(255) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `workspace_messages`
--

INSERT INTO `workspace_messages` (`id`, `workspace_id`, `sender_id`, `message`, `message_type`, `file_path`, `file_name`, `is_read`, `created_at`) VALUES
(24, 24, 55, 'كيفك شو اخبارك ؟؟', 'text', NULL, NULL, 0, '2026-06-20 06:53:07'),
(25, 24, 57, 'تمام انت كيفك', 'text', NULL, NULL, 0, '2026-06-20 06:53:34'),
(26, 24, 57, 'شو في ما في', 'text', NULL, NULL, 0, '2026-06-20 06:53:56'),
(27, 24, 57, 'اليوم رنيتلك بس ما رديت', 'text', NULL, NULL, 0, '2026-06-20 06:54:20'),
(28, 24, 55, 'تننتنىتنى', 'text', NULL, NULL, 0, '2026-06-20 06:54:50'),
(29, 24, 55, 'تننتنىتنىتاتنتناتن', 'text', NULL, NULL, 0, '2026-06-20 06:55:16'),
(30, 24, 57, 'كيفك', 'text', NULL, NULL, 0, '2026-06-20 06:58:16'),
(31, 24, 55, 'تمام', 'text', NULL, NULL, 0, '2026-06-20 06:58:37'),
(32, 24, 55, 'ببيربير', 'text', NULL, NULL, 0, '2026-06-20 07:02:03'),
(33, 24, 55, 'تاتنات', 'text', NULL, NULL, 0, '2026-06-20 07:02:39'),
(34, 24, 55, 'تاتنبتنرتبنر', 'text', NULL, NULL, 0, '2026-06-20 07:05:25'),
(35, 24, 57, 'ويويوي', 'text', NULL, NULL, 0, '2026-06-20 07:05:39'),
(36, 24, 57, 'تااتاا', 'text', NULL, NULL, 0, '2026-06-20 07:06:35'),
(37, 24, 57, 'نرنبنر', 'text', NULL, NULL, 0, '2026-06-20 07:07:04'),
(38, 24, 57, 'نينيبنني', 'text', NULL, NULL, 0, '2026-06-20 07:07:13'),
(39, 24, 57, 'زينيويز', 'text', NULL, NULL, 0, '2026-06-20 07:07:32'),
(40, 24, 57, 'نينيتيتي', 'text', NULL, NULL, 0, '2026-06-20 07:07:48'),
(41, 24, 57, 'ؤززؤؤويو', 'text', NULL, NULL, 0, '2026-06-20 07:07:59'),
(42, 24, 55, 'تناتناتن', 'text', NULL, NULL, 0, '2026-06-20 07:09:16'),
(43, 24, 55, 'Dnndnddn', 'text', NULL, NULL, 0, '2026-06-20 07:26:07'),
(44, 24, 55, 'Hdhdhdh', 'text', NULL, NULL, 0, '2026-06-20 07:26:29'),
(45, 24, 55, 'Hjhgjjg', 'text', NULL, NULL, 0, '2026-06-20 07:27:33'),
(46, 24, 55, 'Fuudcycyyf', 'text', NULL, NULL, 0, '2026-06-20 07:27:55'),
(47, 24, 57, 'للالاللالا', 'text', NULL, NULL, 0, '2026-06-20 07:33:21'),
(48, 24, 57, 'ىتنىتنىن', 'text', NULL, NULL, 0, '2026-06-20 07:34:05'),
(49, 24, 57, 'تالتلتع', 'text', NULL, NULL, 0, '2026-06-20 07:35:19'),
(50, 24, 57, 'الغعلغلع', 'text', NULL, NULL, 0, '2026-06-20 07:35:39'),
(51, 24, 55, 'Yfhhgg', 'text', NULL, NULL, 0, '2026-06-20 07:38:53'),
(52, 24, 55, 'Jfhfjcjccj', 'text', NULL, NULL, 0, '2026-06-20 07:39:42'),
(53, 24, 55, 'Jxndnd', 'text', NULL, NULL, 0, '2026-06-20 07:47:54'),
(54, 24, 55, 'Jdjdhd', 'text', NULL, NULL, 0, '2026-06-20 07:48:19'),
(55, 24, 55, 'Idjdjdjd', 'text', NULL, NULL, 0, '2026-06-20 07:48:30'),
(56, 24, 55, 'Jdjdhd', 'text', NULL, NULL, 0, '2026-06-20 07:51:33'),
(57, 24, 55, 'Jdjdhd', 'text', NULL, NULL, 0, '2026-06-20 07:51:45'),
(58, 24, 55, 'Nxndxjj', 'text', NULL, NULL, 0, '2026-06-20 07:52:10'),
(59, 24, 55, 'Jdjdh', 'text', NULL, NULL, 0, '2026-06-20 07:52:31'),
(60, 24, 55, 'Hgfhggg', 'text', NULL, NULL, 0, '2026-06-20 07:52:47'),
(61, 24, 55, 'Hhhjgg', 'text', NULL, NULL, 0, '2026-06-20 07:52:54'),
(62, 24, 55, 'Chghggvv', 'text', NULL, NULL, 0, '2026-06-20 07:53:05'),
(63, 24, 57, 'تىنتىنتى', 'text', NULL, NULL, 0, '2026-06-20 07:53:31'),
(64, 24, 57, 'تنتنلاتلاةىة', 'text', NULL, NULL, 0, '2026-06-20 07:53:44'),
(65, 24, 57, 'تلاتةلاتلاتةى', 'text', NULL, NULL, 0, '2026-06-20 07:53:59'),
(66, 25, 55, 'مرحبا', 'text', NULL, NULL, 0, '2026-06-20 14:35:59'),
(67, 25, 55, 'مرحبا', 'text', NULL, NULL, 0, '2026-06-20 14:36:30'),
(68, 25, 58, 'اهلا', 'text', NULL, NULL, 0, '2026-06-20 14:37:12');

-- --------------------------------------------------------

--
-- Table structure for table `workspace_milestones`
--

CREATE TABLE `workspace_milestones` (
  `id` int(11) NOT NULL,
  `workspace_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `due_date` date NOT NULL,
  `amount` decimal(15,2) DEFAULT NULL,
  `status` enum('pending','in_progress','completed') DEFAULT 'pending',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `workspace_reports`
--

CREATE TABLE `workspace_reports` (
  `id` int(11) NOT NULL,
  `workspace_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `progress_percentage` decimal(5,2) DEFAULT NULL,
  `work_done` text DEFAULT NULL,
  `work_planned` text DEFAULT NULL,
  `challenges` text DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `response_comment` text DEFAULT NULL,
  `responded_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `workspace_tasks`
--

CREATE TABLE `workspace_tasks` (
  `id` int(11) NOT NULL,
  `workspace_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `assigned_to` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `due_date` date DEFAULT NULL,
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `status` enum('pending','in_progress','completed') DEFAULT 'pending',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `workspace_time_extensions`
--

CREATE TABLE `workspace_time_extensions` (
  `id` int(11) NOT NULL,
  `workspace_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `additional_days` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `response_comment` text DEFAULT NULL,
  `responded_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin_id` (`admin_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `bids`
--
ALTER TABLE `bids`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_project_id` (`project_id`),
  ADD KEY `idx_contractor_id` (`contractor_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_project_status` (`project_id`,`status`),
  ADD KEY `idx_contractor_status` (`contractor_id`,`status`),
  ADD KEY `idx_admin_approved` (`admin_approved`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_amount` (`amount`),
  ADD KEY `idx_status_created` (`status`,`created_at`),
  ADD KEY `idx_project_pending_employer` (`project_id`,`status`);

--
-- Indexes for table `bid_attachments`
--
ALTER TABLE `bid_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bid_id` (`bid_id`);

--
-- Indexes for table `cities`
--
ALTER TABLE `cities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_country_id` (`country_id`);

--
-- Indexes for table `contractor_alerts`
--
ALTER TABLE `contractor_alerts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_contractor` (`contractor_id`);

--
-- Indexes for table `contractor_alerts_log`
--
ALTER TABLE `contractor_alerts_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_contractor` (`contractor_id`),
  ADD KEY `idx_project` (`project_id`);

--
-- Indexes for table `contractor_certificates`
--
ALTER TABLE `contractor_certificates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `contractor_documents`
--
ALTER TABLE `contractor_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contractor_id` (`contractor_id`);

--
-- Indexes for table `contractor_specs`
--
ALTER TABLE `contractor_specs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `conversations`
--
ALTER TABLE `conversations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_conversation` (`project_id`,`employer_id`,`contractor_id`),
  ADD KEY `employer_id` (`employer_id`),
  ADD KEY `contractor_id` (`contractor_id`);

--
-- Indexes for table `countries`
--
ALTER TABLE `countries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_name_ar` (`name_ar`);

--
-- Indexes for table `employer_alerts`
--
ALTER TABLE `employer_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employer_id` (`employer_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `idx_user_read` (`user_id`,`is_read`),
  ADD KEY `idx_user_read_created` (`user_id`,`is_read`,`created_at`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_user_created` (`user_id`,`created_at`);

--
-- Indexes for table `otp_codes`
--
ALTER TABLE `otp_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email` (`email`(250)),
  ADD KEY `code` (`code`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_shop_id` (`shop_id`),
  ADD KEY `idx_is_available` (`is_available`),
  ADD KEY `idx_shop_available` (`shop_id`,`is_available`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_employer_id` (`employer_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_country_id` (`country_id`),
  ADD KEY `idx_status_employer` (`status`,`employer_id`),
  ADD KEY `idx_city_id` (`city_id`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_deadline` (`deadline`),
  ADD KEY `idx_country_status` (`country_id`,`status`),
  ADD KEY `idx_employer_created` (`employer_id`,`created_at`);

--
-- Indexes for table `project_attachments`
--
ALTER TABLE `project_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `project_files`
--
ALTER TABLE `project_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `bid_id` (`bid_id`);

--
-- Indexes for table `project_images`
--
ALTER TABLE `project_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `project_workspace`
--
ALTER TABLE `project_workspace`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `project_bid` (`project_id`,`bid_id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `bid_id` (`bid_id`),
  ADD KEY `idx_project_id` (`project_id`),
  ADD KEY `idx_employer_id` (`employer_id`),
  ADD KEY `idx_contractor_id` (`contractor_id`),
  ADD KEY `idx_bid_id` (`bid_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_project_employer` (`project_id`,`employer_id`),
  ADD KEY `idx_project_contractor` (`project_id`,`contractor_id`);

--
-- Indexes for table `shops`
--
ALTER TABLE `shops`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `country_id` (`country_id`),
  ADD KEY `city_id` (`city_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_user_type` (`user_type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_country_id` (`country_id`),
  ADD KEY `idx_user_type_status` (`user_type`,`status`),
  ADD KEY `idx_city_id` (`city_id`),
  ADD KEY `idx_verification_status` (`verification_status`),
  ADD KEY `idx_employer_verification_status` (`employer_verification_status`),
  ADD KEY `idx_is_featured` (`is_featured`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_country_verification` (`country_id`,`verification_status`);

--
-- Indexes for table `workspace_activity`
--
ALTER TABLE `workspace_activity`
  ADD PRIMARY KEY (`id`),
  ADD KEY `activity_workspace_id` (`workspace_id`);

--
-- Indexes for table `workspace_change_orders`
--
ALTER TABLE `workspace_change_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `change_workspace_id` (`workspace_id`),
  ADD KEY `idx_workspace_id` (`workspace_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `workspace_claims`
--
ALTER TABLE `workspace_claims`
  ADD PRIMARY KEY (`id`),
  ADD KEY `claim_workspace_id` (`workspace_id`),
  ADD KEY `idx_workspace_id` (`workspace_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_workspace_status` (`workspace_id`,`status`),
  ADD KEY `idx_claimed_by` (`claimed_by`);

--
-- Indexes for table `workspace_clarifications`
--
ALTER TABLE `workspace_clarifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_clarifications_workspace` (`workspace_id`),
  ADD KEY `idx_clarifications_status` (`status`),
  ADD KEY `idx_workspace_id` (`workspace_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `workspace_deliveries`
--
ALTER TABLE `workspace_deliveries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_deliveries_workspace` (`workspace_id`),
  ADD KEY `idx_deliveries_status` (`status`),
  ADD KEY `idx_workspace_id` (`workspace_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_workspace_status` (`workspace_id`,`status`);

--
-- Indexes for table `workspace_materials`
--
ALTER TABLE `workspace_materials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_materials_workspace` (`workspace_id`),
  ADD KEY `idx_materials_status` (`status`),
  ADD KEY `idx_workspace_id` (`workspace_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `workspace_messages`
--
ALTER TABLE `workspace_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `msg_workspace_id` (`workspace_id`),
  ADD KEY `idx_workspace_id` (`workspace_id`),
  ADD KEY `idx_workspace_created` (`workspace_id`,`created_at`),
  ADD KEY `idx_sender_id` (`sender_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_message_type` (`message_type`);

--
-- Indexes for table `workspace_milestones`
--
ALTER TABLE `workspace_milestones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_milestones_workspace` (`workspace_id`),
  ADD KEY `idx_milestones_status` (`status`),
  ADD KEY `idx_workspace_id` (`workspace_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `workspace_reports`
--
ALTER TABLE `workspace_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_reports_workspace` (`workspace_id`),
  ADD KEY `idx_reports_status` (`status`),
  ADD KEY `idx_workspace_id` (`workspace_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `workspace_tasks`
--
ALTER TABLE `workspace_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_tasks_workspace` (`workspace_id`),
  ADD KEY `idx_tasks_assigned` (`assigned_to`),
  ADD KEY `idx_tasks_status` (`status`),
  ADD KEY `idx_workspace_id` (`workspace_id`),
  ADD KEY `idx_assigned_to` (`assigned_to`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_workspace_status` (`workspace_id`,`status`);

--
-- Indexes for table `workspace_time_extensions`
--
ALTER TABLE `workspace_time_extensions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_extensions_workspace` (`workspace_id`),
  ADD KEY `idx_extensions_status` (`status`),
  ADD KEY `idx_workspace_id` (`workspace_id`),
  ADD KEY `idx_status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_logs`
--
ALTER TABLE `admin_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=183;

--
-- AUTO_INCREMENT for table `bids`
--
ALTER TABLE `bids`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `bid_attachments`
--
ALTER TABLE `bid_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `cities`
--
ALTER TABLE `cities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `contractor_alerts`
--
ALTER TABLE `contractor_alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `contractor_alerts_log`
--
ALTER TABLE `contractor_alerts_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `contractor_certificates`
--
ALTER TABLE `contractor_certificates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contractor_documents`
--
ALTER TABLE `contractor_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `contractor_specs`
--
ALTER TABLE `contractor_specs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

--
-- AUTO_INCREMENT for table `conversations`
--
ALTER TABLE `conversations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `countries`
--
ALTER TABLE `countries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `employer_alerts`
--
ALTER TABLE `employer_alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=504;

--
-- AUTO_INCREMENT for table `otp_codes`
--
ALTER TABLE `otp_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=88;

--
-- AUTO_INCREMENT for table `project_attachments`
--
ALTER TABLE `project_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `project_files`
--
ALTER TABLE `project_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `project_images`
--
ALTER TABLE `project_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `project_workspace`
--
ALTER TABLE `project_workspace`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `reviews`
-- ⚠️ تم حذف أمر ALTER TABLE نهائياً
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shops`
--
ALTER TABLE `shops`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `workspace_activity`
--
ALTER TABLE `workspace_activity`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `workspace_change_orders`
--
ALTER TABLE `workspace_change_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `workspace_claims`
--
ALTER TABLE `workspace_claims`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `workspace_clarifications`
--
ALTER TABLE `workspace_clarifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `workspace_deliveries`
--
ALTER TABLE `workspace_deliveries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `workspace_materials`
--
ALTER TABLE `workspace_materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `workspace_messages`
--
ALTER TABLE `workspace_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `workspace_milestones`
--
ALTER TABLE `workspace_milestones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `workspace_reports`
--
ALTER TABLE `workspace_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `workspace_tasks`
--
ALTER TABLE `workspace_tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `workspace_time_extensions`
--
ALTER TABLE `workspace_time_extensions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_logs`
--
ALTER TABLE `admin_logs`
  ADD CONSTRAINT `admin_logs_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `bids`
--
ALTER TABLE `bids`
  ADD CONSTRAINT `bids_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bids_ibfk_2` FOREIGN KEY (`contractor_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `bid_attachments`
--
ALTER TABLE `bid_attachments`
  ADD CONSTRAINT `bid_attachments_ibfk_1` FOREIGN KEY (`bid_id`) REFERENCES `bids` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cities`
--
ALTER TABLE `cities`
  ADD CONSTRAINT `cities_ibfk_1` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `contractor_certificates`
--
ALTER TABLE `contractor_certificates`
  ADD CONSTRAINT `contractor_certificates_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `contractor_specs`
--
ALTER TABLE `contractor_specs`
  ADD CONSTRAINT `contractor_specs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`employer_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `projects_ibfk_2` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`),
  ADD CONSTRAINT `projects_ibfk_3` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`);

--
-- Constraints for table `project_attachments`
--
ALTER TABLE `project_attachments`
  ADD CONSTRAINT `attachments_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `project_files`
--
ALTER TABLE `project_files`
  ADD CONSTRAINT `project_files_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `project_files_ibfk_2` FOREIGN KEY (`bid_id`) REFERENCES `bids` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `project_images`
--
ALTER TABLE `project_images`
  ADD CONSTRAINT `project_images_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `project_workspace`
--
ALTER TABLE `project_workspace`
  ADD CONSTRAINT `workspace_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `workspace_ibfk_2` FOREIGN KEY (`bid_id`) REFERENCES `bids` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shops`
--
ALTER TABLE `shops`
  ADD CONSTRAINT `shops_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `shops_ibfk_2` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`),
  ADD CONSTRAINT `shops_ibfk_3` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`),
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`);

--
-- Constraints for table `workspace_activity`
--
ALTER TABLE `workspace_activity`
  ADD CONSTRAINT `activity_fk_workspace` FOREIGN KEY (`workspace_id`) REFERENCES `project_workspace` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `workspace_change_orders`
--
ALTER TABLE `workspace_change_orders`
  ADD CONSTRAINT `change_fk_workspace` FOREIGN KEY (`workspace_id`) REFERENCES `project_workspace` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `workspace_claims`
--
ALTER TABLE `workspace_claims`
  ADD CONSTRAINT `claim_fk_workspace` FOREIGN KEY (`workspace_id`) REFERENCES `project_workspace` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `workspace_messages`
--
ALTER TABLE `workspace_messages`
  ADD CONSTRAINT `msg_fk_workspace` FOREIGN KEY (`workspace_id`) REFERENCES `project_workspace` (`id`) ON DELETE CASCADE;

-- ✅ تمت إضافة أوامر تحديث الروابط القديمة إلى الرابط الجديد تلقائياً
UPDATE `notifications` SET `link` = REPLACE(`link`, 'http://www.quantity-pro.gt.tc', 'https://www.omranhub.com') WHERE `link` IS NOT NULL;
UPDATE `notifications` SET `message` = REPLACE(`message`, 'http://www.quantity-pro.gt.tc', 'https://www.omranhub.com') WHERE `message` IS NOT NULL;
UPDATE `messages` SET `message` = REPLACE(`message`, 'http://www.quantity-pro.gt.tc', 'https://www.omranhub.com') WHERE `message` IS NOT NULL;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;