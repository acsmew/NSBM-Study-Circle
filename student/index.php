<?php
require_once '../includes/functions.php';
requireStudent();

$faculty_filter = $_GET['faculty'] ?? 'All';
$category_filter = $_GET['category'] ?? 'All';
$year_filter = $_GET['year'] ?? 'All';
$batch_filter = $_GET['batch'] ?? 'All';
$search_query = trim($_GET['q'] ?? '');

$stmt = $pdo->query("SELECT m.*, s.name as subject_name, u.name as author_name, u.batch as author_batch, u.faculty as author_faculty
                     FROM materials m
                     LEFT JOIN subjects s ON m.subject_id = s.id
                     LEFT JOIN users u ON m.user_id = u.id
                     WHERE m.status = 'approved'
                     ORDER BY m.created_at DESC");
$all_materials = $stmt->fetchAll();

$faculty_counts = [
    'All' => count($all_materials),
    'Computing' => 0,
    'Business' => 0,
    'Engineering' => 0,
    'Science' => 0
];
foreach ($all_materials as $m) {
    $fac = $m['faculty'] ?: ($m['author_faculty'] ?: 'Computing');
    if (isset($faculty_counts[$fac])) {
        $faculty_counts[$fac]++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NSBM StudyCircle - Explore Study Notes</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/student.css">
</head>
<body>

    <nav class="navbar">
        <div class="nav-left">
            <button class="btn-toggle-sidebar" id="sidebarToggle">☰</button>
            <div class="nav-brand">
                <img src="../assets/img/nsbm-logo.png" alt="NSBM" style="height: 40px; margin-right: 8px;">
                <div style="display: flex; flex-direction: column; line-height: 1.1;">
                    <span style="font-weight: 800; color: #1E293B; font-size: 1.2rem; letter-spacing: 0.5px;">NSBM</span>
                    <span style="font-weight: 700; color: var(--accent-green); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px;">Study Circle</span>
                </div>
            </div>

            <div class="search-bar">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input type="text" id="topNavSearch" placeholder="Search by title, subject, faculty..." value="<?= htmlspecialchars($search_query) ?>">
            </div>
        </div>

        <div class="nav-right">
            <a href="materials.php" class="btn-upload">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Upload Note
            </a>

            <div class="user-profile">
                <div class="avatar-small">
                    <?= strtoupper(substr($_SESSION['name'], 0, 1)) ?>
                </div>
                <?= htmlspecialchars(explode(' ', $_SESSION['name'])[0]) ?>
                <a href="../logout.php" style="margin-left: 0.5rem; color: var(--text-secondary);" title="Sign out">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                </a>
            </div>
        </div>
    </nav>

    <div class="app-container">

        <aside class="sidebar">
            <ul class="sidebar-menu">
                <li><a href="index.php" class="sidebar-link active"><span class="sidebar-icon">🏠</span><span class="sidebar-text">Home</span></a></li>
                <li><a href="forum.php" class="sidebar-link"><span class="sidebar-icon">💬</span><span class="sidebar-text">Forum</span></a></li>
                <li><a href="groups.php" class="sidebar-link"><span class="sidebar-icon">👥</span><span class="sidebar-text">Study Groups</span></a></li>
            </ul>
        </aside>

        <main class="main-content" id="notesContainer" data-faculty="<?= htmlspecialchars($faculty_filter) ?>" data-category="<?= htmlspecialchars($category_filter) ?>" data-year="<?= htmlspecialchars($year_filter) ?>" data-batch="<?= htmlspecialchars($batch_filter) ?>" data-search="<?= htmlspecialchars($search_query) ?>" style="max-width: 1320px; width: 100%; margin: 0 auto;">

            <div class="hero-banner">
                <div class="hero-content">
                    <h1>Find the Best Study Notes</h1>
                    <p>Browse, review, and download peer notes customized for your course. Rewarding note-takers with StudyCredits!</p>
                </div>
            </div>

            <div class="filters-section">

                <div class="mobile-search-wrapper">
                    <div class="mobile-search-box">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <input type="text" id="mobileSearchInput" placeholder="Search notes, subjects, faculty..." value="<?= htmlspecialchars($search_query) ?>">
                        <button type="button" id="clearMobileSearchBtn" class="mobile-search-clear" style="display: none;">&times;</button>
                    </div>
                </div>

                <div class="filter-tabs" id="facultyTabs">
                    <button type="button" class="filter-tab <?= ($faculty_filter === 'All') ? 'active' : '' ?>" data-faculty="All">
                        All Faculties <span class="filter-badge"><?= $faculty_counts['All'] ?></span>
                    </button>
                    <button type="button" class="filter-tab <?= ($faculty_filter === 'Computing') ? 'active' : '' ?>" data-faculty="Computing">
                        Computing <span class="filter-badge"><?= $faculty_counts['Computing'] ?></span>
                    </button>
                    <button type="button" class="filter-tab <?= ($faculty_filter === 'Business') ? 'active' : '' ?>" data-faculty="Business">
                        Business <span class="filter-badge"><?= $faculty_counts['Business'] ?></span>
                    </button>
                    <button type="button" class="filter-tab <?= ($faculty_filter === 'Engineering') ? 'active' : '' ?>" data-faculty="Engineering">
                        Engineering <span class="filter-badge"><?= $faculty_counts['Engineering'] ?></span>
                    </button>
                    <button type="button" class="filter-tab <?= ($faculty_filter === 'Science') ? 'active' : '' ?>" data-faculty="Science">
                        Science <span class="filter-badge"><?= $faculty_counts['Science'] ?></span>
                    </button>
                </div>

                <div class="filter-selects" style="align-items: center;">
                    <select class="select-box" id="categorySelect">
                        <option value="All" <?= $category_filter === 'All' ? 'selected' : '' ?>>All Categories</option>
                        <option value="Lecture Notes" <?= $category_filter === 'Lecture Notes' ? 'selected' : '' ?>>Lecture Notes</option>
                        <option value="Past Papers" <?= $category_filter === 'Past Papers' ? 'selected' : '' ?>>Past Papers</option>
                        <option value="Summaries" <?= $category_filter === 'Summaries' ? 'selected' : '' ?>>Summaries</option>
                    </select>

                    <select class="select-box" id="yearSelect">
                        <option value="All" <?= $year_filter === 'All' ? 'selected' : '' ?>>All Years</option>
                        <option value="1st Year" <?= $year_filter === '1st Year' ? 'selected' : '' ?>>1st Year</option>
                        <option value="2nd Year" <?= $year_filter === '2nd Year' ? 'selected' : '' ?>>2nd Year</option>
                        <option value="3rd Year" <?= $year_filter === '3rd Year' ? 'selected' : '' ?>>3rd Year</option>
                        <option value="4th Year" <?= $year_filter === '4th Year' ? 'selected' : '' ?>>4th Year</option>
                    </select>

                    <select class="select-box" id="batchSelect">
                        <option value="All" <?= $batch_filter === 'All' ? 'selected' : '' ?>>All Batches</option>
                        <option value="26.1" <?= $batch_filter === '26.1' ? 'selected' : '' ?>>Batch 26.1</option>
                        <option value="26.1 IFSL" <?= $batch_filter === '26.1 IFSL' ? 'selected' : '' ?>>Batch 26.1 IFSL</option>
                    </select>

                    <button type="button" id="resetFiltersBtn" class="btn" style="background: #F1F5F9; color: #475569; padding: 0.5rem 1rem; border-radius: 50px; font-size: 0.82rem; font-weight: 700; border: 1px solid #CBD5E1; cursor: pointer; display: none;">
                        ✕ Clear Filters
                    </button>
                </div>
            </div>

            <div class="results-status-bar">
                <span id="resultsCountText">Showing all materials</span>
                <span id="activeFilterBadge" style="display: none; background: #EFF6FF; color: var(--accent-blue); padding: 3px 10px; border-radius: 50px; font-weight: 700; font-size: 0.8rem;"></span>
            </div>

            <div class="grid-container" id="notesGrid">
                <?php foreach($all_materials as $mat):
                    $mat_faculty = $mat['faculty'] ?: ($mat['author_faculty'] ?: 'Computing');
                    $mat_cat = $mat['category'] ?: 'Lecture Notes';
                    $mat_year = $mat['year_level'] ?: '1st Year';
                    $mat_batch = $mat['author_batch'] ?: '26.1';
                    $mat_size = $mat['file_size'] ?: '2.4 MB';

                    $tag_bg = '#2563EB';
                    if ($mat_faculty === 'Business') $tag_bg = '#059669';
                    elseif ($mat_faculty === 'Engineering') $tag_bg = '#D97706';
                    elseif ($mat_faculty === 'Science') $tag_bg = '#7C3AED';

                    $file_icon = '📄';
                    if ($mat_cat === 'Past Papers') $file_icon = '📝';
                    elseif ($mat_cat === 'Summaries') $file_icon = '💡';
                    elseif (stripos($mat['title'], 'guide') !== false || stripos($mat['title'], 'code') !== false) $file_icon = '💻';
                    elseif (stripos($mat['title'], 'notes') !== false) $file_icon = '📚';
                ?>
                <div class="note-card"
                     data-faculty="<?= htmlspecialchars($mat_faculty) ?>"
                     data-category="<?= htmlspecialchars($mat_cat) ?>"
                     data-year="<?= htmlspecialchars($mat_year) ?>"
                     data-batch="<?= htmlspecialchars($mat_batch) ?>"
                     data-title="<?= strtolower(htmlspecialchars($mat['title'])) ?>"
                     data-subject="<?= strtolower(htmlspecialchars($mat['subject_name'] ?? '')) ?>"
                     data-author="<?= strtolower(htmlspecialchars($mat['author_name'] ?? '')) ?>"
                     data-desc="<?= strtolower(htmlspecialchars($mat['description'] ?? '')) ?>">

                    <div class="card-tag" style="background: <?= $tag_bg ?>;">
                        <?= htmlspecialchars($mat_faculty) ?>
                    </div>

                    <div class="card-icon" style="font-size: 2.8rem; margin: 1.25rem 0 0.5rem 0; text-align: center;">
                        <?= $file_icon ?>
                    </div>

                    <h3 class="card-title" style="font-size: 1.05rem; font-weight: 700; color: #0F172A; margin-bottom: 0.35rem; line-height: 1.3; text-align: center;">
                        <?= htmlspecialchars($mat['title']) ?>
                    </h3>

                    <div class="card-author" style="font-size: 0.82rem; color: #64748B; font-weight: 600; margin-bottom: 0.5rem; text-align: center;">
                        by <?= htmlspecialchars($mat['author_name'] ?: 'Peer Student') ?> • <span style="color: #047857; font-weight: 700;"><?= htmlspecialchars($mat_cat) ?></span>
                    </div>

                    <?php if(!empty($mat['description'])): ?>
                    <p style="font-size: 0.8rem; color: #475569; margin: 0 0 0.75rem 0; line-height: 1.35; text-align: center; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                        <?= htmlspecialchars($mat['description']) ?>
                    </p>
                    <?php endif; ?>

                    <div style="font-size: 0.76rem; color: #64748B; text-align: center; margin-bottom: 1rem; font-weight: 700; display: flex; justify-content: center; gap: 6px; flex-wrap: wrap;">
                        <span style="background: #F1F5F9; padding: 2px 8px; border-radius: 4px;">🎓 <?= htmlspecialchars($mat_year) ?></span>
                        <span style="background: #F1F5F9; padding: 2px 8px; border-radius: 4px;">📦 <?= htmlspecialchars($mat_size) ?></span>
                    </div>

                    <div class="card-footer" style="margin-top: auto;">
                        <a href="download.php?id=<?= $mat['id'] ?>" class="btn-download" style="text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 6px;">
                            <span>⬇️</span> Download Note
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div id="noResultsBox" style="display: none; text-align: center; padding: 3.5rem 1.5rem; background: var(--bg-secondary); border-radius: var(--border-radius-md); border: 2px dashed var(--border-color); margin-top: 1rem;">
                <div style="font-size: 3rem; margin-bottom: 0.75rem;">🔍</div>
                <h3 style="font-size: 1.2rem; color: #1E293B; margin-bottom: 0.5rem;">No Study Notes Found</h3>
                <p style="color: var(--text-secondary); margin-bottom: 1.5rem; font-weight: 600;">No notes match your selected faculty or filters. Try clearing some filters or searching for something else.</p>
                <button type="button" onclick="clearAllFilters()" class="btn btn-primary">
                    Clear Filters & Show All
                </button>
            </div>
        </main>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/notes-filter.js"></script>
</body>
</html>
