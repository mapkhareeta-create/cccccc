<?php
require_once 'config.php';

// جلب الفلاتر من URL
$p_country = (int)($_GET['p_country'] ?? 0);
$p_city = (int)($_GET['p_city'] ?? 0);
$p_category = clean($_GET['p_category'] ?? '');

// ============================================
// استعلام جلب جميع المشاريع (بدون Pagination)
// ============================================
$sql = "
    SELECT p.*, u.name as employer_name, c.name_ar as country_name, ci.name_ar as city_name,
           co.currency_ar, co.currency_code,
           (SELECT COUNT(*) FROM bids WHERE project_id = p.id) as bids_count,
           (SELECT pi.image_path FROM project_images pi WHERE pi.project_id = p.id ORDER BY pi.id ASC LIMIT 1) as main_image
    FROM projects p
    JOIN users u ON p.employer_id = u.id
    LEFT JOIN countries c ON p.country_id = c.id
    LEFT JOIN cities ci ON p.city_id = ci.id
    LEFT JOIN countries co ON p.country_id = co.id
    WHERE p.status = 'open'
";

$params = [];
if ($p_country > 0) {
    $sql .= " AND p.country_id = ?";
    $params[] = $p_country;
}
if ($p_city > 0) {
    $sql .= " AND p.city_id = ?";
    $params[] = $p_city;
}
if (!empty($p_category)) {
    $sql .= " AND p.category = ?";
    $params[] = $p_category;
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$projects = $stmt->fetchAll();

// جلب البيانات للفلاتر
$countries = getCountries();
$categories = getProjectCategories();

// الشعار الترحيبي (مرة واحدة فقط)
$show_welcome = false;
if (!isLoggedIn() && !isset($_SESSION['welcome_shown'])) {
    $show_welcome = true;
    $_SESSION['welcome_shown'] = true;
}

$page_title = 'مزاد البناء - المنصة الأولى للمقاولات';
include 'includes/header.php';
?>

<style>
    /* ============================================ */
    /* تحسين الأداء للهواتف - خلفية مبسطة */
    /* ============================================ */
    body {
        position: relative;
        min-height: 100vh;
        background: #0a0e17;
    }
    
    /* ============================================ */
    /* الخلفية - تظهر فقط على الأجهزة الكبيرة */
    /* ============================================ */
    @media (min-width: 768px) {
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url('https://images.unsplash.com/photo-1541888946425-d81bb19240f5?w=1920&q=80');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: scroll;
            z-index: 0;
            animation: zoomIn 20s ease-in-out infinite alternate;
        }
        
        @keyframes zoomIn {
            0% { transform: scale(1); }
            100% { transform: scale(1.05); }
        }
        
        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, 
                rgba(10, 14, 23, 0.85) 0%,
                rgba(10, 14, 23, 0.70) 30%,
                rgba(10, 14, 23, 0.60) 60%,
                rgba(10, 14, 23, 0.75) 100%
            );
            z-index: 0;
        }
    }
    
    /* ============================================ */
    /* للهواتف - خلفية بسيطة بدون صورة */
    /* ============================================ */
    @media (max-width: 767px) {
        body {
            background: linear-gradient(135deg, #0a0e17 0%, #1a2332 100%);
        }
        body::before { display: none !important; }
        body::after { display: none !important; }
    }
    
    .gradient-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: radial-gradient(ellipse at 50% 30%, 
            rgba(255, 255, 255, 0.05) 0%,
            transparent 70%
        );
        z-index: 0;
        pointer-events: none;
    }
    
    .main-container {
        position: relative;
        z-index: 1;
    }
    
    /* ============================================ */
    /* الشعار الترحيبي - بدون تغيير */
    /* ============================================ */
    .welcome-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.7);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        animation: fadeIn 0.3s ease;
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
    }
    
    .welcome-overlay.hidden {
        display: none !important;
    }
    
    .welcome-popup {
        background: white;
        border-radius: 20px;
        padding: 40px 45px;
        max-width: 540px;
        width: 92%;
        text-align: center;
        position: relative;
        animation: slideUp 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes slideUp {
        from { transform: translateY(30px) scale(0.96); opacity: 0; }
        to { transform: translateY(0) scale(1); opacity: 1; }
    }
    
    .welcome-popup .close-btn {
        position: absolute;
        top: 12px;
        left: 16px;
        background: #f0f2f5;
        border: none;
        font-size: 20px;
        color: #65676b;
        cursor: pointer;
        transition: all 0.2s ease;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .welcome-popup .close-btn:hover {
        background: #e4e6eb;
        color: #1b1f24;
    }
    
    .welcome-popup .icon-big {
        font-size: 72px;
        margin-bottom: 12px;
        display: block;
    }
    
    .welcome-popup h2 {
        font-size: 26px;
        font-weight: 800;
        color: #1b1f24;
        margin-bottom: 6px;
    }
    
    .welcome-popup h2 span {
        color: #1877f2;
    }
    
    .welcome-popup .sub-text {
        color: #65676b;
        font-size: 15px;
        line-height: 1.6;
        margin-bottom: 24px;
    }
    
    .welcome-popup .btn-big-group {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-bottom: 16px;
    }
    
    .welcome-popup .btn-big-primary {
        background: #1877f2;
        color: white;
        border: none;
        padding: 14px 20px;
        border-radius: 12px;
        font-weight: 700;
        font-size: 17px;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        box-shadow: 0 2px 8px rgba(24, 119, 242, 0.3);
    }
    
    .welcome-popup .btn-big-primary:hover {
        background: #166fe5;
        transform: scale(1.02);
        box-shadow: 0 4px 16px rgba(24, 119, 242, 0.4);
    }
    
    .welcome-popup .btn-big-secondary {
        background: #f0f2f5;
        color: #1b1f24;
        border: 1px solid #e4e6eb;
        padding: 14px 20px;
        border-radius: 12px;
        font-weight: 700;
        font-size: 17px;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }
    
    .welcome-popup .btn-big-secondary:hover {
        background: #e4e6eb;
        transform: scale(1.02);
    }
    
    .welcome-popup .btn-big-secondary i {
        color: #1877f2;
    }
    
    .welcome-popup .btn-small-row {
        display: flex;
        gap: 8px;
        justify-content: center;
        flex-wrap: wrap;
        margin-top: 4px;
    }
    
    .welcome-popup .btn-small {
        background: #f0f2f5;
        color: #1b1f24;
        border: 1px solid #e4e6eb;
        padding: 8px 16px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        flex: 1;
        justify-content: center;
        min-width: 80px;
    }
    
    .welcome-popup .btn-small:hover {
        background: #e4e6eb;
        border-color: #1877f2;
    }
    
    .welcome-popup .btn-small i {
        color: #1877f2;
        font-size: 15px;
    }
    
    .welcome-popup .timer {
        margin-top: 16px;
        font-size: 13px;
        color: #8a8d91;
    }
    
    .welcome-popup .timer span {
        color: #1877f2;
        font-weight: 700;
        font-size: 15px;
    }
    
    @media (max-width: 480px) {
        .welcome-popup {
            padding: 28px 18px;
            border-radius: 16px;
        }
        .welcome-popup .icon-big {
            font-size: 52px;
        }
        .welcome-popup h2 {
            font-size: 20px;
        }
        .welcome-popup .btn-big-primary,
        .welcome-popup .btn-big-secondary {
            font-size: 15px;
            padding: 12px 14px;
        }
        .welcome-popup .btn-small {
            font-size: 12px;
            padding: 6px 12px;
            min-width: 60px;
        }
        .welcome-popup .btn-small-row {
            gap: 5px;
        }
    }
    
    /* ============================================ */
    /* التصميم الرئيسي - محسن للهواتف */
    /* ============================================ */
    .top-padding {
        padding-top: 20px;
    }
    .bottom-padding {
        padding-bottom: 40px;
    }
    
    /* شريط الفلتر - مبسط للهواتف */
    .filter-bar {
        background: rgba(255, 255, 255, 0.12);
        border-radius: 18px;
        border: 1px solid rgba(255, 255, 255, 0.15);
        padding: 14px 20px;
        margin-bottom: 16px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        position: sticky;
        top: 70px;
        z-index: 100;
    }
    
    @media (min-width: 768px) {
        .filter-bar {
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }
    }
    
    @media (max-width: 767px) {
        .filter-bar {
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
            background: rgba(10, 14, 23, 0.9);
            border-color: rgba(255, 255, 255, 0.05);
            padding: 12px 14px;
            top: 60px;
        }
    }
    
    .filter-bar select {
        border-radius: 12px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        padding: 12px 16px;
        font-size: 14px;
        background: rgba(255, 255, 255, 0.9);
        transition: all 0.3s ease;
        color: #1b1f24;
        width: 100%;
        appearance: auto;
        font-weight: 500;
    }
    
    @media (max-width: 767px) {
        .filter-bar select {
            padding: 10px 12px;
            font-size: 12px;
            background: rgba(255, 255, 255, 0.95);
        }
    }
    
    .filter-bar select:focus {
        border-color: #1877f2;
        box-shadow: 0 0 0 4px rgba(24, 119, 242, 0.15);
        outline: none;
        background: white;
    }
    
    .filter-bar select option {
        background: white;
        color: #1b1f24;
    }
    
    .filter-bar .btn-search {
        background: linear-gradient(135deg, #1877f2, #0d65d9);
        color: white;
        font-weight: 700;
        padding: 12px 20px;
        border-radius: 12px;
        border: none;
        transition: all 0.3s ease;
        font-size: 14px;
        width: 100%;
        letter-spacing: 0.3px;
        box-shadow: 0 4px 15px rgba(24, 119, 242, 0.3);
    }
    
    @media (max-width: 767px) {
        .filter-bar .btn-search {
            padding: 10px 12px;
            font-size: 12px;
        }
    }
    
    .filter-bar .btn-search:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(24, 119, 242, 0.4);
    }
    
    .filter-bar .btn-reset {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        font-weight: 600;
        padding: 12px 16px;
        border-radius: 12px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        transition: all 0.3s ease;
        font-size: 14px;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    @media (max-width: 767px) {
        .filter-bar .btn-reset {
            padding: 10px 12px;
            font-size: 12px;
            background: rgba(255, 255, 255, 0.1);
        }
    }
    
    .filter-bar .btn-reset:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: translateY(-2px);
    }
    
    /* علامات الفلاتر النشطة */
    .active-filters {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        margin-bottom: 14px;
        padding: 10px 16px;
        background: rgba(255, 255, 255, 0.08);
        border-radius: 14px;
        border: 1px solid rgba(255, 255, 255, 0.08);
    }
    
    @media (min-width: 768px) {
        .active-filters {
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }
    }
    
    @media (max-width: 767px) {
        .active-filters {
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
            background: rgba(10, 14, 23, 0.8);
            padding: 8px 12px;
            gap: 6px;
        }
    }
    
    .active-filters .label {
        font-size: 13px;
        color: rgba(255, 255, 255, 0.7);
        font-weight: 600;
    }
    
    @media (max-width: 767px) {
        .active-filters .label {
            font-size: 11px;
        }
    }
    
    .active-filters .tag {
        background: rgba(24, 119, 242, 0.2);
        color: #ffffff;
        font-size: 12px;
        padding: 4px 14px;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-weight: 600;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    @media (max-width: 767px) {
        .active-filters .tag {
            font-size: 10px;
            padding: 3px 10px;
        }
    }
    
    .active-filters .tag a {
        color: #ff6b6b;
        font-weight: 700;
        text-decoration: none;
        font-size: 16px;
        line-height: 1;
    }
    
    .active-filters .tag a:hover {
        color: #ff4444;
    }
    
    /* عنوان القسم */
    .section-header {
        margin-bottom: 18px;
    }
    
    .section-subtitle {
        font-size: 20px;
        color: rgba(255, 255, 255, 0.9);
        font-weight: 600;
        text-shadow: 0 2px 20px rgba(0, 0, 0, 0.3);
        letter-spacing: 0.5px;
    }
    
    @media (max-width: 767px) {
        .section-subtitle {
            font-size: 16px;
            text-shadow: none;
        }
    }
    
    .section-subtitle i {
        color: #fbbf24;
        margin-left: 6px;
    }
    
    /* بطاقات المشاريع - محسنة للهواتف */
    .project-card {
        background: rgba(255, 255, 255, 0.10);
        border-radius: 16px;
        overflow: hidden;
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        height: 100%;
        min-height: 160px;
        display: flex;
        flex-direction: column;
        border: 1px solid rgba(255, 255, 255, 0.12);
        cursor: pointer;
        position: relative;
    }
    
    @media (min-width: 768px) {
        .project-card {
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }
        .project-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, 
                rgba(255, 255, 255, 0.05),
                rgba(255, 255, 255, 0.02)
            );
            border-radius: 16px;
            pointer-events: none;
        }
    }
    
    @media (max-width: 767px) {
        .project-card {
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
            background: rgba(20, 30, 50, 0.85);
            border-color: rgba(255, 255, 255, 0.05);
            min-height: 130px;
            border-radius: 14px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.2);
        }
        .project-card::before {
            display: none !important;
        }
    }
    
    .project-card:hover {
        transform: translateY(-6px) scale(1.01);
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
        border-color: rgba(24, 119, 242, 0.3);
        background: rgba(255, 255, 255, 0.15);
    }
    
    @media (max-width: 767px) {
        .project-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
            background: rgba(30, 50, 80, 0.9);
        }
    }
    
    .project-card:active {
        transform: scale(0.98);
        transition-duration: 0.05s;
    }
    
    .project-card-inner {
        display: flex;
        flex-direction: row;
        align-items: stretch;
        padding: 16px;
        gap: 16px;
        height: 100%;
        flex: 1;
        position: relative;
        z-index: 1;
    }
    
    @media (max-width: 767px) {
        .project-card-inner {
            padding: 12px;
            gap: 12px;
        }
    }
    
    .project-content {
        flex: 1;
        order: 1;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-width: 0;
    }
    
    .project-image {
        width: 95px;
        height: 95px;
        flex-shrink: 0;
        order: 2;
        border-radius: 12px;
        overflow: hidden;
        background: rgba(0, 0, 0, 0.3);
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.2);
        align-self: center;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    @media (max-width: 767px) {
        .project-image {
            width: 72px;
            height: 72px;
            border-radius: 10px;
        }
    }
    
    .project-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.4s ease;
    }
    
    .project-card:hover .project-image img {
        transform: scale(1.08);
    }
    
    .project-image .no-image {
        width: 100%;
        height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        background: rgba(0, 0, 0, 0.2);
        color: rgba(255, 255, 255, 0.4);
    }
    
    .project-image .no-image i {
        font-size: 36px;
    }
    
    @media (max-width: 767px) {
        .project-image .no-image i {
            font-size: 26px;
        }
    }
    
    .project-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 6px;
        flex-wrap: wrap;
        gap: 4px;
    }
    
    .project-category {
        background: rgba(24, 119, 242, 0.25);
        color: #8bb8ff;
        border-radius: 20px;
        font-size: 10px;
        padding: 3px 12px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        letter-spacing: 0.3px;
        border: 1px solid rgba(255, 255, 255, 0.05);
    }
    
    @media (max-width: 767px) {
        .project-category {
            font-size: 9px;
            padding: 2px 10px;
        }
    }
    
    .project-status {
        background: rgba(34, 197, 94, 0.2);
        color: #6fcf97;
        border-radius: 20px;
        font-size: 10px;
        padding: 3px 12px;
        font-weight: 700;
        white-space: nowrap;
        letter-spacing: 0.3px;
        border: 1px solid rgba(255, 255, 255, 0.05);
    }
    
    @media (max-width: 767px) {
        .project-status {
            font-size: 9px;
            padding: 2px 10px;
        }
    }
    
    .project-title {
        color: #ffffff;
        font-weight: 700;
        font-size: 16px;
        margin: 0 0 4px 0;
        line-height: 1.3;
        display: -webkit-box;
        -webkit-line-clamp: 1;
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-shadow: 0 1px 8px rgba(0, 0, 0, 0.2);
    }
    
    @media (max-width: 767px) {
        .project-title {
            font-size: 14px;
            margin-bottom: 3px;
            text-shadow: none;
        }
    }
    
    .project-description {
        color: rgba(255, 255, 255, 0.75);
        font-size: 13px;
        line-height: 1.4;
        margin-bottom: 8px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        flex: 1;
        text-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
    }
    
    @media (max-width: 767px) {
        .project-description {
            font-size: 11px;
            margin-bottom: 4px;
            text-shadow: none;
        }
    }
    
    .project-details {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        margin-bottom: 6px;
    }
    
    @media (max-width: 767px) {
        .project-details {
            gap: 5px;
            margin-bottom: 4px;
        }
    }
    
    .project-budget {
        background: rgba(0, 0, 0, 0.2);
        border-radius: 8px;
        padding: 3px 12px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid rgba(255, 255, 255, 0.05);
    }
    
    @media (max-width: 767px) {
        .project-budget {
            padding: 2px 10px;
        }
    }
    
    .project-budget i {
        font-size: 11px;
        color: rgba(255, 255, 255, 0.6);
    }
    
    .project-budget span {
        color: #ffffff;
        font-size: 12px;
        font-weight: 600;
    }
    
    @media (max-width: 767px) {
        .project-budget span {
            font-size: 10px;
        }
    }
    
    .project-location {
        color: rgba(255, 255, 255, 0.7);
        font-size: 12px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .project-location i {
        font-size: 11px;
    }
    
    @media (max-width: 767px) {
        .project-location {
            font-size: 10px;
        }
    }
    
    .project-footer {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        margin-top: 6px;
        padding-top: 6px;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
    }
    
    @media (max-width: 767px) {
        .project-footer {
            margin-top: 4px;
            padding-top: 4px;
        }
    }
    
    .project-stats {
        color: rgba(255, 255, 255, 0.5);
        font-size: 12px;
        display: flex;
        align-items: center;
        gap: 14px;
    }
    
    .project-stats i {
        font-size: 12px;
        margin-right: 4px;
    }
    
    @media (max-width: 767px) {
        .project-stats {
            font-size: 10px;
            gap: 8px;
        }
    }
    
    /* مؤشر أن الكرت قابل للنقر */
    .click-hint {
        position: absolute;
        bottom: 8px;
        right: 12px;
        font-size: 10px;
        color: rgba(255, 255, 255, 0.3);
        opacity: 0;
        transition: opacity 0.3s ease;
        z-index: 2;
    }
    
    .project-card:hover .click-hint {
        opacity: 1;
    }
    
    @media (max-width: 767px) {
        .click-hint {
            display: none !important;
        }
    }
    
    /* حالة عدم وجود نتائج */
    .empty-state {
        background: rgba(255, 255, 255, 0.08);
        border-radius: 18px;
        padding: 50px 20px;
        text-align: center;
        border: 1px solid rgba(255, 255, 255, 0.08);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    }
    
    @media (min-width: 768px) {
        .empty-state {
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }
    }
    
    @media (max-width: 767px) {
        .empty-state {
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
            background: rgba(20, 30, 50, 0.8);
            padding: 30px 16px;
        }
    }
    
    .empty-state i {
        font-size: 56px;
        color: rgba(255, 255, 255, 0.3);
        margin-bottom: 16px;
    }
    
    @media (max-width: 767px) {
        .empty-state i {
            font-size: 40px;
        }
    }
    
    .empty-state p {
        color: rgba(255, 255, 255, 0.6);
        font-size: 16px;
        font-weight: 500;
    }
    
    @media (max-width: 767px) {
        .empty-state p {
            font-size: 14px;
        }
    }
    
    .empty-state a {
        color: #8bb8ff;
        font-weight: 600;
        font-size: 14px;
        margin-top: 10px;
        display: inline-block;
    }
    
    .empty-state a:hover {
        text-decoration: underline;
    }
</style>

<!-- طبقة التدرج الإضافية -->
<div class="gradient-overlay"></div>

<!-- ============================================ -->
<!-- شعار ترحيبي - يظهر مرة واحدة فقط -->
<!-- ============================================ -->
<?php if ($show_welcome): ?>
<div id="welcomeOverlay" class="welcome-overlay">
    <div class="welcome-popup">
        <button onclick="closeWelcome()" class="close-btn">
            <i class="fas fa-times"></i>
        </button>
        
        <span class="icon-big">🏗️</span>
        <h2>مرحباً بك في <span>مزاد البناء</span></h2>
        <p class="sub-text">منصتك الأولى لربط أصحاب المشاريع بالمقاولين الموثوقين</p>
        
        <div class="btn-big-group">
            <a href="<?= SITE_URL ?>/register.php" class="btn-big-primary">
                <i class="fas fa-user-plus"></i> إنشاء حساب جديد
            </a>
            <a href="<?= SITE_URL ?>/login.php" class="btn-big-secondary">
                <i class="fas fa-sign-in-alt"></i> تسجيل دخول
            </a>
        </div>
        
        <div class="btn-small-row">
            <a href="<?= SITE_URL ?>/projects_list.php" class="btn-small">
                <i class="fas fa-project-diagram"></i> مشاريع
            </a>
            <a href="<?= SITE_URL ?>/contractors_list.php" class="btn-small">
                <i class="fas fa-hard-hat"></i> مقاولين
            </a>
            <a href="<?= SITE_URL ?>/shops_list.php" class="btn-small">
                <i class="fas fa-store"></i> محلات
            </a>
        </div>
        
        <div class="timer" id="welcomeTimer">
            سيتم إغلاق هذه النافذة تلقائياً خلال <span id="countdown">10</span> ثواني
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ============================================ -->
<!-- المحتوى الرئيسي -->
<!-- ============================================ -->
<div class="main-container top-padding bottom-padding">
    <div class="max-w-7xl mx-auto px-3 md:px-6">
        
        <!-- فلتر العطاءات -->
        <div class="filter-bar">
            <form method="GET" action="">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2 md:gap-3">
                    <div>
                        <select name="p_country" id="p_country" onchange="loadCities('p_country', 'p_city')">
                            <option value="">🇸🇦 الدولة</option>
                            <?php foreach ($countries as $country): ?>
                            <option value="<?= $country['id'] ?>" <?= ($p_country == $country['id']) ? 'selected' : '' ?>>
                                <?= $country['name_ar'] ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <select name="p_city" id="p_city">
                            <option value="">📍 المدينة</option>
                            <?php 
                            if ($p_country > 0) {
                                $cities = getCities($p_country);
                                foreach ($cities as $city) {
                                    $selected = ($p_city == $city['id']) ? 'selected' : '';
                                    echo "<option value='{$city['id']}' $selected>{$city['name_ar']}</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div>
                        <select name="p_category">
                            <option value="">🏗️ التخصص</option>
                            <?php foreach ($categories as $cat => $icon): ?>
                            <option value="<?= $cat ?>" <?= ($p_category === $cat) ? 'selected' : '' ?>>
                                <?= $icon ?> <?= $cat ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="flex gap-2">
                        <button type="submit" class="btn-search">
                            <i class="fas fa-search"></i> بحث
                        </button>
                        <a href="<?= SITE_URL ?>/" class="btn-reset" style="width: auto; padding: 12px 18px;">
                            <i class="fas fa-undo-alt"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- عرض الفلاتر النشطة -->
        <?php if ($p_country > 0 || $p_city > 0 || !empty($p_category)): ?>
        <div class="active-filters">
            <span class="label">📌 الفلاتر النشطة:</span>
            <?php 
            function buildFilterUrl($removeKey, $removeValue = null) {
                $params = $_GET;
                unset($params[$removeKey]);
                if ($removeValue !== null) {
                    $params[$removeKey] = $removeValue;
                }
                return '?' . http_build_query($params);
            }
            ?>
            <?php if ($p_country > 0): 
                foreach ($countries as $c) { if ($c['id'] == $p_country) { $country_name = $c['name_ar']; break; } }
            ?>
            <span class="tag">
                🌍 <?= $country_name ?>
                <a href="<?= buildFilterUrl('p_country', 0) ?>">&times;</a>
            </span>
            <?php endif; ?>
            <?php if ($p_city > 0): 
                $cities = getCities($p_country);
                foreach ($cities as $c) { if ($c['id'] == $p_city) { $city_name = $c['name_ar']; break; } }
            ?>
            <span class="tag">
                📍 <?= $city_name ?>
                <a href="<?= buildFilterUrl('p_city', 0) ?>">&times;</a>
            </span>
            <?php endif; ?>
            <?php if (!empty($p_category)): ?>
            <span class="tag">
                🏷️ <?= $p_category ?>
                <a href="<?= buildFilterUrl('p_category', '') ?>">&times;</a>
            </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- عنوان القسم -->
        <div class="section-header">
            <p class="section-subtitle">
                <i class="fas fa-helmet-safety"></i> اختر المشروع المناسب لك وقدم عطائك
                <?php if (!empty($projects)): ?>
                <span style="font-size: 14px; color: rgba(255,255,255,0.5); font-weight: 400; margin-right: 12px;">
                    (<?= count($projects) ?> مشروع)
                </span>
                <?php endif; ?>
            </p>
        </div>
        
        <!-- قائمة المشاريع (جميعها دفعة واحدة) -->
        <?php if (empty($projects)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p>لا توجد عطاءات مطابقة لبحثك</p>
            <a href="<?= SITE_URL ?>/">عرض الكل</a>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 md:gap-5">
            <?php foreach ($projects as $project): ?>
            <a href="<?= SITE_URL ?>/project_detail.php?id=<?= $project['id'] ?>" class="project-card" style="text-decoration: none;">
                <div class="project-card-inner">
                    <div class="project-content">
                        <div>
                            <div class="project-header">
                                <span class="project-category">
                                    <?= $categories[$project['category']] ?? '🏗️' ?> <?= clean($project['category']) ?>
                                </span>
                                <span class="project-status">
                                    <i class="fas fa-check-circle" style="font-size: 9px;"></i> مفتوح
                                </span>
                            </div>
                            
                            <h3 class="project-title"><?= clean($project['title']) ?></h3>
                            
                            <p class="project-description">
                                <?= clean(substr($project['description'], 0, 60)) ?>...
                            </p>
                        </div>
                        
                        <div>
                            <div class="project-details">
                                <div class="project-budget">
                                    <i class="fas fa-coins"></i>
                                    <span>
                                        <?php if ($project['budget_min'] && $project['budget_max']): ?>
                                            <?= number_format($project['budget_min']) ?> - <?= number_format($project['budget_max']) ?> <?= clean($project['currency_ar'] ?? '') ?>
                                        <?php elseif ($project['budget_min']): ?>
                                            من <?= number_format($project['budget_min']) ?> <?= clean($project['currency_ar'] ?? '') ?>
                                        <?php else: ?>
                                            حسب الاتفاق
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="project-location">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?= clean($project['city_name'] ?? '') ?></span>
                                </div>
                            </div>
                            
                            <div class="project-footer">
                                <div class="project-stats">
                                    <span><i class="fas fa-gavel"></i> <?= $project['bids_count'] ?? 0 ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="project-image">
                        <?php 
                        $image_path = null;
                        if (!empty($project['main_image']) && file_exists(__DIR__ . '/' . $project['main_image'])) {
                            $image_path = SITE_URL . '/' . $project['main_image'];
                        }
                        ?>
                        <?php if ($image_path): ?>
                            <img src="<?= $image_path ?>" alt="<?= clean($project['title']) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="no-image">
                                <i class="fas fa-building"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="click-hint">👆 اضغط للتفاصيل</div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<script>
// ============================================
// دوال خاصة بـ index.php
// ============================================

function closeWelcome() {
    const overlay = document.getElementById('welcomeOverlay');
    if (overlay) {
        overlay.classList.add('hidden');
    }
    clearInterval(welcomeTimer);
}

let welcomeCountdown = 10;
const welcomeCountdownElement = document.getElementById('countdown');
const welcomeOverlay = document.getElementById('welcomeOverlay');

const welcomeTimer = setInterval(function() {
    welcomeCountdown--;
    if (welcomeCountdownElement) {
        welcomeCountdownElement.textContent = welcomeCountdown;
    }
    if (welcomeCountdown <= 0) {
        closeWelcome();
    }
}, 1000);

document.querySelectorAll('.welcome-popup a, .welcome-popup .close-btn').forEach(function(el) {
    el.addEventListener('click', function() {
        setTimeout(function() {
            closeWelcome();
        }, 150);
    });
});

// ============================================
// دالة تحميل المدن
// ============================================
function loadCities(countrySelectId, citySelectId) {
    const countrySelect = document.getElementById(countrySelectId);
    const citySelect = document.getElementById(citySelectId);
    const countryId = countrySelect.value;
    
    citySelect.innerHTML = '<option value="">جاري التحميل...</option>';
    
    if (!countryId || countryId == 0) {
        citySelect.innerHTML = '<option value="">📍 المدينة</option>';
        return;
    }
    
    fetch('<?= SITE_URL ?>/api.php?action=get_cities&country_id=' + countryId)
        .then(response => response.json())
        .then(data => {
            citySelect.innerHTML = '<option value="">📍 المدينة</option>';
            if (data.status === 'success' && data.data && data.data.length > 0) {
                data.data.forEach(city => {
                    const option = document.createElement('option');
                    option.value = city.id;
                    option.textContent = city.name_ar;
                    citySelect.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.error('Error loading cities:', error);
            citySelect.innerHTML = '<option value="">📍 المدينة</option>';
        });
}

console.log('✅ index.php (بدون Pagination) loaded successfully');
</script>

<?php include 'includes/footer.php'; ?>