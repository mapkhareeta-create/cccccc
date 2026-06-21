<?php
require_once 'config.php';

// التأكد من تسجيل الدخول وأن المستخدم صاحب عمل
if (!isLoggedIn()) {
    redirect('login.php');
}

if ($_SESSION['user_type'] !== 'employer') {
    redirect('index.php');
}

$user_id = $_SESSION['user_id'];

// ============================================
// جلب الإحصائيات
// ============================================

// عدد مشاريع صاحب العمل
$stmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE employer_id = ?");
$stmt->execute([$user_id]);
$projects_count = $stmt->fetchColumn();

// عدد العطاءات على مشاريعه
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM bids b 
    JOIN projects p ON b.project_id = p.id 
    WHERE p.employer_id = ?
");
$stmt->execute([$user_id]);
$bids_count = $stmt->fetchColumn();

// عدد العطاءات الجديدة (غير المقروءة)
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM bids b 
    JOIN projects p ON b.project_id = p.id 
    WHERE p.employer_id = ? AND b.status = 'pending'
");
$stmt->execute([$user_id]);
$new_bids_count = $stmt->fetchColumn();

// عدد العطاءات المقبولة (قيد التنفيذ)
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM bids b 
    JOIN projects p ON b.project_id = p.id 
    WHERE p.employer_id = ? AND b.status = 'accepted'
");
$stmt->execute([$user_id]);
$accepted_bids_count = $stmt->fetchColumn();

// عدد المقاولين النشطين (للعرض)
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'contractor' AND status = 'active'");
$contractors_count = $stmt->fetchColumn();

