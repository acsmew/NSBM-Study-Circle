<?php
require_once __DIR__ . '/includes/functions.php';

echo "Running migrations for materials...\n";

$cols = $pdo->query("SHOW COLUMNS FROM materials")->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('faculty', $cols)) {
    $pdo->exec("ALTER TABLE materials ADD COLUMN faculty VARCHAR(100) DEFAULT 'Computing'");
    echo "Added 'faculty' column.\n";
}
if (!in_array('category', $cols)) {
    $pdo->exec("ALTER TABLE materials ADD COLUMN category VARCHAR(100) DEFAULT 'Lecture Notes'");
    echo "Added 'category' column.\n";
}
if (!in_array('year_level', $cols)) {
    $pdo->exec("ALTER TABLE materials ADD COLUMN year_level VARCHAR(50) DEFAULT '1st Year'");
    echo "Added 'year_level' column.\n";
}
if (!in_array('file_size', $cols)) {
    $pdo->exec("ALTER TABLE materials ADD COLUMN file_size VARCHAR(50) DEFAULT '1.2 MB'");
    echo "Added 'file_size' column.\n";
}

$existing_subjects = $pdo->query("SELECT name FROM subjects")->fetchAll(PDO::FETCH_COLUMN);
$default_subjects = [
    'Data Structures & Algorithms',
    'Web Application Development',
    'Database Systems',
    'Object Oriented Programming',
    'Financial Accounting',
    'Principles of Marketing',
    'Engineering Mathematics',
    'Digital Electronics',
    'Biotechnology',
    'Environmental Science'
];

foreach ($default_subjects as $sname) {
    if (!in_array($sname, $existing_subjects)) {
        $stmt = $pdo->prepare("INSERT INTO subjects (name, description) VALUES (?, ?)");
        $stmt->execute([$sname, "$sname course module"]);
    }
}

$upload_dir = __DIR__ . '/uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$sample_file = 'sample_study_note.pdf';
if (!file_exists($upload_dir . $sample_file)) {
    file_put_contents($upload_dir . $sample_file, "%PDF-1.4 sample NSBM study note document");
}

$user_id = $pdo->query("SELECT id FROM users LIMIT 1")->fetchColumn();
if (!$user_id) {
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, batch, faculty, role) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute(['Achira Chamika', 'achira@nsbm.ac.lk', password_hash('password', PASSWORD_DEFAULT), '26.1 IFSL', 'Computing', 'student']);
    $user_id = $pdo->lastInsertId();
}

