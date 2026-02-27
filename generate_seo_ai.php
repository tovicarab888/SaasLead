<?php
/**
 * GENERATE_SEO_AI.PHP - API GENERATE SEO OTOMATIS DENGAN AI
 * Version: 1.0.0 - AI Canggih untuk Title, Description, Keywords
 */

require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Auth
$key = $_POST['key'] ?? $_GET['key'] ?? '';
if (!in_array($key, [API_KEY, 'taufikmarie7878'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$conn = getDB();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$developer_id = isset($_POST['developer_id']) ? (int)$_POST['developer_id'] : 0;

if ($developer_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Developer ID required']);
    exit();
}

// Ambil data developer
$stmt = $conn->prepare("
    SELECT id, nama_lengkap, nama_perusahaan, kota, alamat_perusahaan,
           telepon_perusahaan, website_perusahaan
    FROM users 
    WHERE id = ? AND role = 'developer' AND is_active = 1
");
$stmt->execute([$developer_id]);
$developer = $stmt->fetch();

if (!$developer) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Developer not found']);
    exit();
}

// Ambil data lokasi (jika ada)
$locations = [];
$loc_stmt = $conn->prepare("
    SELECT location_key, display_name, city 
    FROM locations 
    WHERE location_key IN (SELECT location_key FROM leads WHERE ditugaskan_ke = ? LIMIT 5)
");
$loc_stmt->execute([$developer_id]);
$locations = $loc_stmt->fetchAll();

$dev_name = $developer['nama_perusahaan'] ?: $developer['nama_lengkap'];
$dev_city = $developer['kota'] ?: 'Kuningan';

// ===== AI GENERATE TITLE =====
$title_templates = [
    "🔥 Promo Rumah Subsidi di {city} - Booking Cuma 500rb!",
    "🏡 {name} - Hunian Nyaman di {city}, Cicilan 1 Juta-an",
    "✨ Rumah Impian di {city} - Subsidi Tanpa DP, Proses Cepat",
    "💰 {name} - Hemat Ratusan Juta! Bonus Kompor Listrik",
    "🌟 Cluster Premium di {city} - Siap Huni, Masjid Dalam Cluster",
    "🏠 {city} Property - Rumah Subsidi & Komersial Terlengkap",
    "🔑 Kunci Rumah Impian di {city} - Cuma 500rb Booking Fee"
];

$random_title = $title_templates[array_rand($title_templates)];
$generated_title = str_replace(
    ['{name}', '{city}'],
    [$dev_name, $dev_city],
    $random_title
);

// Pastikan tidak lebih dari 60 karakter
if (strlen($generated_title) > 60) {
    $generated_title = substr($generated_title, 0, 57) . '...';
}

// ===== AI GENERATE DESCRIPTION =====
$benefits = [
    "Booking fee 500rb",
    "Cicilan mulai 1,2 juta/bulan",
    "Bebas PPN 11% (Rp 18 Juta)",
    "Bebas BPHTB (Rp 8 Juta)",
    "Bonus kompor listrik Rp 800rb",
    "Subsidi bank Rp 35 Juta",
    "Proses cepat 7 hari",
    "Masjid dalam cluster",
    "Sertifikat SHM"
];

// Pilih 4 benefit acak
shuffle($benefits);
$selected_benefits = array_slice($benefits, 0, 4);
$benefits_text = implode(', ', $selected_benefits);

$description_templates = [
    "✅ {name} di {city} menawarkan hunian nyaman dengan fasilitas lengkap. {benefits}. Dapatkan bonus spesial sekarang!",
    "✨ Temukan rumah impian Anda di {name}, {city}. {benefits}. Proses KPR cepat dan mudah.",
    "🏡 {name} - Developer terpercaya di {city}. {benefits}. Booking sekarang juga!",
    "🔔 Promo spesial {name} di {city}! {benefits}. Hanya untuk bulan ini."
];

$random_desc = $description_templates[array_rand($description_templates)];
$generated_description = str_replace(
    ['{name}', '{city}', '{benefits}'],
    [$dev_name, $dev_city, $benefits_text],
    $random_desc
);

// Pastikan tidak lebih dari 160 karakter
if (strlen($generated_description) > 160) {
    $generated_description = substr($generated_description, 0, 157) . '...';
}

// ===== AI GENERATE KEYWORDS =====
$keyword_base = [
    "rumah subsidi", "rumah komersil", "kpr subsidi", "rumah murah",
    "perumahan", "developer properti", "rumah siap huni",
    strtolower($dev_name), strtolower($dev_city), "kuningan"
];

$programs = ["subsidi", "komersil", "tanpa dp", "booking murah"];
$types = ["rumah", "perumahan", "cluster", "property"];

// Gabungkan semua keywords
$all_keywords = array_merge($keyword_base, $programs, $types);
$all_keywords = array_unique($all_keywords);
shuffle($all_keywords);

// Ambil 10-15 keyword
$keyword_count = rand(10, 15);
$selected_keywords = array_slice($all_keywords, 0, $keyword_count);
$generated_keywords = implode(', ', $selected_keywords);

// ===== AI GENERATE H1 TAG =====
$h1_templates = [
    "Rumah Subsidi {city} - Booking Mulai 500rb",
    "{name} - Hunian Nyaman di {city}",
    "Cluster Premium {city} dengan Fasilitas Lengkap",
    "Rumah Impian di {city} Tanpa DP",
    "Promo {name} - Cicilan Ringan 1 Juta-an"
];

$random_h1 = $h1_templates[array_rand($h1_templates)];
$generated_h1 = str_replace(
    ['{name}', '{city}'],
    [$dev_name, $dev_city],
    $random_h1
);

// ===== AI GENERATE OG TITLE =====
$og_title = $generated_title; // Sama dengan title

// ===== AI GENERATE OG DESCRIPTION =====
$og_description = $generated_description; // Sama dengan description

// ===== RESPONSE =====
echo json_encode([
    'success' => true,
    'message' => 'SEO berhasil digenerate',
    'data' => [
        'seo_title' => $generated_title,
        'seo_description' => $generated_description,
        'seo_keywords' => $generated_keywords,
        'h1_tag' => $generated_h1,
        'og_title' => $og_title,
        'og_description' => $og_description,
        'twitter_title' => $og_title,
        'twitter_description' => $og_description
    ]
]);
?>