// ============================================
// جلب آخر العطاءات
// ============================================
$stmt = $pdo->prepare("
    SELECT 
        b.id as bid_id,
        b.amount,
        b.status as bid_status,
        b.created_at as bid_date,
        p.id as project_id,
        p.title as project_title,
        u.id as contractor_id,
        u.name as contractor_name,
        u.avatar as contractor_avatar
    FROM bids b
    JOIN projects p ON b.project_id = p.id
    JOIN users u ON b.contractor_id = u.id
    WHERE p.employer_id = ?
    ORDER BY b.created_at DESC
    LIMIT 5
");
$stmt->execute([$user_id]);
$recent_bids = $stmt->fetchAll();

// ============================================
// جلب مشاريع صاحب العمل (آخر 3)
// ============================================
$stmt = $pdo->prepare("
    SELECT id, title, status, created_at 
    FROM projects 
    WHERE employer_id = ? 
    ORDER BY created_at DESC 
    LIMIT 3
");
$stmt->execute([$user_id]);
$recent_projects = $stmt->fetchAll();

$page_title = 'لوحة تحكم صاحب العمل';
include 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl" class="h-full">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>لوحة تحكم صاحب العمل</title>
    <script src="https://cdn.tailwindcss.com/3.4.17"></script>
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.263.0/dist/umd/lucide.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&amp;display=swap" rel="stylesheet" />
    <style>
        * {
            font-family: 'Tajawal', sans-serif;
        }
        html,
        body {
            height: 100%;
            margin: 0;
        }

        .main-wrapper {
            height: 100%;
            width: 100%;
            overflow-y: auto;
            background: linear-gradient(135deg, #f8fafb 0%, #f0f4f8 100%);
            background-image: 
                linear-gradient(135deg, #f8fafb 0%, #f0f4f8 100%),
                url('data:image/svg+xml,%3Csvg width="100" height="100" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"%3E%3Cdefs%3E%3ClinearGradient id="grad1" x1="0%25" y1="0%25" x2="100%25" y2="100%25"%3E%3Cstop offset="0%25" style="stop-color:%233b82f6;stop-opacity:0.05" /%3E%3Cstop offset="100%25" style="stop-color:%23a855f7;stop-opacity:0.03" /%3E%3C/linearGradient%3E%3C/defs%3E%3Cg fill="url(%23grad1)"stroke="none"%3E%3Cpath d="M20 20 L80 20 L80 80 L20 80 Z" opacity="0.4"/%3E%3Ccircle cx="50" cy="50" r="30" opacity="0.3"/%3E%3Cpath d="M10 50 Q50 10 90 50 Q50 90 10 50" opacity="0.25"/%3E%3Crect x="30" y="30" width="40" height="40" opacity="0.35"/%3E%3C/g%3E%3C/svg%3E');
            background-size: cover, 140px 140px;
            background-position: 0 0, 0 0;
        }

        .card-base {
            background: white;
            border-radius: 16px;
            border: 1px solid #e5e7eb;
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04), 0 4px 16px rgba(0, 0, 0, 0.02);
        }

        .card-base:hover {
            border-color: #d1d5db;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08), 0 12px 32px rgba(0, 0, 0, 0.04);
        }

        .welcome-header {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            border-radius: 20px;
        }

        .welcome-header::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url('data:image/svg+xml,%3Csvg width="60" height="60" viewBox="0 0 60 60"%3E%3Cg fill="white" opacity="0.03"%3E%3Cpath d="M0 0h60v60H0z"/%3E%3Ccircle cx="30" cy="30" r="20" fill="none" stroke="white" stroke-width="0.5"/%3E%3Ccircle cx="30" cy="30" r="10" fill="none" stroke="white" stroke-width="0.5"/%3E%3C/g%3E%3C/svg%3E');
            border-radius: 20px;
        }

        /* ====== 4 عدادات في سطر واحد ====== */
        .stats-row {
            display: flex;
            flex-direction: row;
            gap: 10px;
            width: 100%;
        }

        .stat-box {
            flex: 1;
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(8px);
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 8px 6px;
            transition: all 0.35s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            min-height: 60px;
            cursor: default;
        }

        .stat-box:hover {
            transform: translateY(-3px);
            border-color: #d1d5db;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
        }

        .stat-box .stat-icon {
            font-size: 18px;
            line-height: 1;
            margin-bottom: 2px;
        }

        .stat-box .stat-number {
            font-size: 20px;
            font-weight: 900;
            color: #1e293b;
            line-height: 1.2;
        }

        .stat-box .stat-label {
            font-size: 10px;
            color: #94a3b8;
            font-weight: 500;
            white-space: nowrap;
        }

        .stat-box .stat-badge {
            font-size: 8px;
            font-weight: 700;
            background: #f59e0b;
            color: white;
            padding: 1px 6px;
            border-radius: 8px;
            margin-right: 2px;
        }

        .stat-box.blue .stat-icon { color: #3b82f6; }
        .stat-box.emerald .stat-icon { color: #10b981; }
        .stat-box.amber .stat-icon { color: #f59e0b; }
        .stat-box.rose .stat-icon { color: #f43f5e; }
        .stat-box.purple .stat-icon { color: #8b5cf6; }

        /* ====== أزرار مربعة ====== */
        .action-square {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 28px 20px;
            border-radius: 16px;
            font-weight: 700;
            font-size: 18px;
            text-decoration: none;
            transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
            border: 2px solid #e5e7eb;
            background: white;
            color: #1e293b;
            min-height: 120px;
            gap: 8px;
            text-align: center;
        }

        .action-square:hover {
            transform: translateY(-6px);
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.12);
        }

        .action-square .icon-big {
            font-size: 36px;
            line-height: 1;
        }

        .action-square.primary {
            border-color: #3b82f6;
            background: linear-gradient(135deg, #3b82f6, #0ea5e9);
            color: white;
        }

        .action-square.primary:hover {
            box-shadow: 0 16px 40px rgba(59, 130, 246, 0.35);
        }

        .action-square.secondary {
            border-color: #a855f7;
            background: linear-gradient(135deg, #a855f7, #ec4899);
            color: white;
        }

        .action-square.secondary:hover {
            box-shadow: 0 16px 40px rgba(168, 85, 247, 0.35);
        }

        /* ====== زر المشاريع المحالة ====== */
        .action-square.assigned {
            border-color: #10b981;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            position: relative;
            overflow: hidden;
        }

        .action-square.assigned::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: shimmer 3s ease-in-out infinite;
        }

        @keyframes shimmer {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(-30%, -30%); }
        }

        .action-square.assigned:hover {
            box-shadow: 0 16px 40px rgba(16, 185, 129, 0.4);
        }

        .action-square.assigned .count-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ef4444;
            color: white;
            font-size: 12px;
            font-weight: 700;
            padding: 2px 10px;
            border-radius: 20px;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
            animation: pulse-badge 2s ease-in-out infinite;
        }

        @keyframes pulse-badge {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        /* ====== أزرار مستطيلة مع إيموجي ====== */
        .action-rect {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 18px 28px;
            border-radius: 14px;
            font-weight: 700;
            font-size: 17px;
            text-decoration: none;
            transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
            border: 2px solid #e5e7eb;
            background: white;
            color: #1e293b;
        }

        .action-rect:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.1);
        }

        .action-rect .emoji {
            font-size: 28px;
            line-height: 1;
        }

        .action-rect.gold {
            border-color: #f59e0b;
            background: linear-gradient(135deg, #f59e0b, #f97316);
            color: white;
        }

        .action-rect.gold:hover {
            box-shadow: 0 12px 32px rgba(245, 158, 11, 0.4);
        }

        .action-rect.purple {
            border-color: #8b5cf6;
            background: linear-gradient(135deg, #8b5cf6, #a855f7);
            color: white;
        }

        .action-rect.purple:hover {
            box-shadow: 0 12px 32px rgba(139, 92, 246, 0.4);
        }

        /* ====== باقي التنسيقات ====== */
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 14px;
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 14px;
        }

        .section-header h3 {
            font-size: 16px;
            font-weight: 800;
            color: #1e293b;
        }

        .view-all-link {
            font-size: 12px;
            color: #3b82f6;
            font-weight: 700;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 4px;
            padding: 5px 12px;
            border-radius: 8px;
            background: rgba(59, 130, 246, 0.08);
            transition: all 0.3s ease;
        }

        .view-all-link:hover {
            background: rgba(59, 130, 246, 0.15);
            gap: 8px;
        }

        .bid-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 14px;
            border-bottom: 1px solid #f3f4f6;
            transition: all 0.25s ease;
            flex-wrap: wrap;
            gap: 10px;
            border-radius: 10px;
            cursor: pointer;
            background: white;
            text-decoration: none;
        }

        .bid-item:last-child {
            border-bottom: none;
        }

        .bid-item:hover {
            background: linear-gradient(135deg, #f0f9ff, #f5f3ff);
            border-color: #dbeafe;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
            transform: translateX(-4px);
        }

        .bid-contractor {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
            min-width: 180px;
        }

        .bid-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 14px;
            flex-shrink: 0;
            background: linear-gradient(135deg, #3b82f6, #06b6d4);
        }

        .bid-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .bid-contractor-name {
            font-weight: 600;
            color: #1e293b;
            font-size: 14px;
        }

        .bid-project {
            font-size: 12px;
            color: #64748b;
        }

        .bid-amount {
            font-weight: 700;
            color: #10b981;
            font-size: 15px;
            white-space: nowrap;
        }

        .status-badge {
            font-size: 11px;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 12px;
            white-space: nowrap;
        }

        .status-pending {
            background: rgba(245, 158, 11, 0.12);
            color: #d97706;
            border: 1px solid rgba(217, 119, 6, 0.2);
        }

        .status-accepted {
            background: rgba(16, 185, 129, 0.12);
            color: #059669;
            border: 1px solid rgba(5, 150, 105, 0.2);
        }

        .status-rejected {
            background: rgba(239, 68, 68, 0.12);
            color: #dc2626;
            border: 1px solid rgba(220, 38, 38, 0.2);
        }

        .project-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            border-bottom: 1px solid #f3f4f6;
            transition: all 0.25s ease;
            flex-wrap: wrap;
            gap: 8px;
            border-radius: 10px;
            cursor: pointer;
            background: white;
            text-decoration: none;
        }

        .project-item:last-child {
            border-bottom: none;
        }

        .project-item:hover {
            background: linear-gradient(135deg, #f0fdf4, #f5f3ff);
            border-color: #dcfce7;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
            transform: translateX(-4px);
        }

        .project-title {
            font-weight: 600;
            color: #1e293b;
            font-size: 14px;
            flex: 1;
            min-width: 140px;
        }

        .project-date {
            font-size: 11px;
            color: #94a3b8;
        }

        .status-open {
            background: rgba(16, 185, 129, 0.12);
            color: #059669;
            border: 1px solid rgba(5, 150, 105, 0.2);
        }

        .status-in_progress {
            background: rgba(245, 158, 11, 0.12);
            color: #d97706;
            border: 1px solid rgba(217, 119, 6, 0.2);
        }

        .status-completed {
            background: rgba(59, 130, 246, 0.12);
            color: #2563eb;
            border: 1px solid rgba(37, 99, 235, 0.2);
        }

        .status-cancelled {
            background: rgba(239, 68, 68, 0.12);
            color: #dc2626;
            border: 1px solid rgba(220, 38, 38, 0.2);
        }

        .empty-state {
            text-align: center;
            padding: 32px 20px;
        }

        .empty-icon {
            font-size: 48px;
            display: block;
            margin-bottom: 12px;
            opacity: 0.4;
        }

        .empty-text {
            color: #94a3b8;
            font-size: 14px;
        }

        .empty-action {
            display: inline-block;
            margin-top: 12px;
            color: #3b82f6;
            font-weight: 600;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 8px;
            background: rgba(59, 130, 246, 0.08);
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .empty-action:hover {
            background: rgba(59, 130, 246, 0.15);
        }

        .fade-in {
            animation: fadeUp 0.6s ease both;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .stagger-1 { animation-delay: 0.08s; }
        .stagger-2 { animation-delay: 0.16s; }
        .stagger-3 { animation-delay: 0.24s; }
        .stagger-4 { animation-delay: 0.32s; }
        .stagger-5 { animation-delay: 0.40s; }

        .lucide {
            display: inline-block;
            width: 1.25rem;
            height: 1.25rem;
        }

        .bid-item-link,
        .project-item-link {
            display: block;
            text-decoration: none;
            color: inherit;
        }

        /* ====== صف الأزرار ====== */
        .actions-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 16px;
        }

        .rect-actions-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        /* ====== للشاشات الصغيرة (الهاتف) ====== */
        @media (max-width: 640px) {
            .actions-row {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }
            .rect-actions-row {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }
            .action-square {
                padding: 20px 14px;
                min-height: 90px;
                font-size: 14px;
                border-radius: 14px;
            }
            .action-square .icon-big {
                font-size: 28px;
            }
            .action-rect {
                padding: 14px 16px;
                font-size: 13px;
                gap: 8px;
                border-radius: 12px;
            }
            .action-rect .emoji {
                font-size: 22px;
            }
            .stats-row {
                gap: 6px;
            }
            .stat-box {
                min-height: 50px;
                padding: 6px 4px;
                border-radius: 10px;
            }
            .stat-box .stat-icon {
                font-size: 14px;
            }
            .stat-box .stat-number {
                font-size: 16px;
            }
            .stat-box .stat-label {
                font-size: 8px;
            }
            .welcome-header {
                padding: 12px 16px !important;
            }
            .welcome-header h1 {
                font-size: 20px !important;
            }
            .welcome-header p {
                font-size: 12px !important;
            }
            .card-base {
                padding: 14px !important;
            }
            .section-header h3 {
                font-size: 14px;
            }
            .bid-item {
                padding: 10px 12px;
            }
            .project-item {
                padding: 8px 12px;
            }
        }

        @media (max-width: 480px) {
            .actions-row {
                grid-template-columns: 1fr;
            }
            .action-square.assigned .count-badge {
                font-size: 10px;
                padding: 1px 8px;
                top: -6px;
                right: -6px;
            }
        }
    </style>
</head>
<body class="h-full">

<div class="main-wrapper">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

        <!-- قسم الترحيب -->
        <div class="fade-in card-base welcome-header relative overflow-hidden px-6 sm:px-8 py-4 mb-6">
            <div class="relative z-10">
                <h1 class="text-2xl sm:text-3xl font-900 text-white mb-0.5">
                    مرحباً، <span class="text-cyan-300"><?= clean($_SESSION['user_name'] ?? 'صاحب العمل') ?></span> 👋
                </h1>
                <p class="text-white/70 text-sm sm:text-base">استعرض مشاريعك، تابع العطاءات، وابدأ مشروعك الجديد</p>
            </div>
        </div>

        <!-- ====== 4 عدادات في سطر واحد ====== -->
        <div class="stats-row fade-in stagger-1 mb-6">
            <div class="stat-box blue">
                <div class="stat-icon"><i data-lucide="briefcase" class="w-5 h-5"></i></div>
                <div class="stat-number"><?= $projects_count ?></div>
                <div class="stat-label">مشاريع مطروحة</div>
            </div>
            <div class="stat-box emerald">
                <div class="stat-icon"><i data-lucide="layers" class="w-5 h-5"></i></div>
                <div class="stat-number"><?= $bids_count ?></div>
                <div class="stat-label">عطاءات مستلمة</div>
            </div>
            <div class="stat-box amber">
                <div class="stat-icon"><i data-lucide="bell-ring" class="w-5 h-5"></i></div>
                <div class="stat-number">
                    <?= $new_bids_count ?>
                    <?php if ($new_bids_count > 0): ?>
                        <span class="stat-badge">جديد</span>
                    <?php endif; ?>
                </div>
                <div class="stat-label">عطاءات جديدة</div>
            </div>
            <div class="stat-box rose">
                <div class="stat-icon"><i data-lucide="users" class="w-5 h-5"></i></div>
                <div class="stat-number"><?= $contractors_count ?></div>
                <div class="stat-label">مقاولين نشطين</div>
            </div>
        </div>

        <!-- ====== أزرار مربعة: اطرح مشروع + المشاريع المحالة + ابحث عن مقاول ====== -->
        <div class="actions-row fade-in stagger-2 mb-4">
            <a href="post_project.php" class="action-square primary">
                <span class="icon-big">🚀</span>
                <span>اطرح مشروعاً</span>
            </a>
            <a href="my_bids.php?filter=accepted" class="action-square assigned">
                <span class="icon-big">⚡</span>
                <span>المشاريع المحالة</span>
                <span style="font-size:14px;font-weight:500;opacity:0.9;">قيد التنفيذ</span>
                <?php if ($accepted_bids_count > 0): ?>
                    <span class="count-badge"><?= $accepted_bids_count ?></span>
                <?php endif; ?>
            </a>
            <a href="contractors_list.php" class="action-square secondary">
                <span class="icon-big">🔍</span>
                <span>ابحث عن مقاول</span>
            </a>
        </div>

        <!-- ====== أزرار مستطيلة: العطاءات الواردة + مشاريعي ====== -->
        <div class="rect-actions-row fade-in stagger-3 mb-8">
            <a href="my_bids.php" class="action-rect gold">
                <span class="emoji">📥</span>
                <span>العطاءات الواردة لمشاريعي</span>
            </a>
            <a href="projects_list.php" class="action-rect purple">
                <span class="emoji">📁</span>
                <span>مشاريعي</span>
            </a>
        </div>

        <!-- ====== العمودان: آخر العطاءات + آخر مشاريعي (في الأسفل) ====== -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 fade-in stagger-5">

            <!-- آخر العطاءات -->
            <div class="card-base p-6">
                <div class="section-header">
                    <h3><i data-lucide="file-text" class="inline ml-2 text-indigo-600"></i>آخر العطاءات</h3>
                    <a href="my_bids.php" class="view-all-link">
                        عرض الكل <i data-lucide="arrow-left" class="w-4 h-4"></i>
                    </a>
                </div>

                <?php if (empty($recent_bids)): ?>
                    <div class="empty-state">
                        <span class="empty-icon">📭</span>
                        <p class="empty-text">لا توجد عطاءات مستلمة حتى الآن</p>
                        <a href="post_project.php" class="empty-action">اطرح مشروعك الأول →</a>
                    </div>
                <?php else: ?>
                    <div class="space-y-1">
                        <?php foreach ($recent_bids as $bid): ?>
                            <a href="project_detail.php?id=<?= $bid['project_id'] ?>&bid=<?= $bid['bid_id'] ?>" class="bid-item-link">
                                <div class="bid-item">
                                    <div class="bid-contractor">
                                        <div class="bid-avatar">
                                            <?php if (!empty($bid['contractor_avatar']) && $bid['contractor_avatar'] !== 'default-avatar.png'): ?>
                                                <img src="<?= SITE_URL ?>/<?= $bid['contractor_avatar'] ?>" alt="<?= clean($bid['contractor_name']) ?>" />
                                            <?php else: ?>
                                                <?= mb_substr($bid['contractor_name'], 0, 1) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="bid-contractor-name"><?= clean($bid['contractor_name']) ?></div>
                                            <div class="bid-project">📌 <?= clean($bid['project_title']) ?></div>
                                        </div>
                                    </div>
                                    <div class="bid-amount"><?= number_format($bid['amount']) ?> ر.س</div>
                                    <span class="status-badge status-<?= $bid['bid_status'] ?>">
                                        <?php
                                            $status_labels = [
                                                'pending'   => '⏳ قيد المراجعة',
                                                'accepted'  => '✅ مقبول',
                                                'rejected'  => '❌ مرفوض'
                                            ];
                                            echo $status_labels[$bid['bid_status']] ?? $bid['bid_status'];
                                        ?>
                                    </span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- آخر المشاريع -->
            <div class="card-base p-6">
                <div class="section-header">
                    <h3><i data-lucide="building-2" class="inline ml-2 text-purple-600"></i>آخر مشاريعي</h3>
                    <a href="projects_list.php" class="view-all-link">
                        عرض الكل <i data-lucide="arrow-left" class="w-4 h-4"></i>
                    </a>
                </div>

                <?php if (empty($recent_projects)): ?>
                    <div class="empty-state">
                        <span class="empty-icon">🏗️</span>
                        <p class="empty-text">لا توجد مشاريع مضافة بعد</p>
                        <a href="post_project.php" class="empty-action">اطرح مشروعك الأول →</a>
                    </div>
                <?php else: ?>
                    <div class="space-y-1">
                        <?php foreach ($recent_projects as $project): ?>
                            <a href="project_detail.php?id=<?= $project['id'] ?>" class="project-item-link">
                                <div class="project-item">
                                    <div class="flex items-center gap-3">
                                        <div class="w-2 h-2 rounded-full 
                                            <?= $project['status'] === 'open' ? 'bg-emerald-500' : '' ?>
                                            <?= $project['status'] === 'in_progress' ? 'bg-amber-500' : '' ?>
                                            <?= $project['status'] === 'completed' ? 'bg-blue-500' : '' ?>
                                            <?= $project['status'] === 'cancelled' ? 'bg-red-500' : '' ?>
                                        "></div>
                                        <span class="project-title"><?= clean($project['title']) ?></span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="status-badge status-<?= $project['status'] ?>">
                                            <?php
                                                $status_labels = [
                                                    'open'          => '🟢 مفتوح',
                                                    'in_progress'   => '🟡 قيد التنفيذ',
                                                    'completed'     => '🔵 مكتمل',
                                                    'cancelled'     => '🔴 ملغي'
                                                ];
                                                echo $status_labels[$project['status']] ?? $project['status'];
                                            ?>
                                        </span>
                                        <span class="project-date"><?= date('Y-m-d', strtotime($project['created_at'])) ?></span>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div><!-- نهاية العمودين -->

    </div>
</div>

<!-- تفعيل أيقونات Lucide -->
<script>
    lucide.createIcons();
</script>

<?php include 'includes/footer.php'; ?>
</body>
</html>