$mat_count = $pdo->query("SELECT COUNT(*) FROM materials")->fetchColumn();
if ($mat_count == 0) {
    echo "Seeding sample materials for all faculties...\n";

    $seed_data = [

        [
            'title' => 'Data Structures & Algorithms - Complete Lecture Notes',
            'subject' => 'Data Structures & Algorithms',
            'faculty' => 'Computing',
            'category' => 'Lecture Notes',
            'year' => '1st Year',
            'desc' => 'Comprehensive notes covering Arrays, Linked Lists, Stacks, Queues, and Big-O notation.'
        ],
        [
            'title' => 'Web Development HTML, CSS & JavaScript Master Guide',
            'subject' => 'Web Application Development',
            'faculty' => 'Computing',
            'category' => 'Lecture Notes',
            'year' => '2nd Year',
            'desc' => 'Frontend essentials, DOM manipulation, responsive design with CSS Flexbox & Grid.'
        ],
        [
            'title' => 'Database Systems SQL Queries & Normalization Past Papers',
            'subject' => 'Database Systems',
            'faculty' => 'Computing',
            'category' => 'Past Papers',
            'year' => '2nd Year',
            'desc' => 'Past examination questions with model answers on 1NF, 2NF, 3NF, and complex SQL joins.'
        ],
        [
            'title' => 'OOP in Java Exam Summary & Cheat Sheet',
            'subject' => 'Object Oriented Programming',
            'faculty' => 'Computing',
            'category' => 'Lecture Notes',
            'year' => '1st Year',
            'desc' => 'Key concepts of Encapsulation, Inheritance, Polymorphism, and Interfaces in Java.'
        ],

        [
            'title' => 'Financial Accounting Principles & Balance Sheet Notes',
            'subject' => 'Financial Accounting',
            'faculty' => 'Business',
            'category' => 'Lecture Notes',
            'year' => '1st Year',
            'desc' => 'Double entry bookkeeping, ledger accounts, trial balances, and financial statement preparation.'
        ],
        [
            'title' => 'Principles of Modern Marketing Case Studies',
            'subject' => 'Principles of Marketing',
            'faculty' => 'Business',
            'category' => 'Lecture Notes',
            'year' => '2nd Year',
            'desc' => 'Analysis of the 4 Ps, consumer behavior, market segmentation, and digital branding.'
        ],
        [
            'title' => 'Business Accounting 2024 Mid-Semester Past Paper Solutions',
            'subject' => 'Financial Accounting',
            'faculty' => 'Business',
            'category' => 'Past Papers',
            'year' => '1st Year',
            'desc' => 'Step-by-step solved past questions with marking rubric.'
        ],

        [
            'title' => 'Engineering Mathematics & Calculus Formula Guide',
            'subject' => 'Engineering Mathematics',
            'faculty' => 'Engineering',
            'category' => 'Lecture Notes',
            'year' => '1st Year',
            'desc' => 'Differential equations, integration techniques, Laplace transforms, and vector calculus.'
        ],
        [
            'title' => 'Digital Logic & Circuit Design Lab Notes',
            'subject' => 'Digital Electronics',
            'faculty' => 'Engineering',
            'category' => 'Lecture Notes',
            'year' => '2nd Year',
            'desc' => 'Logic gates, Boolean algebra, Karnaugh maps, flip-flops, and counters.'
        ],
        [
            'title' => 'Engineering Mathematics 2023 End-Semester Past Papers',
            'subject' => 'Engineering Mathematics',
            'faculty' => 'Engineering',
            'category' => 'Past Papers',
            'year' => '2nd Year',
            'desc' => 'Complete past exam questions with detailed proofs and diagrams.'
        ],

        [
            'title' => 'Biotechnology & Genetic Engineering Lecture Slides Summary',
            'subject' => 'Biotechnology',
            'faculty' => 'Science',
            'category' => 'Lecture Notes',
            'year' => '1st Year',
            'desc' => 'Recombinant DNA technology, PCR techniques, gel electrophoresis, and gene cloning.'
        ],
        [
            'title' => 'Environmental Science & Sustainability Examination Questions',
            'subject' => 'Environmental Science',
            'faculty' => 'Science',
            'category' => 'Past Papers',
            'year' => '1st Year',
            'desc' => 'Past questions on ecosystems, biodiversity conservation, and environmental policy.'
        ]
    ];

    foreach ($seed_data as $item) {
        $s_stmt = $pdo->prepare("SELECT id FROM subjects WHERE name = ? LIMIT 1");
        $s_stmt->execute([$item['subject']]);
        $sub_id = $s_stmt->fetchColumn() ?: 1;

        $ins = $pdo->prepare("INSERT INTO materials (user_id, subject_id, title, description, file_path, status, faculty, category, year_level, file_size) VALUES (?, ?, ?, ?, ?, 'approved', ?, ?, ?, ?)");
        $ins->execute([
            $user_id,
            $sub_id,
            $item['title'],
            $item['desc'],
            'uploads/' . $sample_file,
            $item['faculty'],
            $item['category'],
            $item['year'],
            '2.4 MB'
        ]);
    }
    echo "Seeded " . count($seed_data) . " sample materials successfully!\n";
} else {
    echo "Materials already exist ($mat_count found). Ensuring status is approved and faculty is set...\n";
    $pdo->exec("UPDATE materials SET status = 'approved' WHERE status = 'pending'");
}

echo "Migration finished successfully!\n";
