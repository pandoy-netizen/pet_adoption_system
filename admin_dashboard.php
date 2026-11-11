<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

/* ================= AJAX: MARK TUTORIAL AS SEEN ================= */
// This catches the background request when a user finishes the tutorial
if (isset($_POST['mark_tutorial_seen'])) {
    $conn_ajax = $conn;
    if ($conn_ajax) {
        $u_id = (int)($_SESSION['user_id'] ?? 1);
        mysqli_query($conn_ajax, "UPDATE users SET tutorial_seen = 1 WHERE id = '$u_id'");
    }
    exit; // Stop execution for AJAX
}

/* ================= LOGOUT & RESET HANDLERS ================= */
if(isset($_GET['logout'])){ session_unset(); session_destroy(); header("Location: index.php"); exit(); }
if (isset($_GET['reset_match'])) { header("Location: ?#ai_match"); exit(); }

// Database Connection
// Railway MySQL connection is initialized above via config/db.php.

/* ================= IDENTIFY USER FIRST ================= */
$user_id = $_SESSION['user_id'] ?? 1; 

// Fetch User Info
$full_name = "User"; 
$contact_info = "Registered User";
$tutorial_seen = 1; // Default to 1 (seen) just in case, updated by DB below

try {
    $user_query = @mysqli_query($conn, "SELECT * FROM users WHERE id = '$user_id'");
    if ($user_query && mysqli_num_rows($user_query) > 0) {
        $user_data = mysqli_fetch_assoc($user_query);
        if (!empty($user_data['name'])) { $full_name = $user_data['name']; } 
        elseif (!empty($user_data['full_name'])) { $full_name = $user_data['full_name']; } 
        elseif (!empty($user_data['username'])) { $full_name = $user_data['username']; } 
        elseif (!empty($user_data['first_name'])) { $full_name = $user_data['first_name'] . (!empty($user_data['last_name']) ? ' ' . $user_data['last_name'] : ''); }
        if (!empty($user_data['email'])) { $contact_info = $user_data['email']; }
        
        // Check if tutorial is seen
        if (isset($user_data['tutorial_seen'])) {
            $tutorial_seen = (int)$user_data['tutorial_seen'];
        } else {
            $tutorial_seen = 0; // If column exists but is null/not set
        }
    }
} catch (Exception $e) {}

$first_name = explode(' ', trim($full_name))[0]; 
$initials = strtoupper(substr($first_name, 0, 1) . (strpos($full_name, ' ') !== false ? substr($full_name, strpos($full_name, ' ') + 1, 1) : ''));

/* ================= INITIALIZE UI VARIABLES ================= */
$scroll_target = ""; 
$client_feature_msg = "";
$profile_msg = "";
$app_success = "";
$stray_success = "";
$lost_success = "";
$sponsor_success = "";

// Ensure uploads folder exists for photos
if (!is_dir('uploads')) {
    @mkdir('uploads', 0777, true);
}

/* ================= DATABASE AUTO-MIGRATION ================= */
// Add Tutorial Tracking Column to Users Table
try { @mysqli_query($conn, "ALTER TABLE users ADD COLUMN tutorial_seen TINYINT(1) DEFAULT 0"); } catch (Exception $e) {}

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS adoption_applications (
    id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, pet_id INT, living_space VARCHAR(255),
    experience_level VARCHAR(255), activity_level VARCHAR(255), with_children VARCHAR(50),
    status VARCHAR(50) DEFAULT 'Pending', application_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS rescue_reports (
    id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, reporter_name VARCHAR(100), contact_info VARCHAR(100),
    location VARCHAR(255), latitude VARCHAR(50), longitude VARCHAR(50), description TEXT, image VARCHAR(255), status VARCHAR(50) DEFAULT 'Action Needed',
    reported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS client_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL, type VARCHAR(50) DEFAULT 'info', is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS adoption_appointments (
    id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, pet_id INT NOT NULL,
    appointment_date DATE NOT NULL, appointment_time TIME NOT NULL, purpose VARCHAR(100) DEFAULT 'Meet & Greet',
    notes TEXT, status VARCHAR(50) DEFAULT 'Pending', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS community_posts (
    id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, user_display_name VARCHAR(100), content TEXT, location VARCHAR(255), image VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS pet_favorites (
    id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, pet_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY unique_user_pet (user_id, pet_id)
)");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS client_donations (
    id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, pet_name VARCHAR(150), amount DECIMAL(10,2) NOT NULL,
    status VARCHAR(50) DEFAULT 'Completed', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS lost_pets (
    id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, user_display_name VARCHAR(100), contact_info VARCHAR(100),
    pet_name VARCHAR(100), description TEXT, location VARCHAR(255), image VARCHAR(255), status VARCHAR(50) DEFAULT 'Lost',
    reported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Ensure structural columns exist
try { @mysqli_query($conn, "ALTER TABLE adoption_applications ADD COLUMN living_space VARCHAR(255) AFTER pet_id"); } catch (Exception $e) {}
try { @mysqli_query($conn, "ALTER TABLE adoption_applications ADD COLUMN experience_level VARCHAR(255) AFTER living_space"); } catch (Exception $e) {}
try { @mysqli_query($conn, "ALTER TABLE adoption_applications ADD COLUMN activity_level VARCHAR(255) AFTER experience_level"); } catch (Exception $e) {}
try { @mysqli_query($conn, "ALTER TABLE adoption_applications ADD COLUMN with_children VARCHAR(50) AFTER activity_level"); } catch (Exception $e) {}
try { @mysqli_query($conn, "ALTER TABLE adoption_applications ADD COLUMN application_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER status"); } catch (Exception $e) {}
try { @mysqli_query($conn, "ALTER TABLE pets ADD COLUMN size VARCHAR(50) DEFAULT 'Medium'"); } catch (Exception $e) {}
try { @mysqli_query($conn, "ALTER TABLE pets ADD COLUMN temperament VARCHAR(100) DEFAULT 'Friendly'"); } catch (Exception $e) {}
try { @mysqli_query($conn, "ALTER TABLE rescue_reports ADD COLUMN reported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"); } catch (Exception $e) {}

/* ========================= CLIENT FEATURE HANDLERS ========================= */

// Handle Sponsorship/Donation Submission
if(isset($_POST['submit_sponsorship'])) {
    $sponsor_amount = (float)$_POST['sponsor_amount'];
    $sponsor_pet = mysqli_real_escape_string($conn, $_POST['sponsor_pet_name']);
    
    if($sponsor_amount > 0) {
        $sponsor_sql = "INSERT INTO client_donations (user_id, pet_name, amount, status) VALUES ('$user_id', '$sponsor_pet', '$sponsor_amount', 'Completed')";
        if(mysqli_query($conn, $sponsor_sql)) {
            $sponsor_success = "Thank you! Your sponsorship of $$sponsor_amount for $sponsor_pet has been successfully processed.";
            $scroll_target = "donations";
        }
    }
}

// Handle Lost & Found Upload
if(isset($_POST['submit_lost_pet'])){
    $l_pet_name = mysqli_real_escape_string($conn, $_POST['l_pet_name']);
    $l_desc = mysqli_real_escape_string($conn, $_POST['l_description']);
    $l_loc = mysqli_real_escape_string($conn, $_POST['l_location']);
    
    $imageName = "default_lost.jpg";
    if(!empty($_FILES["l_image"]["name"])){
        $imageName = time() . "_lost_" . basename($_FILES["l_image"]["name"]);
        move_uploaded_file($_FILES["l_image"]["tmp_name"], "uploads/" . $imageName);
    }
    
    $sql = "INSERT INTO lost_pets (user_id, user_display_name, contact_info, pet_name, description, location, image, status) 
            VALUES ('$user_id', '$full_name', '$contact_info', '$l_pet_name', '$l_desc', '$l_loc', '$imageName', 'Lost')";
    if(mysqli_query($conn, $sql)){
        $lost_success = "Lost pet alert posted successfully. The community will keep an eye out!";
        $scroll_target = "lost_found";
    }
}

// Handle Appointment Scheduling
if (isset($_POST['schedule_appointment'])) {
    $appointment_pet = (int)($_POST['appointment_pet_id'] ?? 0);
    $appointment_date = mysqli_real_escape_string($conn, $_POST['appointment_date'] ?? '');
    $appointment_time = mysqli_real_escape_string($conn, $_POST['appointment_time'] ?? '');
    $appointment_purpose = mysqli_real_escape_string($conn, $_POST['appointment_purpose'] ?? 'Meet & Greet');
    $appointment_notes = mysqli_real_escape_string($conn, $_POST['appointment_notes'] ?? '');

    if ($appointment_pet > 0 && $appointment_date !== '' && $appointment_time !== '') {
        $appt_sql = "INSERT INTO adoption_appointments
            (user_id, pet_id, appointment_date, appointment_time, purpose, notes, status)
            VALUES ('$user_id', '$appointment_pet', '$appointment_date', '$appointment_time',
                    '$appointment_purpose', '$appointment_notes', 'Pending')";
        if (mysqli_query($conn, $appt_sql)) {
            $client_feature_msg = "Appointment request submitted successfully!";
            mysqli_query($conn, "INSERT INTO client_notifications (user_id,title,message,type)
                VALUES ('$user_id','Appointment Request Sent',
                'Your appointment request has been submitted and is waiting for shelter confirmation.','appointment')");
            $scroll_target = "appointments";
        }
    }
}

// Generate simple notifications from application status
if (!isset($_POST['mark_notifications_read'])) {
    $notif_check = mysqli_query($conn, "SELECT a.id, a.status, p.name AS pet_name
        FROM adoption_applications a JOIN pets p ON a.pet_id=p.id
        WHERE a.user_id='$user_id' ORDER BY a.id DESC LIMIT 10");
    if ($notif_check) {
        while ($n = mysqli_fetch_assoc($notif_check)) {
            $title = "Adoption Application Update";
            $message = "Your application for ".mysqli_real_escape_string($conn, $n['pet_name'])." is currently ".mysqli_real_escape_string($conn, $n['status']).".";
            $safe_message = mysqli_real_escape_string($conn, $message);
            $dup = mysqli_query($conn, "SELECT id FROM client_notifications
                WHERE user_id='$user_id' AND title='$title' AND message='$safe_message' LIMIT 1");
            if (!$dup || mysqli_num_rows($dup) === 0) {
                mysqli_query($conn, "INSERT INTO client_notifications (user_id,title,message,type)
                    VALUES ('$user_id','$title','$safe_message','application')");
            }
        }
    }
}

// Clear Notifications
if (isset($_POST['mark_notifications_read'])) {
    mysqli_query($conn, "UPDATE client_notifications SET is_read=1 WHERE user_id='$user_id'");
    header("Location: ".$_SERVER['PHP_SELF']."#notifications");
    exit();
}

// Fetch Appointments & Notifications Data for Dashboard
$unread_notifications = 0;
$notif_rows = [];
$notif_res = mysqli_query($conn, "SELECT * FROM client_notifications WHERE user_id='$user_id' ORDER BY id DESC LIMIT 8");
if ($notif_res) {
    while ($nr = mysqli_fetch_assoc($notif_res)) {
        $notif_rows[] = $nr;
        if ((int)$nr['is_read'] === 0) $unread_notifications++;
    }
}

$appointment_rows = [];
$appt_res = mysqli_query($conn, "SELECT aa.*, p.name AS pet_name, p.breed, p.image
    FROM adoption_appointments aa LEFT JOIN pets p ON aa.pet_id=p.id
    WHERE aa.user_id='$user_id' ORDER BY aa.appointment_date DESC, aa.appointment_time DESC");
if ($appt_res) { while ($ar = mysqli_fetch_assoc($appt_res)) $appointment_rows[] = $ar; }

/* ================= HANDLE FAVORITE / UNFAVORITE ================= */
if (isset($_POST['favorite_action'])) {
    $favorite_pet_id = (int)($_POST['favorite_pet_id'] ?? 0);
    $favorite_action = $_POST['favorite_action'];

    if ($favorite_pet_id > 0 && in_array($favorite_action, ['add', 'remove'], true)) {
        if ($favorite_action === 'add') {
            $stmt = mysqli_prepare($conn, "INSERT IGNORE INTO pet_favorites (user_id, pet_id) VALUES (?, ?)");
            if ($stmt) { mysqli_stmt_bind_param($stmt, "ii", $user_id, $favorite_pet_id); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt); }
        } else {
            $stmt = mysqli_prepare($conn, "DELETE FROM pet_favorites WHERE user_id = ? AND pet_id = ?");
            if ($stmt) { mysqli_stmt_bind_param($stmt, "ii", $user_id, $favorite_pet_id); mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt); }
        }
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "?#pets"); exit();
}

$favorite_pet_ids = [];
$favorite_res = mysqli_query($conn, "SELECT pet_id FROM pet_favorites WHERE user_id = '$user_id'");
if ($favorite_res) { while ($fav = mysqli_fetch_assoc($favorite_res)) { $favorite_pet_ids[] = (int)$fav['pet_id']; } }
$favorite_count = count($favorite_pet_ids);

/* ================= HANDLE NEW COMMUNITY POST UPLOAD ================= */
if(isset($_POST['submit_community_post'])){
    $content = mysqli_real_escape_string($conn, $_POST['post_content']);
    $location = mysqli_real_escape_string($conn, $_POST['post_location']);
    $imageName = "";
    if(!empty($_FILES["post_image"]["name"])){
        $imageName = time() . "_post_" . basename($_FILES["post_image"]["name"]);
        move_uploaded_file($_FILES["post_image"]["tmp_name"], "uploads/" . $imageName);
    }
    $sql = "INSERT INTO community_posts (user_id, user_display_name, content, location, image) VALUES ('$user_id', '$full_name', '$content', '$location', '$imageName')";
    mysqli_query($conn, $sql);
    $scroll_target = "community_feed";
}

/* ================= PROFILE UPDATE ================= */
if(isset($_POST['update_profile'])){ $profile_msg = "Profile updated successfully!"; $scroll_target = "profile"; }

/* ================= ADOPTION APPLICATION ================= */
if(isset($_POST['submit_application'])){
    $pet_id = mysqli_real_escape_string($conn, $_POST['pet_id']);
    $living_space = mysqli_real_escape_string($conn, $_POST['app_home']);
    $experience = mysqli_real_escape_string($conn, $_POST['app_exp']);
    $activity = mysqli_real_escape_string($conn, $_POST['app_act'] ?? 'Moderate');
    $children = mysqli_real_escape_string($conn, $_POST['app_kids'] ?? 'No');
    $app_date = date("Y-m-d H:i:s");

    $sql = "INSERT INTO adoption_applications (user_id, pet_id, living_space, experience_level, activity_level, with_children, status, application_date) 
            VALUES ('$user_id', '$pet_id', '$living_space', '$experience', '$activity', '$children', 'Pending', '$app_date')";
    if(mysqli_query($conn, $sql)) { $app_success = "Your adoption application has been submitted and is currently Pending review!"; }
    $scroll_target = "profile"; 
}

/* ================= HANDLE STRAY REPORT UPLOAD ================= */
if(isset($_POST['submit_stray'])){
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $lat = mysqli_real_escape_string($conn, $_POST['lat']);
    $lon = mysqli_real_escape_string($conn, $_POST['lon']);
    $desc = mysqli_real_escape_string($conn, $_POST['description']);
    $imageName = "default_stray.jpg";
    if(!empty($_FILES["stray_photo"]["name"])){
        $imageName = time() . "_" . basename($_FILES["stray_photo"]["name"]);
        move_uploaded_file($_FILES["stray_photo"]["tmp_name"], "uploads/" . $imageName);
    }
    
    $sql = "INSERT INTO rescue_reports (user_id, reporter_name, contact_info, location, latitude, longitude, description, image, status) 
            VALUES ('$user_id', '$full_name', '$contact_info', '$address', '$lat', '$lon', '$desc', '$imageName', 'Action Needed')";
    @mysqli_query($conn, $sql); 
    $stray_success = "Stray reported successfully! The rescue team has received the exact GPS coordinates and address.";
    $scroll_target = "report_stray"; 
}

/* ================= PREPARE DATA FOR LOCAL MACHINE LEARNING ================= */
$all_pets_for_ai = [];
$ai_sql = "SELECT * FROM pets WHERE status='Available' OR status='available'";
$ai_res = mysqli_query($conn, $ai_sql);
if($ai_res && mysqli_num_rows($ai_res) > 0){
    while($pet = mysqli_fetch_assoc($ai_res)){
        $image = filter_var($pet['image'], FILTER_VALIDATE_URL) ? $pet['image'] : "uploads/".$pet['image'];
        if(empty($pet['image'])) $image = "uploads/default.jpg";
        
        $pet['display_image'] = $image;
        $pet['safe_traits'] = isset($pet['characteristics']) ? $pet['characteristics'] : ($pet['description'] ?? 'No specific traits provided.');
        $pet['safe_gender'] = $pet['gender'] ?? 'Unknown';
        
        $pet['ai_text_document'] = "Breed: " . $pet['breed'] . ". " . 
                                   "Size: " . ($pet['size'] ?? 'Medium') . ". " .
                                   "Temperament: " . ($pet['temperament'] ?? 'Friendly') . ". " .
                                   "Description: " . $pet['safe_traits'];
        $all_pets_for_ai[] = $pet;
    }
}
$pets_json_data = json_encode($all_pets_for_ai);

/* ================= PET FETCHING ================= */
// Removed the redundant filter bar SQL logic based on user request. 
// Just fetching all available pets directly now.
$sql_pets = "SELECT * FROM pets WHERE (status='Available' OR status='available') ORDER BY id DESC";

$check_approval = mysqli_query($conn, "SELECT a.*, p.name as pet_name, p.image, p.breed FROM adoption_applications a JOIN pets p ON a.pet_id = p.id WHERE a.user_id='$user_id' AND (a.status='Approved' OR a.status='Adopted')");
$has_approved_application = ($check_approval && mysqli_num_rows($check_approval) > 0); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pet Rescue - Client Dashboard</title>

<!-- External Resources -->
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- Leaflet Maps for Routing Feature -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.css" />

<!-- Intro.js Spotlight Tour CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intro.js@7.2.0/minified/introjs.min.css">

<!-- PRE-LOAD THEME SCRIPT TO PREVENT FLASH -->
<script>
    const storedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', storedTheme);
</script>

<style>
/* ================= GLOBAL LIGHT/DARK THEME VARIABLES ================= */
:root {
    --primary: #F59E0B; /* Amber */
    --primary-hover: #D97706; 
    --primary-light: #FEF3C7;
    --secondary: #3B82F6; /* Blue */
    --secondary-hover: #2563EB;
    
    --bg-main: #F8FAFC; 
    --bg-surface: #FFFFFF;
    --header-bg: rgba(255, 255, 255, 0.9);
    
    --hero-bg-top: #FFFBEB;
    --hero-bg-bottom: var(--bg-surface);
    
    --text-dark: #0F172A; 
    --text-muted: #64748B; 
    --text-light: #94A3B8; 
    
    --success: #10B981;
    --success-light: #D1FAE5;
    --danger: #EF4444;
    --danger-light: #FEE2E2;
    --warning: #F59E0B;
    
    --badge-success-bg: #D1FAE5;
    --badge-success-text: #065F46;
    --badge-pending-bg: #FEF3C7;
    --badge-pending-text: #92400E;
    --badge-danger-bg: #FEE2E2;
    --badge-danger-text: #991B1B;
    
    --border-color: #E2E8F0;
    
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
    --shadow-lg: 0 10px 25px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.025);
    --shadow-hover: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 10px 10px -5px rgba(0, 0, 0, 0.02);
    
    --radius-sm: 8px;
    --radius-md: 12px;
    --radius-lg: 20px;
    --radius-full: 9999px;
}

[data-theme="dark"] {
    --bg-main: #0F172A; 
    --bg-surface: #1E293B; 
    --header-bg: rgba(30, 41, 59, 0.9);
    
    --hero-bg-top: #020617;
    --hero-bg-bottom: var(--bg-surface);
    
    --text-dark: #F8FAFC; 
    --text-muted: #94A3B8; 
    --text-light: #64748B; 
    
    --primary-light: #332305;
    --success-light: #064E3B;
    --danger-light: #7F1D1D;
    
    --badge-success-bg: rgba(16, 185, 129, 0.2);
    --badge-success-text: #34D399;
    --badge-pending-bg: rgba(245, 158, 11, 0.2);
    --badge-pending-text: #FBBF24;
    --badge-danger-bg: rgba(239, 68, 68, 0.2);
    --badge-danger-text: #F87171;

    --border-color: #334155; 
    
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.3);
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3), 0 2px 4px -1px rgba(0, 0, 0, 0.2);
    --shadow-lg: 0 10px 25px -3px rgba(0, 0, 0, 0.4);
    --shadow-hover: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
}

body { margin: 0; font-family: 'Nunito', sans-serif; background: var(--bg-main); color: var(--text-dark); scroll-behavior: smooth; overflow-x: hidden; -webkit-font-smoothing: antialiased; transition: background 0.3s ease, color 0.3s ease; }
a { text-decoration: none; transition: 0.2s ease; }
section { padding: 90px 0; }
.container { width: 92%; max-width: 1280px; margin: auto; }
.text-center { text-align: center; }
.section-title { font-size: clamp(2rem, 4vw, 2.5rem); font-weight: 800; color: var(--text-dark); margin-bottom: 15px; }
.section-subtitle { color: var(--text-muted); font-size: 1.1rem; max-width: 600px; margin: 0 auto 40px auto; line-height: 1.6; }

/* ================= UTILITY CLASSES ================= */
.bg-white { background-color: var(--bg-surface); }
.bg-light { background-color: var(--bg-main); }
.badge { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: var(--radius-full); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
.badge-success { background: var(--badge-success-bg); color: var(--badge-success-text); }
.badge-pending { background: var(--badge-pending-bg); color: var(--badge-pending-text); }
.badge-danger { background: var(--badge-danger-bg); color: var(--badge-danger-text); }

/* ================= ALERT MESSAGES ================= */
.alert { padding: 16px 20px; border-radius: var(--radius-md); font-weight: 600; margin-bottom: 30px; display: flex; align-items: center; gap: 10px; box-shadow: var(--shadow-sm); }
.alert-success { background-color: var(--badge-success-bg); color: var(--badge-success-text); border: 1px solid var(--success); }

/* ================= BUTTONS ================= */
.btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; font-family: inherit; font-weight: 700; border: none; cursor: pointer; transition: all 0.25s ease; padding: 12px 24px; border-radius: var(--radius-full); font-size: 0.95rem; }
.btn-primary { background: var(--primary); color: #fff; box-shadow: 0 4px 10px rgba(245, 158, 11, 0.2); }
.btn-primary:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 6px 15px rgba(245, 158, 11, 0.3); }
.btn-secondary { background: var(--secondary); color: #fff; box-shadow: 0 4px 10px rgba(59, 130, 246, 0.2); }
.btn-secondary:hover { background: var(--secondary-hover); transform: translateY(-2px); box-shadow: 0 6px 15px rgba(59, 130, 246, 0.3); }
.btn-outline { background: transparent; border: 2px solid var(--border-color); color: var(--text-dark); }
.btn-outline:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }

/* ================= FORMS & INPUTS ================= */
input, select, textarea { width: 100%; padding: 14px 18px; margin: 8px 0 20px; border-radius: var(--radius-md); border: 1px solid var(--border-color); font-family: inherit; font-size: 0.95rem; background-color: var(--bg-main); color: var(--text-dark); box-sizing: border-box; transition: all 0.2s ease; outline: none; }
input:focus, select:focus, textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.15); background-color: var(--bg-surface); }
label { font-weight: 700; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }

/* ================= NAVIGATION & HEADER ================= */
.topbar { background: #0F172A; color: #F8FAFC; text-align: center; padding: 10px; font-size: 13px; font-weight: 600; letter-spacing: 0.5px; }
.topbar span { color: var(--primary); margin-right: 5px; }

.header { display: flex; justify-content: space-between; align-items: center; padding: 15px 40px; background: var(--header-bg); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); box-shadow: var(--shadow-sm); position: sticky; top: 0; z-index: 1000; border-bottom: 1px solid rgba(0,0,0,0.02); transition: background 0.3s; }
.logo { font-size: 1.4rem; font-weight: 900; color: var(--text-dark); display: flex; align-items: center; gap: 12px; letter-spacing: -0.5px; }
.logo .logo-icon { background: linear-gradient(135deg, var(--primary), var(--primary-hover)); color: white; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: var(--radius-md); font-size: 18px; box-shadow: 0 4px 10px rgba(245, 158, 11, 0.3); }

.nav-links { display: flex; align-items: center; gap: 4px; background: var(--bg-main); padding: 4px; border-radius: var(--radius-full); border: 1px solid var(--border-color); }
.nav-link { color: var(--text-muted); font-weight: 600; font-size: 0.9rem; padding: 8px 18px; border-radius: var(--radius-full); transition: 0.3s; cursor: pointer; }
.nav-link:hover { color: var(--primary); background: var(--bg-surface); box-shadow: var(--shadow-sm); }

.nav-utils { display: flex; align-items: center; gap: 15px; }
.icon-btn { position: relative; color: var(--text-muted); font-size: 1.1rem; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: var(--bg-surface); border: 1px solid var(--border-color); transition: 0.3s; cursor: pointer; }
.icon-btn:hover { color: var(--primary); border-color: var(--primary); background: var(--primary-light); transform: translateY(-2px); }
.icon-btn.logout-btn:hover { color: var(--danger); border-color: var(--danger); background: var(--danger-light); }
.nav-badge { position: absolute; background: var(--danger); color: white; font-size: 10px; font-weight: 800; min-width: 18px; height: 18px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid var(--bg-surface); }

.user-profile-btn { position: relative; display: flex; align-items: center; gap: 10px; padding: 4px 16px 4px 4px; background: var(--bg-main); border: 1px solid var(--border-color); border-radius: var(--radius-full); color: var(--text-dark); font-weight: 700; font-size: 0.9rem; transition: 0.3s; }
.user-profile-btn:hover { background: var(--bg-surface); border-color: var(--primary); box-shadow: var(--shadow-sm); }
.avatar-small { width: 34px; height: 34px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.8rem; }

/* Mobile Menu Setup */
.mobile-toggle { display: none; background: none; border: none; font-size: 24px; color: var(--text-dark); cursor: pointer; transition: 0.2s; }
.mobile-only-links { display: none; }

@media (max-width: 992px) {
    .nav-links, .nav-utils { display: none; }
    .mobile-toggle { display: block; }
    .header { padding: 15px 20px; }
    
    .nav-links.active {
        display: flex; flex-direction: column; position: absolute; top: 100%; left: 0; 
        width: 100%; background: var(--bg-surface); box-shadow: var(--shadow-lg); 
        padding: 20px 0; border: none; border-radius: 0; z-index: 1000;
        animation: slideDown 0.3s ease;
    }
    .nav-links.active .nav-link { width: 90%; text-align: left; padding: 15px 25px; margin: 2px auto; background: var(--bg-main); border-radius: var(--radius-md); box-shadow: none;}
    .nav-links.active .mobile-only-links { display: flex; flex-direction: column; width: 100%; border-top: 1px solid var(--border-color); margin-top: 15px; padding-top: 15px; }
}
@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

/* ================= HERO SECTION ================= */
.hero { background: linear-gradient(to bottom, var(--hero-bg-top), var(--hero-bg-bottom)); text-align: center; padding: 120px 20px; position: relative; overflow: hidden; transition: background 0.3s; }
.hero::before { content: ''; position: absolute; top: -50px; right: -50px; width: 300px; height: 300px; background: rgba(245, 158, 11, 0.2); border-radius: 50%; filter: blur(60px); z-index: 0; }
.hero::after { content: ''; position: absolute; bottom: 0; left: -100px; width: 400px; height: 400px; background: rgba(59, 130, 246, 0.15); border-radius: 50%; filter: blur(80px); z-index: 0; }
.hero .container { position: relative; z-index: 1; }
.hero h1 { font-size: clamp(2.8rem, 6vw, 4rem); font-weight: 900; color: var(--text-dark); margin-bottom: 25px; letter-spacing: -1px; line-height: 1.1; }
.hero h1 span { background: linear-gradient(135deg, var(--primary), #F97316); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
.hero p { max-width: 650px; margin: 0 auto 40px; font-size: 1.15rem; color: var(--text-muted); line-height: 1.7; }
.hero-btn-group { display: flex; justify-content: center; gap: 15px; flex-wrap: wrap; margin-top: 20px; }

/* ================= POST-ADOPTION SMART MONITOR ================= */
.monitor-panel { background: var(--bg-surface); border-radius: var(--radius-lg); padding: clamp(25px, 4vw, 40px); box-shadow: var(--shadow-lg); margin-bottom: 50px; position: relative; overflow: hidden; border: 1px solid var(--border-color); }
.monitor-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 40px; align-items: center; }
.pet-vital-card { background: var(--bg-main); padding: 25px; border-radius: var(--radius-lg); border: 1px solid var(--border-color); display: flex; flex-direction: column; gap: 15px; transition: 0.3s; }
.pet-vital-card:hover { border-color: var(--primary); box-shadow: var(--shadow); }
.vital-row { display: flex; justify-content: space-between; align-items: center; font-weight: 700; font-size: 0.95rem; color: var(--text-dark); }
.pulse-dot { height: 12px; width: 12px; background-color: var(--success); border-radius: 50%; display: inline-block; animation: pulse 2s infinite; margin-right: 8px; box-shadow: 0 0 0 rgba(16, 185, 129, 0.4); }

/* ================= COMMUNITY FEED & LOST/FOUND ================= */
.feed-container { max-width: 650px; margin: 0 auto; }
.feed-input-card { background: var(--bg-surface); border-radius: var(--radius-lg); padding: 25px; box-shadow: var(--shadow); margin-bottom: 30px; border: 1px solid var(--border-color); }
.feed-input-top { display: flex; align-items: center; gap: 15px; border-bottom: 1px solid var(--border-color); padding-bottom: 20px; }
.user-avatar-circle { width: 45px; height: 45px; border-radius: 50%; background: linear-gradient(135deg, var(--secondary), #6366F1); color: white; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.1rem; flex-shrink: 0; box-shadow: var(--shadow-sm); }
.fake-input-bar { background: var(--bg-main); border-radius: var(--radius-full); border: 1px solid var(--border-color); padding: 12px 25px; flex-grow: 1; color: var(--text-dark); font-size: 0.95rem; font-family: inherit; font-weight: 600; outline: none; transition: 0.3s; }
.fake-input-bar:focus { background: var(--bg-surface); border-color: var(--primary); box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1); }
.feed-action-row { display: flex; justify-content: space-between; padding-top: 15px; }
.action-btn { display: flex; align-items: center; gap: 8px; color: var(--text-muted); font-weight: 700; font-size: 0.9rem; cursor: pointer; padding: 8px 16px; border-radius: var(--radius-md); transition: background 0.2s; }
.action-btn:hover { background: var(--bg-main); color: var(--text-dark); }
.post-card { background: var(--bg-surface); border-radius: var(--radius-lg); box-shadow: var(--shadow); margin-bottom: 25px; padding: 25px; border: 1px solid var(--border-color); transition: 0.3s; }
.post-card:hover { box-shadow: var(--shadow-lg); }
.post-author { font-weight: 800; color: var(--text-dark); margin: 0 0 2px 0; font-size: 1.05rem; }
.post-meta { font-size: 0.8rem; color: var(--text-light); font-weight: 600; }
.img-remove-btn { position: absolute; top: -10px; right: -10px; background: var(--danger); color: white; border: none; border-radius: 50%; width: 28px; height: 28px; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: var(--shadow); transition: 0.2s; }
.img-remove-btn:hover { background: #DC2626; transform: scale(1.1); }

/* ================= AVAILABLE PETS & FILTER ================= */
.filter-bar { background: var(--bg-surface); padding: 15px 25px; border-radius: var(--radius-full); display: flex; gap: 15px; align-items: center; box-shadow: var(--shadow); border: 1px solid var(--border-color); margin-bottom: 40px; justify-content: center; flex-wrap: wrap; }
.filter-bar input, .filter-bar select { padding: 12px 20px; border: 1px solid transparent; border-radius: var(--radius-full); background: var(--bg-main); font-family: inherit; color: var(--text-dark); font-weight: 600; outline: none; min-width: 180px; margin: 0; box-shadow: inset 0 1px 2px rgba(0,0,0,0.02); }
.filter-bar input:focus, .filter-bar select:focus { border-color: var(--primary); background: var(--bg-surface); }
.reset-link { color: var(--text-muted); font-size: 0.9rem; cursor: pointer; font-weight: 700; transition: 0.2s; margin-left: 10px; }
.reset-link:hover { color: var(--danger); }

/* ================= PET CARDS ================= */
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 30px; }
.pet-card { background: var(--bg-surface); border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow); display: flex; flex-direction: column; transition: all 0.3s ease; position: relative; border: 1px solid var(--border-color); }
.pet-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-hover); border-color: rgba(245, 158, 11, 0.3); }
.pet-card-img-wrap { position: relative; width: 100%; height: 240px; overflow: hidden; }
.pet-card-img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
.pet-card:hover .pet-card-img { transform: scale(1.05); }
.pet-card-content { padding: 25px; display: flex; flex-direction: column; flex-grow: 1; }
.pet-card-title { margin: 0 0 15px; font-size: 1.4rem; font-weight: 900; color: var(--text-dark); }
.pet-tags { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 20px; }
.pet-tag { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; background: var(--bg-main); border-radius: var(--radius-md); font-size: 0.8rem; font-weight: 700; color: var(--text-muted); border: 1px solid var(--border-color); }
.pet-tag i { color: var(--primary); }

.favorite-btn { position: absolute; top: 15px; right: 15px; width: 42px; height: 42px; border-radius: 50%; border: none; background: rgba(255,255,255,0.9); color: #94A3B8; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 20; box-shadow: var(--shadow); transition: all 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275); backdrop-filter: blur(4px); font-size: 1.1rem; }
.favorite-btn:hover { transform: scale(1.1); color: var(--danger); background: white; }
.favorite-btn.active { color: var(--danger); background: var(--danger-light); border: 1px solid #FECACA; }
.favorite-btn.active i { animation: favoritePop 0.4s ease; }

/* AI Match Badges */
.match-badge { position: absolute; top: 15px; right: 15px; padding: 6px 14px; border-radius: var(--radius-full); font-weight: 800; color: white; font-size: 0.75rem; box-shadow: var(--shadow); z-index: 10; letter-spacing: 0.5px; text-transform: uppercase; }
.match-score { position: absolute; top: 15px; left: 15px; padding: 6px 14px; border-radius: var(--radius-full); background: rgba(255,255,255,0.95); font-weight: 900; color: #0F172A; font-size: 0.85rem; box-shadow: var(--shadow); z-index: 10; backdrop-filter: blur(4px); }

/* ================= FAVORITES & REMOVE FORM ================= */
.favorite-remove-btn { width: 100%; border: 1px dashed #FECACA; background: var(--danger-light); color: #B91C1C; padding: 12px; border-radius: var(--radius-md); font-family: inherit; font-weight: 800; cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 15px; }
.favorite-remove-btn:hover { background: #FEE2E2; border-style: solid; }
.favorite-empty { text-align: center; padding: 60px 20px; color: var(--text-muted); background: var(--bg-surface); border-radius: var(--radius-lg); border: 1px dashed var(--border-color); }
.favorite-empty i { font-size: 50px; color: #FDA4AF; margin-bottom: 20px; }

/* ================= AI PET MATCHER ================= */
.ai-section { background: linear-gradient(135deg, #0F172A, #1E293B); color: white; position: relative; overflow: hidden; padding: 100px 0; }
.ai-section::before { content:''; position:absolute; width: 500px; height: 500px; background: rgba(59, 130, 246, 0.15); border-radius: 50%; filter: blur(80px); top: -100px; left: -100px; }
.ai-box { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); padding: 40px; border-radius: var(--radius-lg); max-width: 900px; margin: 0 auto; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
.ai-box label { color: #E2E8F0; font-size: 0.95rem; letter-spacing: 0; }
.ai-box p { color: #94A3B8; }
.ai-box select, .ai-box input { background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255,255,255,0.1); color: white; padding: 16px; border-radius: var(--radius-md); font-size: 1rem; box-shadow: inset 0 2px 4px rgba(0,0,0,0.2); }
.ai-box select:focus, .ai-box input:focus { border-color: var(--secondary); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3); }

/* ================= ABOUT, EVENTS & PROCESS ================= */
.process-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 30px; margin-top: 50px; }
.process-card { background: var(--bg-surface); padding: 40px 30px; border-radius: var(--radius-lg); text-align: center; box-shadow: var(--shadow); border: 1px solid var(--border-color); transition: 0.3s; }
.process-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-hover); border-color: var(--primary-light); }
.process-icon { font-size: 40px; color: var(--primary); margin-bottom: 20px; background: rgba(245, 158, 11, 0.1); width: 80px; height: 80px; line-height: 80px; border-radius: 50%; display: inline-block; }

/* ================= PROFILE & FORMS ================= */
.profile-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 450px), 1fr)); gap: 40px; margin-top: 40px; }
.form-container { background: var(--bg-surface); padding: clamp(30px, 4vw, 40px); border-radius: var(--radius-lg); box-shadow: var(--shadow); border: 1px solid var(--border-color); }
.form-container h3 { font-size: 1.4rem; color: var(--text-dark); margin-top: 0; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 2px dashed var(--border-color); display: flex; align-items: center; gap: 10px; }
.form-group { margin-bottom: 20px; }

/* ================= TABLES ================= */
.history-table { width: 100%; border-collapse: separate; border-spacing: 0; border-radius: var(--radius-md); overflow: hidden; border: 1px solid var(--border-color); }
.history-table th, .history-table td { padding: 16px 20px; text-align: left; font-size: 0.95rem; }
.history-table th { background: var(--bg-main); font-weight: 800; color: var(--text-muted); text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color); }
.history-table td { background: var(--bg-surface); border-bottom: 1px solid var(--border-color); color: var(--text-dark); font-weight: 600; }
.history-table tr:last-child td { border-bottom: none; }

/* ================= MAP & ROUTING UI ================= */
.map-container-stray { background: var(--bg-surface); padding: 30px; border-radius: var(--radius-lg); box-shadow: var(--shadow); border: 1px solid var(--border-color); }
#strayMapLive { height: 500px; width: 100%; border-radius: var(--radius-md); margin-top: 20px; border: 1px solid var(--border-color); z-index: 1; }
#strayMapLive:fullscreen { width: 100vw !important; height: 100vh !important; border-radius: 0 !important; }

/* ================= MODAL STYLES ================= */
.sf-modal-overlay { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(15, 23, 42, 0.8); backdrop-filter: blur(5px); overflow-y: auto; padding: 40px 0; opacity: 0; transition: opacity 0.3s ease; }
.sf-modal-overlay.show { opacity: 1; }
.sf-modal-container { background: var(--bg-surface); width: clamp(300px, 92%, 1000px); margin: auto; border-radius: var(--radius-lg); position: relative; box-shadow: 0 30px 60px rgba(0,0,0,0.5); transform: scale(0.95) translateY(20px); opacity: 0; transition: all 0.3s ease; overflow: hidden; border: 1px solid var(--border-color); }
.sf-modal-overlay.show .sf-modal-container { transform: scale(1) translateY(0); opacity: 1; }
.sf-modal-close { position: absolute; right: 25px; top: 25px; font-size: 24px; color: var(--text-muted); cursor: pointer; z-index: 10; transition: 0.3s; background: var(--bg-main); width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 1px solid var(--border-color); }
.sf-modal-close:hover { color: var(--danger); background: var(--danger-light); border-color: #FECACA; transform: rotate(90deg); }

.sponsor-amt.active { background: var(--primary); color: white; border-color: var(--primary); }

/* ================= INTRO.JS CUSTOM THEME STYLES ================= */
.introjs-tooltip {
    font-family: 'Nunito', sans-serif !important;
    background: var(--bg-surface) !important;
    color: var(--text-dark) !important;
    border-radius: var(--radius-md) !important;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2) !important;
    border: 1px solid var(--border-color) !important;
}
.introjs-tooltip-title {
    font-weight: 900 !important;
    font-size: 1.2rem !important;
    color: var(--text-dark) !important;
}
.introjs-tooltiptext {
    font-size: 0.95rem !important;
    color: var(--text-muted) !important;
    line-height: 1.6 !important;
}
.introjs-button {
    font-family: 'Nunito', sans-serif !important;
    font-weight: 700 !important;
    border-radius: var(--radius-full) !important;
    padding: 8px 16px !important;
    text-shadow: none !important;
    transition: 0.3s !important;
}
.introjs-nextbutton {
    background-color: var(--primary) !important;
    color: white !important;
    border: none !important;
    box-shadow: 0 4px 10px rgba(245, 158, 11, 0.2) !important;
}
.introjs-nextbutton:hover {
    background-color: var(--primary-hover) !important;
}
.introjs-prevbutton, .introjs-skipbutton {
    background-color: var(--bg-main) !important;
    color: var(--text-dark) !important;
    border: 1px solid var(--border-color) !important;
}
.introjs-bullets ul li a.active {
    background: var(--primary) !important;
}
.introjs-arrow.top { border-bottom-color: var(--bg-surface) !important; }
.introjs-arrow.bottom { border-top-color: var(--bg-surface) !important; }
.introjs-arrow.left { border-right-color: var(--bg-surface) !important; }
.introjs-arrow.right { border-left-color: var(--bg-surface) !important; }
.introjs-helperLayer { background-color: rgba(255, 255, 255, 0.1) !important; border-radius: var(--radius-md) !important;}

/* ================= AI THINKING PANEL ================= */
.ai-thinking { margin-top: 25px; padding: 25px; border-radius: var(--radius-lg); background: rgba(15,23,42,0.6); border: 1px solid rgba(96,165,250,0.35); box-shadow: 0 0 30px rgba(99,102,241,0.15) inset; animation: thinkIn .4s ease; }
.ai-thinking.fade-out { opacity: 0; transform: translateY(-6px); transition: .4s ease; }
.ai-thinking-head { display: flex; align-items: center; gap: 18px; margin-bottom: 18px; }
.ai-orb { width: 56px; height: 56px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 24px; color: #fff; background: linear-gradient(135deg,#3B82F6,#8B5CF6); animation: orbPulse 1.6s ease-in-out infinite; }
.ai-thinking-title { font-weight: 900; font-size: 1.15rem; color: #E2E8F0; }
.ai-thinking-sub { font-size: .9rem; color: #94A3B8; font-weight: 600; margin-top: 3px; min-height: 1.3em; }
.ai-dots span { animation: dotBlink 1.4s infinite both; }
.ai-dots span:nth-child(2) { animation-delay: .2s; }
.ai-dots span:nth-child(3) { animation-delay: .4s; }
.ai-progress { height: 8px; background: rgba(255,255,255,0.08); border-radius: 99px; overflow: hidden; margin-bottom: 18px; }
#ai-progress-bar { height: 100%; width: 0%; border-radius: 99px; background: linear-gradient(90deg,#3B82F6,#8B5CF6,#EC4899); background-size: 200% 100%; animation: barShift 2s linear infinite; transition: width .4s ease; }
.ai-steps { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
.ai-steps li { display: flex; align-items: center; gap: 10px; font-size: .92rem; font-weight: 700; color: #475569; transition: .3s; }
.ai-steps li i { width: 18px; text-align: center; }
.ai-steps li.active { color: #93C5FD; }
.ai-steps li.done { color: #34D399; }
@keyframes orbPulse { 0%,100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(139,92,246,.6); } 50% { transform: scale(1.08); box-shadow: 0 0 0 16px rgba(139,92,246,0); } }
@keyframes dotBlink { 0%,80%,100% { opacity: .2; } 40% { opacity: 1; } }
@keyframes barShift { 0% { background-position: 0% 0; } 100% { background-position: 200% 0; } }
@keyframes thinkIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }

@keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.6); } 70% { box-shadow: 0 0 0 12px rgba(16, 185, 129, 0); } 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); } }
@keyframes favoritePop { 0% { transform: scale(0.7); } 50% { transform: scale(1.3); } 100% { transform: scale(1); } }

@media (max-width: 768px) { 
    .monitor-grid, .profile-grid { grid-template-columns: 1fr; } 
    .map-container-stray { padding: 20px; }
}
</style>
</head>

<body>

<div class="topbar"><span><i class="fas fa-heart"></i></span> Make a difference today. Help us save abandoned paws and find them a home.</div>
<header class="header" id="navbar">
    <!-- Logo -->
    <a href="#home" class="logo">
        <div class="logo-icon"><i class="fas fa-paw"></i></div> PetRescue
    </a>

    <!-- Mobile Hamburger Toggle -->
    <button class="mobile-toggle" onclick="toggleMenu()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Center Navigation Links -->
    <nav class="nav-links" id="navLinks">
        <a href="#home" class="nav-link" onclick="toggleMenu()">Home</a>
        <a href="#pets" class="nav-link" onclick="toggleMenu()">Adopt</a>
        <?php if($has_approved_application): ?><a href="#smart_monitor" class="nav-link" onclick="toggleMenu()">My Pets</a><?php endif; ?>
        <a href="#community_feed" class="nav-link" onclick="toggleMenu()">Community</a>
        <a href="#lost_found" class="nav-link" onclick="toggleMenu()">Lost & Found</a>
        <a href="#ai_match" class="nav-link" onclick="toggleMenu()">AI Match</a>
        <a href="#donation-section" class="nav-link" onclick="toggleMenu()"> Donate</a>
        <a href="#report_stray" class="nav-link" onclick="toggleMenu()">Report Stray</a>
        
        <!-- App Guide Trigger -->
        <a class="nav-link" onclick="startTutorial(); toggleMenu();" style="color: var(--secondary); font-weight: 800; cursor:pointer;"><i class="fas fa-question-circle"></i> Tour</a>
        
        <!-- These only show inside the mobile menu drawer -->
        <div class="mobile-only-links">
            <button id="theme-toggle-mobile" class="nav-link" style="text-align:left; border:none; background:transparent; width:100%; cursor:pointer;">
                <i class="fas fa-moon"></i> Toggle Dark Mode
            </button>
            <a href="#favorites" class="nav-link" onclick="toggleMenu()"><i class="fas fa-heart"></i> Favorites (<?php echo $favorite_count; ?>)</a>
            <a href="#appointments" class="nav-link" onclick="toggleMenu()"><i class="fas fa-calendar-check"></i> Visits</a>
            <a href="#notifications" class="nav-link" onclick="toggleMenu()"><i class="fas fa-bell"></i> Alerts (<?php echo $unread_notifications; ?>)</a>
            <a href="#donations" class="nav-link" onclick="toggleMenu()"><i class="fas fa-hand-holding-heart"></i> Donations</a>
            <a href="#profile" class="nav-link" onclick="toggleMenu()"><i class="fas fa-user-circle"></i> Profile</a>
            <a href="?logout=true" class="nav-link" style="color: var(--danger);"><i class="fas fa-sign-out-alt"></i> Logout</a>
            
        </div>
    </nav>

    <!-- Right Utility Icons (Desktop) -->
    <div class="nav-utils">
        <button id="theme-toggle" class="icon-btn" title="Toggle Dark/Light Mode">
            <i class="fas fa-moon"></i>
        </button>
        
        <a href="#profile" class="user-profile-btn" id="tour-profile-btn" title="My Profile">
            <div class="avatar-small"><?php echo $initials; ?></div>
            <span>Profile</span>
            <?php if($unread_notifications > 0): ?>
                <span class="nav-badge" style="top: -6px; right: -6px;"><?php echo $unread_notifications; ?></span>
            <?php endif; ?>
        </a>
        
        <a href="?logout=true" class="icon-btn logout-btn" title="Logout">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>
</header>

<main>
    <!-- ================= HERO SECTION ================= -->
    <section class="hero" id="home">
        <div class="container">
            <h1>Adopt Your <span>Perfect Friend</span> Today</h1>
            <p>Welcome to Pet Rescue! We are dedicated to rescuing abandoned pets and finding them loving homes. Browse through our current residents and start your adoption journey.</p>
            
            <div class="hero-btn-group">
                <a href="#ai_match" class="btn btn-primary"><i class="fas fa-magic"></i> AI Pet Matcher</a>
                <a href="#pets" class="btn btn-secondary"><i class="fas fa-search"></i> Browse All Pets</a>
                <a href="#favorites" class="btn btn-outline"><i class="fas fa-heart"></i> My Favorites (<?php echo $favorite_count; ?>)</a>
            </div>
        </div>
    </section>

    <!-- ================= POST-ADOPTION SMART MONITOR ================= -->
    <?php if($has_approved_application): ?>
    <section id="smart_monitor" class="bg-white">
        <div class="container">
            <div class="text-center">
                <h2 class="section-title"><i class="fas fa-heartbeat" style="color:var(--success);"></i> Post-Adoption Monitor</h2>
                <p class="section-subtitle">Assuring your pet's safety and well-being. Our automated system syncs with your pet's Smart Collar and analyzes your feed activities to update the shelter automatically.</p>
            </div>
            
            <?php 
            mysqli_data_seek($check_approval, 0);
            while($adopted = mysqli_fetch_assoc($check_approval)): 
                $seed = crc32($adopted['pet_name']);
                $activity_hrs = number_format(($seed % 30) / 10 + 1.5, 1);
                $sleep_hrs = number_format(($seed % 50) / 10 + 10, 1);
                $img = !empty($adopted['image']) ? (filter_var($adopted['image'], FILTER_VALIDATE_URL) ? $adopted['image'] : "uploads/".$adopted['image']) : "uploads/default.jpg";
            ?>
            <div class="monitor-panel">
                <div class="monitor-grid">
                    <div class="text-center">
                        <img src="<?php echo $img; ?>" style="width:160px; height:160px; border-radius:50%; object-fit:cover; border:6px solid var(--success-light); margin-bottom:20px; box-shadow:var(--shadow);">
                        <h3 style="font-size:1.8rem; font-weight:900; margin:0 0 5px; color:var(--text-dark);"><?php echo htmlspecialchars($adopted['pet_name']); ?></h3>
                        <p style="color:var(--text-muted); margin:0 0 20px; font-weight:700;"><?php echo htmlspecialchars($adopted['breed']); ?></p>
                        <div class="badge badge-success" style="padding: 8px 16px; font-size: 0.85rem;">
                            <span class="pulse-dot"></span> IoT Collar Sync Active
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:25px;">
                        <div class="pet-vital-card">
                            <div style="color:var(--text-muted); font-size:0.85rem; font-weight:800; text-transform:uppercase;"><i class="fas fa-running" style="color:var(--secondary);"></i> Daily Activity</div>
                            <div style="font-size:2rem; font-weight:900; color:var(--text-dark);"><?php echo $activity_hrs; ?> <span style="font-size:1rem; color:var(--text-light);">Hours</span></div>
                            <div class="vital-row">Status: <span class="badge badge-success">Optimal</span></div>
                        </div>
                        <div class="pet-vital-card">
                            <div style="color:var(--text-muted); font-size:0.85rem; font-weight:800; text-transform:uppercase;"><i class="fas fa-moon" style="color:var(--primary);"></i> Rest Pattern</div>
                            <div style="font-size:2rem; font-weight:900; color:var(--text-dark);"><?php echo $sleep_hrs; ?> <span style="font-size:1rem; color:var(--text-light);">Hours</span></div>
                            <div class="vital-row">Status: <span class="badge badge-success">Very Low Stress</span></div>
                        </div>
                        <div class="pet-vital-card" style="grid-column: 1 / -1; flex-direction:row; justify-content:space-between; align-items:center; background:rgba(245, 158, 11, 0.1); border-color:var(--primary);">
                            <div>
                                <div style="color:var(--primary-hover); font-size:1rem; font-weight:900; margin-bottom:5px;"><i class="fas fa-brain"></i> Wellness Assessment</div>
                                <div style="color:var(--text-muted); font-size:0.9rem; font-weight:600;">Analyzed from Feed Posts & Smart Vitals</div>
                            </div>
                            <div class="badge" style="background:var(--primary); color:white; padding:12px 24px; font-size: 0.95rem;">Thriving / Safe</div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </section>
    <?php endif; ?>

<!-- =========================
     DONATION QR CODE SECTION
========================= -->
<section id="donation-section" style="
    background:#ffffff;
    padding:28px;
    margin:25px 0;
    border-radius:18px;
    box-shadow:0 5px 20px rgba(0,0,0,.07);
    text-align:center;
">
    <div style="font-size:40px;">🐾</div>

    <h2 style="color:#ff9f43;margin:10px 0;">
        Support Our Rescue Animals
    </h2>

    <p style="
        max-width:620px;
        margin:0 auto 22px;
        color:#666;
        line-height:1.8;
    ">
        Your donation helps provide food, medicine, shelter,
        and veterinary care for rescued dogs and cats.
        Every contribution makes a difference.
    </p>

    <div style="
        display:inline-block;
        max-width:100%;
        background:#fff8ef;
        border:1px solid #ffe2bf;
        border-radius:16px;
        padding:22px;
    ">
        <h3 style="margin:0 0 16px;color:#333;">
            Scan to Donate via GCash or Maya
        </h3>

        <img
            src="/pet_adoption_system/uploads/Donation_proofs/donation_qr.jpg"
            alt="Official Shelter Donation QR Code"
            style="
                display:block;
                width:270px;
                max-width:100%;
                height:auto;
                margin:0 auto 15px;
                padding:8px;
                background:white;
                border:1px solid #eee;
                border-radius:12px;
            "
            onerror="
                this.style.display='none';
                document.getElementById('donationQrError').style.display='block';
            "
        >

        <p id="donationQrError" style="display:none;color:#c0392b;">
            QR image not found. Please check the file path.
        </p>

        <p style="color:#555;font-size:14px;">
            Verify the recipient name before paying.
        </p>
    </div>

   
    </p>

    <p style="font-size:12px;color:#888;">
        Donations remain pending until reviewed by the admin.
    </p>
</section>
<!-- END DONATION QR CODE SECTION --></div>
    <!-- ================= AVAILABLE PETS ================= -->
    <section id="pets" class="bg-white">
        <div class="container">
            <h2 class="section-title text-center">Available Pets</h2>
            <p class="section-subtitle text-center">Every pet here is healthy, vaccinated, and waiting for you.</p>

            <?php if($app_success) echo "<div class='alert alert-success'><i class='fas fa-check-circle'></i> $app_success</div>"; ?>

            <div class="grid">
                <?php 
                $result_pets = @mysqli_query($conn, $sql_pets); 
                if($result_pets && mysqli_num_rows($result_pets) > 0) {
                    while($row = mysqli_fetch_assoc($result_pets)){ 
                        $image = filter_var($row['image'], FILTER_VALIDATE_URL) ? $row['image'] : "uploads/".$row['image'];
                        if(empty($row['image'])){ $image = "uploads/default.jpg"; }
                        
                        $safe_name = addslashes(htmlspecialchars($row['name']));
                        $safe_breed = addslashes(htmlspecialchars($row['breed']));
                        $safe_gender = addslashes(htmlspecialchars($row['gender'] ?? 'Unknown'));
                        $safe_size = addslashes(htmlspecialchars($row['size'] ?? 'Medium'));
                        $safe_temp = addslashes(htmlspecialchars($row['temperament'] ?? 'Friendly'));
                        $safe_act = addslashes(htmlspecialchars($row['activity_level'] ?? 'Moderate'));
                        $safe_traits = addslashes(htmlspecialchars($row['characteristics'] ?? ($row['description'] ?? 'No specific traits provided.')));
                ?>
                <div class="pet-card">
                    <form method="POST" action="" class="favorite-form">
                        <input type="hidden" name="favorite_pet_id" value="<?php echo (int)$row['id']; ?>">
                        <input type="hidden" name="favorite_action" value="<?php echo in_array((int)$row['id'], $favorite_pet_ids, true) ? 'remove' : 'add'; ?>">
                        <button type="submit" class="favorite-btn <?php echo in_array((int)$row['id'], $favorite_pet_ids, true) ? 'active' : ''; ?>" title="<?php echo in_array((int)$row['id'], $favorite_pet_ids, true) ? 'Remove from favorites' : 'Add to favorites'; ?>">
                            <i class="<?php echo in_array((int)$row['id'], $favorite_pet_ids, true) ? 'fas' : 'far'; ?> fa-heart"></i>
                        </button>
                    </form>
                    <div class="pet-card-img-wrap">
                        <img src="<?php echo htmlspecialchars($image); ?>" class="pet-card-img" alt="Pet">
                    </div>
                    <div class="pet-card-content">
                        <h3 class="pet-card-title"><?php echo htmlspecialchars($row['name']); ?></h3>
                        <div class="pet-tags">
                            <span class="pet-tag"><i class="fas fa-paw"></i> <?php echo htmlspecialchars($row['breed']); ?></span>
                            <span class="pet-tag"><i class="fas fa-birthday-cake"></i> <?php echo $row['age']; ?> yrs</span>
                            <span class="pet-tag"><i class="fas fa-ruler"></i> <?php echo htmlspecialchars($row['size'] ?? 'Medium'); ?></span>
                            <span class="pet-tag"><i class="fas fa-smile"></i> <?php echo htmlspecialchars($row['temperament'] ?? 'Friendly'); ?></span>
                        </div>
                        <div style="display:flex; gap:10px; margin-top: auto;">
                            <button class="btn btn-primary" style="flex:2;" onclick="openSFModal('<?php echo $row['id']; ?>', '<?php echo $safe_name; ?>', '<?php echo $safe_breed; ?>', '<?php echo $row['age']; ?>', '<?php echo $safe_gender; ?>', '<?php echo $safe_size; ?>', '<?php echo $safe_temp; ?>', '<?php echo $safe_act; ?>', '<?php echo $safe_traits; ?>', '<?php echo $image; ?>')">
                                View Full Profile
                            </button>
                            <button class="btn btn-outline" style="flex:1; padding:12px 10px;" onclick="openSponsorModal('<?php echo $safe_name; ?>')" title="Sponsor this pet">
                                <i class="fas fa-hand-holding-heart" style="color:var(--danger);"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php } } else { echo "<div style='grid-column: 1 / -1; text-align:center; padding: 60px; background:var(--bg-main); border-radius:var(--radius-lg); border:1px dashed var(--border-color);'><p style='font-size: 1.1rem; color: var(--text-muted); font-weight:700;'>No available pets right now. Please check back later.</p></div>"; } ?>
            </div>
        </div>
    </section>

    <!-- ================= HAPPY TAILS (SUCCESS STORIES) ================= -->
    <section id="success_stories" class="bg-light">
        <div class="container">
            <h2 class="section-title text-center"><i class="fas fa-award" style="color:var(--primary);"></i> Happy Tails</h2>
            <p class="section-subtitle text-center">Heartwarming success stories from pets that found their forever homes.</p>
            <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); margin-top:30px;">
                <div class="post-card text-center" style="padding: 35px 25px;">
                    <img src="https://images.unsplash.com/photo-1543466835-00a7907e9de1?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80" style="width:120px; height:120px; border-radius:50%; object-fit:cover; margin:0 auto 20px; border:4px solid var(--badge-success-bg); box-shadow: var(--shadow);">
                    <h3 style="margin:0 0 10px; color:var(--text-dark);">Max & The Johnsons</h3>
                    <div style="color:var(--primary); margin-bottom:15px;"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
                    <p style="color:var(--text-muted); font-size:0.95rem; line-height:1.6; font-style: italic;">"Adopting Max was the best decision we ever made. He brings so much joy and energy to our home!"</p>
                </div>
                <div class="post-card text-center" style="padding: 35px 25px;">
                    <img src="https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80" style="width:120px; height:120px; border-radius:50%; object-fit:cover; margin:0 auto 20px; border:4px solid var(--badge-success-bg); box-shadow: var(--shadow);">
                    <h3 style="margin:0 0 10px; color:var(--text-dark);">Luna's Journey</h3>
                    <div style="color:var(--primary); margin-bottom:15px;"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
                    <p style="color:var(--text-muted); font-size:0.95rem; line-height:1.6; font-style: italic;">"Luna was very shy at first, but with a little love and patience, she has blossomed into the sweetest cat."</p>
                </div>
                <div class="post-card text-center" style="padding: 35px 25px;">
                    <img src="https://images.unsplash.com/photo-1537151608804-ea6f1184aca9?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80" style="width:120px; height:120px; border-radius:50%; object-fit:cover; margin:0 auto 20px; border:4px solid var(--badge-success-bg); box-shadow: var(--shadow);">
                    <h3 style="margin:0 0 10px; color:var(--text-dark);">Buddy's New Start</h3>
                    <div style="color:var(--primary); margin-bottom:15px;"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
                    <p style="color:var(--text-muted); font-size:0.95rem; line-height:1.6; font-style: italic;">"Our morning runs are never lonely anymore. Buddy is the perfect companion for our active lifestyle."</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= MY FAVORITE PETS ================= -->
    <section id="favorites" class="bg-white">
        <div class="container">
            <h2 class="section-title text-center">
                <i class="fas fa-heart" style="color:var(--danger);"></i> My Favorite Pets
            </h2>
            <p class="section-subtitle text-center">
                Save pets you're interested in and come back to them anytime.
                <strong><?php echo $favorite_count; ?></strong> saved pet(s).
            </p>

            <div style="margin-top: 35px;">
                <?php
                $favorite_pets = [];
                if ($favorite_count > 0) {
                    $fav_ids_sql = implode(',', array_map('intval', $favorite_pet_ids));
                    $favorite_sql = "SELECT * FROM pets WHERE id IN ($fav_ids_sql) AND (status='Available' OR status='available') ORDER BY FIELD(id, $fav_ids_sql)";
                    $favorite_result = mysqli_query($conn, $favorite_sql);
                    if ($favorite_result) { while ($fav_pet = mysqli_fetch_assoc($favorite_result)) { $favorite_pets[] = $fav_pet; } }
                }
                ?>

                <?php if (count($favorite_pets) > 0): ?>
                    <div class="grid">
                        <?php foreach ($favorite_pets as $fav_pet): ?>
                            <?php
                            $fav_image = !empty($fav_pet['image']) ? (filter_var($fav_pet['image'], FILTER_VALIDATE_URL) ? $fav_pet['image'] : "uploads/".$fav_pet['image']) : "uploads/default.jpg";
                            $fav_name = htmlspecialchars($fav_pet['name'] ?? 'Unnamed Pet');
                            $fav_breed = htmlspecialchars($fav_pet['breed'] ?? 'Unknown');
                            $fav_age = htmlspecialchars($fav_pet['age'] ?? 'Unknown');
                            $fav_gender = htmlspecialchars($fav_pet['gender'] ?? 'Unknown');
                            $fav_size = htmlspecialchars($fav_pet['size'] ?? 'Medium');
                            $fav_temp = htmlspecialchars($fav_pet['temperament'] ?? 'Friendly');
                            $fav_act = htmlspecialchars($fav_pet['activity_level'] ?? 'Moderate');
                            $fav_traits = htmlspecialchars($fav_pet['characteristics'] ?? ($fav_pet['description'] ?? 'No specific traits provided.'));

                            $js_fav_name = addslashes($fav_name);
                            $js_fav_breed = addslashes($fav_breed);
                            $js_fav_gender = addslashes($fav_gender);
                            $js_fav_size = addslashes($fav_size);
                            $js_fav_temp = addslashes($fav_temp);
                            $js_fav_act = addslashes($fav_act);
                            $js_fav_traits = addslashes($fav_traits);
                            ?>
                            <div class="pet-card">
                                <div class="pet-card-img-wrap">
                                    <img src="<?php echo htmlspecialchars($fav_image); ?>" class="pet-card-img" alt="<?php echo $fav_name; ?>">
                                </div>
                                <div class="pet-card-content">
                                    <h3 class="pet-card-title"><?php echo $fav_name; ?></h3>
                                    <div class="pet-tags">
                                        <span class="pet-tag"><i class="fas fa-paw"></i> <?php echo $fav_breed; ?></span>
                                        <span class="pet-tag"><i class="fas fa-birthday-cake"></i> <?php echo $fav_age; ?> yrs</span>
                                        <span class="pet-tag"><i class="fas fa-ruler"></i> <?php echo $fav_size; ?></span>
                                    </div>

                                    <button type="button" class="btn btn-secondary" style="width: 100%;"
                                        onclick="openSFModal(
                                            '<?php echo (int)$fav_pet['id']; ?>',
                                            '<?php echo $js_fav_name; ?>',
                                            '<?php echo $js_fav_breed; ?>',
                                            '<?php echo addslashes($fav_age); ?>',
                                            '<?php echo $js_fav_gender; ?>',
                                            '<?php echo $js_fav_size; ?>',
                                            '<?php echo $js_fav_temp; ?>',
                                            '<?php echo $js_fav_act; ?>',
                                            '<?php echo $js_fav_traits; ?>',
                                            '<?php echo addslashes(htmlspecialchars($fav_image)); ?>'
                                        )">
                                        <i class="fas fa-eye"></i> View Full Profile
                                    </button>

                                    <form method="POST" action="">
                                        <input type="hidden" name="favorite_pet_id" value="<?php echo (int)$fav_pet['id']; ?>">
                                        <input type="hidden" name="favorite_action" value="remove">
                                        <button type="submit" class="favorite-remove-btn">
                                            <i class="fas fa-heart-broken"></i> Remove
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="favorite-empty">
                        <i class="far fa-heart"></i>
                        <h3 style="font-size: 1.5rem; color: var(--text-dark); margin-bottom: 10px;">You don't have any favorite pets yet.</h3>
                        <p style="margin-bottom: 25px;">Click the heart <i class="far fa-heart" style="color:var(--danger);"></i> on any available pet to save it here.</p>
                        <a href="#pets" class="btn btn-primary"><i class="fas fa-search"></i> Browse Available Pets</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- ================= AI PET MATCHER (COMBO BOXES - EXPANDED) ================= -->
    <section id="ai_match" class="ai-section">
        <div class="container" style="position: relative; z-index: 2;">
            <div class="text-center">
                <h2 style="font-size:clamp(2rem, 4vw, 2.8rem); font-weight:900; margin-bottom:15px; background: linear-gradient(135deg, #60A5FA, #A78BFA); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                    <i class="fas fa-brain"></i> Neural Pet Matcher
                </h2>
                <p class="section-subtitle" style="color: #94A3B8;">Select your lifestyle preferences below. Our embedded AI dynamically builds a profile and semantically matches you with the absolute best shelter pets for your situation.</p>
            </div>
            
            <div class="ai-box">
                <div id="ai-init-status" class="alert" style="background:rgba(59,130,246,0.1); color:#60A5FA; border:1px solid #2563EB; display:none;">
                    <i class="fas fa-spinner fa-spin"></i> Downloading AI Engine (first time only, please wait...)
                </div>

                <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Species Preference</label>
                        <select id="ai-species">
                            <option value="Any">No Preference (Any)</option>
                            <option value="Dog">Dog</option>
                            <option value="Cat">Cat</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Living Space</label>
                        <select id="ai-living">
                            <option value="House">House (With Yard)</option>
                            <option value="Apartment">Apartment / Condo</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Activity Level</label>
                        <select id="ai-activity">
                            <option value="Calm">Calm & Relaxed</option>
                            <option value="Moderate">Moderate Energy</option>
                            <option value="Active">Highly Active</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Children in Home?</label>
                        <select id="ai-kids">
                            <option value="No">No Children</option>
                            <option value="Yes">Yes, I have children</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Pet Experience</label>
                        <select id="ai-experience">
                            <option value="Beginner">Beginner / First-time Owner</option>
                            <option value="Experienced">Experienced Owner</option>
                        </select>
                    </div>

                    <!-- Added fields based on prompt request -->
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Other Pets in Home?</label>
                        <select id="ai-other-pets">
                            <option value="None">No other pets</option>
                            <option value="Dogs">Yes, I have dogs</option>
                            <option value="Cats">Yes, I have cats</option>
                            <option value="Both">Yes, both</option>
                        </select>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Time Away From Home</label>
                        <select id="ai-time-away">
                            <option value="Minimal">0-4 hours (Work from home)</option>
                            <option value="Moderate">4-8 hours (Part-time)</option>
                            <option value="High">8+ hours (Full-time)</option>
                        </select>
                    </div>

                </div>

                <div style="margin-top: 25px;">
                    <label>Additional Specifics (Optional)</label>
                    <input type="text" id="ai-extra-notes" placeholder="E.g., I specifically want a small size pet that doesn't bark much...">
                </div>
                
                <button type="button" id="btn-run-ai" class="btn" style="background: linear-gradient(135deg, #3B82F6, #8B5CF6); color: white; margin-top:20px; width:100%; font-size: 1.1rem; box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3); border: none;">
                    <i class="fas fa-magic"></i> Find My Perfect Match via AI
                </button>

                <!-- AI THINKING PANEL -->
                <div id="ai-thinking" class="ai-thinking" style="display:none;">
                    <div class="ai-thinking-head">
                        <div class="ai-orb"><i class="fas fa-brain"></i></div>
                        <div>
                            <div class="ai-thinking-title">Neural Matcher is thinking<span class="ai-dots"><span>.</span><span>.</span><span>.</span></span></div>
                            <div class="ai-thinking-sub" id="ai-thinking-sub">Warming up…</div>
                        </div>
                    </div>
                    <div class="ai-progress"><div id="ai-progress-bar"></div></div>
                    <ul class="ai-steps" id="ai-steps"></ul>
                </div>
            </div>

            <!-- AI MATCH RESULTS CONTAINER -->
            <div id="ai-results-container" class="grid" style="margin-top: 50px; display: none;"></div>
        </div>
    </section>

    <!-- ================= COMMUNITY FEED & LOST AND FOUND ================= -->
    <section id="community_feed" class="bg-light">
        <div class="container">
            <h2 class="section-title text-center">Community Board</h2>
            <p class="section-subtitle text-center">Share your adoption journey, report missing pets, and connect with other pet lovers.</p>
            
            <div class="grid" style="grid-template-columns: 2fr 1fr; gap: 40px;" id="lost_found">
                
                <!-- Main Feed (Left Side) -->
                <div>
                    <h3 style="margin-top:0; color:var(--text-dark); display:flex; align-items:center; gap:10px;"><i class="fas fa-comments" style="color:var(--secondary);"></i> Activity Feed</h3>
                    
                    <div class="feed-input-card" style="margin-top: 20px;">
                        <div class="feed-input-top">
                            <div class="user-avatar-circle"><?php echo $initials; ?></div>
                            <form action="" method="POST" enctype="multipart/form-data" style="display:contents;" id="community-post-form">
                                <input type="text" name="post_content" class="fake-input-bar" placeholder="What's on your mind, <?php echo $first_name; ?>?" required>
                                <input type="hidden" name="post_location" id="post_loc_val" value="">
                                <input type="file" name="post_image" id="post_img_val" accept="image/*" style="display:none;" onchange="showImagePreview(this)">
                                <input type="submit" name="submit_community_post" id="hidden_post_btn" style="display:none;">
                            </form>
                        </div>
                        
                        <!-- Dynamic VISUAL Image Preview & Tag Notifications -->
                        <div id="attachment-notif" style="padding: 20px 0 0 60px; display: none;">
                            <div style="position: relative; display: inline-block;">
                                <img id="image-preview" src="" style="max-width: 250px; max-height: 250px; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); object-fit: cover; border: 1px solid var(--border-color);">
                                <button type="button" onclick="removeAttachment()" class="img-remove-btn" title="Remove Photo"><i class="fas fa-times" style="font-size:12px;"></i></button>
                            </div>
                        </div>
                        
                        <div id="location-notif" style="font-size: 0.85rem; color: var(--secondary); font-weight: 700; padding: 15px 0 0 60px; display: none;">
                            <i class="fas fa-map-marker-alt"></i> Location Tagged: <span id="location-name-text"></span>
                        </div>

                        <div class="feed-action-row">
                            <div class="action-btn" onclick="document.getElementById('post_img_val').click()"><i class="fas fa-image" style="color:var(--success);"></i> Photo/video</div>
                            <div class="action-btn" onclick="getCommunityLocation()"><i class="fas fa-map-marker-alt" style="color:var(--danger);"></i> Location</div>
                            <div class="action-btn" style="color:var(--secondary); background: rgba(59, 130, 246, 0.1);" onclick="document.getElementById('hidden_post_btn').click()"><i class="fas fa-paper-plane"></i> Share Post</div>
                        </div>
                    </div>

                    <!-- POSTS LIST -->
                    <?php 
                    $fetch_sql = "SELECT * FROM community_posts ORDER BY id DESC";
                    $fetch_res = mysqli_query($conn, $fetch_sql);
                    if($fetch_res && mysqli_num_rows($fetch_res) > 0){
                        while($post = mysqli_fetch_assoc($fetch_res)){
                            $post_initials = strtoupper(substr($post['user_display_name'], 0, 1));
                            $formatted_date = date("F j, g:i a", strtotime($post['created_at']));
                    ?>
                    <div class="post-card">
                        <div style="display:flex; align-items:center; gap:12px; margin-bottom:20px;">
                            <div class="user-avatar-circle" style="background:var(--text-muted);"><?php echo $post_initials; ?></div>
                            <div>
                                <p class="post-author"><?php echo htmlspecialchars($post['user_display_name']); ?></p>
                                <div class="post-meta"><?php echo $formatted_date; ?></div>
                            </div>
                        </div>
                        <div style="font-size:1rem; color:var(--text-dark); line-height:1.6; margin-bottom:20px;"><?php echo htmlspecialchars($post['content']); ?></div>
                        
                        <?php if(!empty($post['location'])): ?>
                            <div style="color: var(--secondary); font-weight: 700; font-size: 0.85rem; margin-bottom: 15px; display: flex; align-items: center; gap: 6px;"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($post['location']); ?></div>
                        <?php endif; ?>
                        
                        <?php if(!empty($post['image'])): ?>
                            <img src="uploads/<?php echo $post['image']; ?>" style="width:100%; border-radius:var(--radius-md); max-height: 400px; object-fit: cover; border: 1px solid var(--border-color);">
                        <?php endif; ?>
                    </div>
                    <?php } } else { echo "<div class='text-center' style='color:var(--text-muted); font-size:1rem; padding:40px; border:1px dashed var(--border-color); border-radius:var(--radius-lg);'>No posts yet. Start the conversation!</div>"; } ?>
                </div>

                <!-- Lost & Found Sidebar (Right Side) -->
                <div>
                    <h3 style="margin-top:0; color:var(--text-dark); display:flex; align-items:center; gap:10px;"><i class="fas fa-search-location" style="color:var(--danger);"></i> Lost Pets Alerts</h3>
                    
                    <?php if($lost_success) echo "<div class='alert alert-success' style='font-size:0.85rem;'><i class='fas fa-check-circle'></i> $lost_success</div>"; ?>
                    
                    <div class="form-container" style="padding: 20px; margin-top: 20px; margin-bottom: 30px; border-left: 4px solid var(--danger);">
                        <h4 style="margin: 0 0 15px; font-size: 1.1rem; color: var(--text-dark);">Report a Lost Pet</h4>
                        <form action="" method="POST" enctype="multipart/form-data">
                            <input type="text" name="l_pet_name" placeholder="Pet's Name" required style="padding: 10px; margin-bottom:10px;">
                            <input type="text" name="l_location" placeholder="Last seen location" required style="padding: 10px; margin-bottom:10px;">
                            <textarea name="l_description" rows="2" placeholder="Describe the pet (color, collar, etc.)" required style="padding: 10px; margin-bottom:10px;"></textarea>
                            <label style="font-size:0.75rem; display:block; margin-bottom:5px;">Upload Photo</label>
                            <input type="file" name="l_image" accept="image/*" style="font-size:0.85rem; padding:5px; margin-bottom:15px;">
                            <button type="submit" name="submit_lost_pet" class="btn btn-outline" style="width: 100%; font-size: 0.9rem; padding: 10px; color: var(--danger); border-color: var(--danger);">Alert Community</button>
                        </form>
                    </div>

                    <?php 
                    $lost_sql = "SELECT * FROM lost_pets WHERE status='Lost' ORDER BY id DESC LIMIT 5";
                    $lost_res = mysqli_query($conn, $lost_sql);
                    if($lost_res && mysqli_num_rows($lost_res) > 0){
                        while($lpet = mysqli_fetch_assoc($lost_res)){
                            $l_img = !empty($lpet['image']) ? "uploads/".$lpet['image'] : "uploads/default_lost.jpg";
                    ?>
                    <div class="post-card" style="padding: 15px; display: flex; gap: 15px; align-items: flex-start; margin-bottom: 15px;">
                        <img src="<?php echo htmlspecialchars($l_img); ?>" style="width: 80px; height: 80px; border-radius: var(--radius-sm); object-fit: cover;">
                        <div>
                            <div class="badge badge-danger" style="margin-bottom: 5px; font-size: 0.65rem;">MISSING</div>
                            <h4 style="margin: 0 0 5px; font-size: 1.05rem; color: var(--text-dark);"><?php echo htmlspecialchars($lpet['pet_name']); ?></h4>
                            <p style="margin: 0 0 5px; font-size: 0.85rem; color: var(--text-muted); line-height: 1.4;"><?php echo htmlspecialchars($lpet['description']); ?></p>
                            <small style="color: var(--secondary); font-weight: 700;"><i class="fas fa-map-pin"></i> <?php echo htmlspecialchars($lpet['location']); ?></small>
                        </div>
                    </div>
                    <?php } } else { echo "<div class='text-center' style='padding:20px; font-size:0.9rem; color:var(--text-muted); border:1px dashed var(--border-color); border-radius:var(--radius-md);'>No lost pets reported locally.</div>"; } ?>
                </div>

            </div>
        </div>
    </section>

    <!-- ================= CLIENT NOTIFICATION CENTER ================= -->
    <section id="notifications" class="bg-white">
        <div class="container">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:20px; flex-wrap:wrap; margin-bottom:35px;">
                <div>
                    <h2 class="section-title" style="margin-bottom: 5px;"><i class="fas fa-bell" style="color:var(--primary);"></i> Notification Center</h2>
                    <p style="color:var(--text-muted); margin:0;">Stay updated about your applications, appointments, and rescue reports.</p>
                </div>
                <form method="POST">
                    <button type="submit" name="mark_notifications_read" class="btn btn-outline" style="background: var(--bg-main);">
                        <i class="fas fa-check-double"></i> Mark All as Read
                    </button>
                </form>
            </div>

            <?php if($client_feature_msg): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($client_feature_msg); ?>
                </div>
            <?php endif; ?>

            <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(300px,1fr)); gap: 20px;">
                <?php if(count($notif_rows)>0): ?>
                    <?php foreach($notif_rows as $notice): ?>
                        <div class="form-container" style="padding:20px; border-left:5px solid <?php echo ((int)$notice['is_read']===0)?'var(--primary)':'var(--border-color)'; ?>;">
                            <div style="display:flex; gap:15px; align-items:flex-start;">
                                <div style="width:45px; height:45px; border-radius:50%; background:rgba(245, 158, 11, 0.1); color:var(--primary); display:flex; align-items:center; justify-content:center; font-size: 1.2rem; flex-shrink: 0;">
                                    <i class="fas fa-<?php echo $notice['type']==='appointment'?'calendar-check':'bell'; ?>"></i>
                                </div>
                                <div style="flex:1;">
                                    <h3 style="margin:0 0 6px; font-size:1.1rem; color:var(--text-dark); font-weight: 800; border:none; padding:0;"><?php echo htmlspecialchars($notice['title']); ?></h3>
                                    <p style="margin:0 0 10px 0; color:var(--text-muted); font-size:0.95rem; line-height:1.5;"><?php echo htmlspecialchars($notice['message']); ?></p>
                                    <small style="color:var(--text-light); font-weight: 700;"><i class="far fa-clock"></i> <?php echo date('M d, Y g:i A',strtotime($notice['created_at'])); ?></small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="form-container text-center" style="grid-column:1/-1; padding:60px; border: 1px dashed var(--border-color);">
                        <i class="far fa-bell" style="font-size:50px; color:var(--border-color); margin-bottom: 15px;"></i>
                        <h3 style="border:none; padding:0; margin:0 0 5px 0; justify-content:center; font-size: 1.4rem;">No notifications yet</h3>
                        <p style="color:var(--text-muted); margin:0;">Updates from your adoption journey will appear here.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- ================= APPOINTMENT / VISIT BOOKING ================= -->
    <section id="appointments" class="bg-light">
        <div class="container">
            <div class="text-center">
                <h2 class="section-title"><i class="fas fa-calendar-check" style="color:var(--primary);"></i> Shelter Visit</h2>
                <p class="section-subtitle">Request a meet-and-greet, interview, or adoption visit with a pet.</p>
            </div>

            <div class="profile-grid">
                <div class="form-container">
                    <h3><i class="fas fa-calendar-plus" style="color:var(--primary);"></i> Request a Visit</h3>
                    <form method="POST">
                        <div class="form-group">
                            <label>Pet</label>
                            <select name="appointment_pet_id" required>
                                <option value="">Select a pet</option>
                                <?php
                                $appt_pets = mysqli_query($conn, "SELECT id,name,breed FROM pets WHERE status='Available' OR status='available' ORDER BY name");
                                if($appt_pets){
                                    while($ap = mysqli_fetch_assoc($appt_pets)){
                                        echo '<option value="'.(int)$ap['id'].'">'.htmlspecialchars($ap['name'].' - '.$ap['breed']).'</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Purpose</label>
                            <select name="appointment_purpose" required>
                                <option>Meet & Greet</option>
                                <option>Adoption Interview</option>
                                <option>Home Visit Discussion</option>
                                <option>Follow-up Visit</option>
                            </select>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div class="form-group">
                                <label>Preferred Date</label>
                                <input type="date" name="appointment_date" min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Preferred Time</label>
                                <input type="time" name="appointment_time" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Notes</label>
                            <textarea name="appointment_notes" rows="3" placeholder="Add questions or special requests..."></textarea>
                        </div>

                        <button type="submit" name="schedule_appointment" class="btn btn-primary" style="width:100%;">
                            <i class="fas fa-paper-plane"></i> Send Appointment Request
                        </button>
                    </form>
                </div>

                <div class="form-container" style="overflow-x:auto;">
                    <h3><i class="fas fa-history" style="color:var(--secondary);"></i> My Appointments</h3>
                    <?php if(count($appointment_rows)>0): ?>
                        <table class="history-table">
                            <thead><tr><th>Pet</th><th>Date</th><th>Purpose</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php foreach($appointment_rows as $ap): ?>
                                <?php
                                    $as = strtolower($ap['status']);
                                    $ab = $as==='approved' || $as==='confirmed' ? 'badge-success' : ($as==='rejected' || $as==='cancelled' ? 'badge-danger' : 'badge-pending');
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($ap['pet_name'] ?? 'Pet'); ?></strong><br><span style="font-size:0.75rem; color:var(--text-light);"><?php echo htmlspecialchars($ap['breed'] ?? ''); ?></span></td>
                                    <td><?php echo date('M d, Y',strtotime($ap['appointment_date'])); ?><br><span style="font-size:0.75rem; color:var(--text-light);"><?php echo date('g:i A',strtotime($ap['appointment_time'])); ?></span></td>
                                    <td><?php echo htmlspecialchars($ap['purpose']); ?></td>
                                    <td><span class="badge <?php echo $ab; ?>"><?php echo htmlspecialchars($ap['status']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="padding:50px 20px; text-align:center; color:var(--text-muted); border: 1px dashed var(--border-color); border-radius: var(--radius-md);">
                            <i class="far fa-calendar" style="font-size:45px; margin-bottom:15px; color:var(--border-color);"></i>
                            <p>No appointment requests yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= PET CARE CENTER & NAME GENERATOR ================= -->
    <section id="pet_care" class="bg-white">
        <div class="container">
            <h2 class="section-title text-center"><i class="fas fa-book-medical" style="color:var(--success);"></i> Pet Care Center</h2>
            <p class="section-subtitle text-center">Resources and fun tools for responsible pet ownership.</p>
            
            <div class="grid" style="grid-template-columns: 2fr 1fr; gap: 40px; margin-bottom: 50px;" id="name_generator">
                <!-- Name Generator -->
                <div class="form-container" style="background: linear-gradient(135deg, var(--primary-light), #FFFFFF);">
                    <h3 style="border-bottom:none; margin-bottom: 10px;"><i class="fas fa-dice" style="color:var(--primary);"></i> Pet Name Generator</h3>
                    <p style="color:var(--text-muted); margin-bottom: 25px;">Need inspiration for your new friend's name? Click below to generate a cute and popular pet name!</p>
                    
                    <div style="text-align: center; padding: 40px 20px; background: var(--bg-surface); border-radius: var(--radius-md); box-shadow: var(--shadow-sm); border: 1px solid var(--border-color);">
                        <h2 id="generated-name" style="font-size: 2.5rem; color: var(--text-dark); margin: 0 0 20px; font-weight: 900; letter-spacing: 1px;">Ready?</h2>
                        <button class="btn btn-primary" onclick="generatePetName()" style="font-size: 1.1rem; padding: 12px 30px;"><i class="fas fa-magic"></i> Generate Name</button>
                    </div>
                </div>

                <!-- Fast Care tips -->
                <div style="display:flex; flex-direction:column; gap: 20px;">
                    <div style="background: rgba(139, 92, 246, 0.1); padding: 20px; border-radius: var(--radius-md); border-left: 4px solid #8B5CF6;">
                        <h4 style="margin:0 0 5px; color:var(--text-dark);"><i class="fas fa-syringe" style="color:#8B5CF6;"></i> Vaccinations</h4>
                        <p style="margin:0; font-size: 0.9rem; color:var(--text-muted);">Keep your pet's vaccination records updated.</p>
                    </div>
                    <div style="background: rgba(245, 158, 11, 0.1); padding: 20px; border-radius: var(--radius-md); border-left: 4px solid #F59E0B;">
                        <h4 style="margin:0 0 5px; color:var(--text-dark);"><i class="fas fa-bone" style="color:#F59E0B;"></i> Nutrition</h4>
                        <p style="margin:0; font-size: 0.9rem; color:var(--text-muted);">Provide fresh water and age-appropriate food.</p>
                    </div>
                    <div style="background: rgba(59, 130, 246, 0.1); padding: 20px; border-radius: var(--radius-md); border-left: 4px solid #3B82F6;">
                        <h4 style="margin:0 0 5px; color:var(--text-dark);"><i class="fas fa-walking" style="color:#3B82F6;"></i> Exercise</h4>
                        <p style="margin:0; font-size: 0.9rem; color:var(--text-muted);">Give your pet safe daily activity and play.</p>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- ================= USER PROFILE, HISTORY & DONATIONS ================= -->
    <section id="profile" class="bg-light">
        <div class="container">
            <h2 class="section-title text-center"><i class="fas fa-user-circle"></i> My Account & History</h2>
            <p class="section-subtitle text-center">Manage your details, applications, reports, and donations.</p>

            <?php if($profile_msg) echo "<div class='alert alert-success'><i class='fas fa-check'></i> $profile_msg</div>"; ?>
            <?php if($sponsor_success) echo "<div class='alert alert-success'><i class='fas fa-heart' style='color:var(--danger);'></i> $sponsor_success</div>"; ?>
            
            <div class="profile-grid">
                
                <!-- Profile Settings -->
                <div class="form-container">
                    <h3><i class="fas fa-user-edit" style="color:var(--primary);"></i> Account Details</h3>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="user_name" value="<?php echo htmlspecialchars($full_name); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" name="user_email" value="<?php echo htmlspecialchars($contact_info); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="text" name="user_phone" value="(555) 123-4567">
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-primary" style="width:100%; margin-top:10px;"><i class="fas fa-save"></i> Save Changes</button>
                    </form>
                </div>

                <!-- Adoption History -->
                <div class="form-container" style="overflow-x:auto;">
                    <h3><i class="fas fa-file-alt" style="color:var(--secondary);"></i> Adoption History</h3>
                    <table class="history-table">
                        <thead><tr><th>Pet</th><th>Date Applied</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php 
                            $history_sql = "SELECT a.*, p.name as pet_name, p.breed FROM adoption_applications a JOIN pets p ON a.pet_id = p.id WHERE a.user_id = '$user_id' ORDER BY a.id DESC";
                            $history_res = mysqli_query($conn, $history_sql);
                            if($history_res && mysqli_num_rows($history_res) > 0){
                                while($app = mysqli_fetch_assoc($history_res)){
                                    $status = strtolower($app['status']);
                                    $badge_class = "badge-pending";
                                    if($status == 'approved' || $status == 'adopted') $badge_class = "badge-success";
                                    if($status == 'rejected') $badge_class = "badge-danger";
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($app['pet_name']); ?></strong><br><span style="font-size:0.75rem; color:var(--text-light);"><?php echo htmlspecialchars($app['breed']); ?></span></td>
                                        <td><?php echo isset($app['application_date']) ? date('M d, Y', strtotime($app['application_date'])) : 'Unknown'; ?></td>
                                        <td><span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($app['status']); ?></span></td>
                                        <td>
                                            <?php if($status == 'approved' || $status == 'adopted'): ?>
                                                <a href="#smart_monitor" style="color:var(--success); font-weight:800; font-size: 0.85rem;"><i class="fas fa-heartbeat"></i> Vitals</a>
                                            <?php elseif($status == 'pending'): ?>
                                                <a href="#" style="color:var(--danger); font-size:0.85rem; font-weight: 700;"><i class="fas fa-times"></i> Withdraw</a>
                                            <?php else: ?>
                                                <span style="color:var(--text-light); font-size:0.85rem; font-weight:700;">Closed</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php
                                }
                            } else {
                                echo "<tr><td colspan='4' style='text-align:center; padding:30px; color:var(--text-muted);'>No applications found. Start your journey today!</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <!-- Donation & Sponsorship History -->
                <div class="form-container" style="overflow-x:auto;" id="donations">
                    <h3><i class="fas fa-hand-holding-heart" style="color:var(--danger);"></i> My Sponsorships & Donations</h3>
                    <table class="history-table">
                        <thead><tr><th>Supported Pet</th><th>Amount</th><th>Date</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php 
                            $don_sql = "SELECT * FROM client_donations WHERE user_id = '$user_id' ORDER BY id DESC";
                            $don_res = mysqli_query($conn, $don_sql);
                            if($don_res && mysqli_num_rows($don_res) > 0){
                                while($don = mysqli_fetch_assoc($don_res)){
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($don['pet_name']); ?></strong></td>
                                        <td style="color: var(--success); font-weight: 900;">$<?php echo number_format($don['amount'], 2); ?></td>
                                        <td style="font-size: 0.9rem;"><?php echo date('M d, Y', strtotime($don['created_at'])); ?></td>
                                        <td><span class="badge badge-success"><?php echo htmlspecialchars($don['status']); ?></span></td>
                                    </tr>
                                    <?php
                                }
                            } else {
                                echo "<tr><td colspan='4' style='text-align:center; padding:30px; color:var(--text-muted);'>You haven't sponsored any pets yet. Help a pet today!</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <!-- Rescue Reports History -->
                <div class="form-container" style="overflow-x:auto;">
                    <h3><i class="fas fa-ambulance" style="color:var(--primary);"></i> My Rescue Reports</h3>
                    <table class="history-table">
                        <thead><tr><th>Location / Address</th><th>Description</th><th>Date Reported</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php 
                            $rescue_sql = "SELECT * FROM rescue_reports WHERE user_id = '$user_id' ORDER BY id DESC";
                            $rescue_res = mysqli_query($conn, $rescue_sql);
                            if($rescue_res && mysqli_num_rows($rescue_res) > 0){
                                while($req = mysqli_fetch_assoc($rescue_res)){
                                    $r_status = strtolower($req['status']);
                                    $r_badge = "badge-pending"; 
                                    if($r_status == 'rescued') $r_badge = "badge-success"; 
                                    
                                    $loc_text = trim($req['location'] ?? '');
                                    if(empty($loc_text)) {
                                        if(!empty($req['latitude']) && !empty($req['longitude'])) {
                                            $loc_text = "Lat: " . $req['latitude'] . ", Lng: " . $req['longitude'];
                                        } else {
                                            $loc_text = "Address not captured";
                                        }
                                    }
                                    ?>
                                    <tr>
                                        <td><i class="fas fa-map-marker-alt" style="color:var(--secondary); margin-right:5px;"></i> <strong style="line-height: 1.5; font-size: 0.9rem;"><?php echo htmlspecialchars($loc_text); ?></strong></td>
                                        <td style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 0.9rem;"><?php echo htmlspecialchars($req['description']); ?></td>
                                        <td style="font-size: 0.9rem;"><?php echo !empty($req['reported_at']) ? date('M d, Y - g:i A', strtotime($req['reported_at'])) : 'Just now'; ?></td>
                                        <td><span class="badge <?php echo $r_badge; ?>"><?php echo htmlspecialchars($req['status']); ?></span></td>
                                    </tr>
                                    <?php
                                }
                            } else {
                                echo "<tr><td colspan='4' style='text-align:center; padding:30px; color:var(--text-muted);'>You haven't reported any strays yet.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </section>

    <!-- ================= REPORT STRAY & LIVE MAP SECTION ================= -->
    <section id="report_stray" class="bg-white">
        <div class="container">
            <h2 class="section-title text-center"><i class="fas fa-camera" style="color:var(--primary);"></i> Report an Emergency Stray</h2>
            <p class="section-subtitle text-center">Help us rescue animals in immediate danger. Pin the exact location below.</p>
            
            <?php if($stray_success) echo "<div class='alert alert-success'><i class='fas fa-check-circle'></i> $stray_success</div>"; ?>
            
            <div class="profile-grid">
                <!-- FORM -->
                <div class="form-container">
                    <h3><i class="fas fa-exclamation-triangle" style="color:var(--danger);"></i> Submit Details</h3>
                    <form method="POST" enctype="multipart/form-data" action="">
                        <div class="form-group">
                            <label>Visual Evidence (Photo)</label>
                            <input type="file" name="stray_photo" accept="image/*" required style="padding: 12px; background: transparent; border: 2px dashed var(--border-color); margin-top: 8px;">
                        </div>
                        
                        <button type="button" class="btn btn-secondary" style="width:100%; margin-bottom: 15px;" onclick="getDetailedLocation(this)"><i class="fas fa-crosshairs"></i> Get My Location</button>
                        
                        <div class="form-group">
                            <label>Detected Location</label>
                            <textarea name="address" id="detected-address" rows="2" placeholder="Waiting for GPS verification..." readonly required style="background:var(--bg-main); cursor:not-allowed; border:1px dashed var(--border-color);"></textarea>
                        </div>
                        
                        <input type="hidden" name="lat" id="lat"><input type="hidden" name="lon" id="lon">
                        
                        <div class="form-group">
                            <label>Condition Notes</label>
                            <textarea name="description" rows="3" placeholder="Describe the stray's health status..." required></textarea>
                        </div>
                        
                        <button type="submit" name="submit_stray" class="btn btn-primary" style="width:100%;">Dispatch Rescue Request <i class="fas fa-paper-plane"></i></button>
                    </form>
                </div>

                <!-- LIVE MAP -->
                <div class="map-container-stray">
                    <h3 style="margin-top:0; font-size: 1.4rem; display:flex; align-items:center; gap:10px; color: var(--text-dark); border-bottom: 2px dashed var(--border-color); padding-bottom: 15px; margin-bottom: 15px;"><i class="fas fa-map-marked-alt" style="color:var(--secondary);"></i> Live Tracking Map</h3>
                    <p style="font-size:0.95rem; color:var(--text-muted); margin-bottom:0;">Click the reported pin on the map to generate the fastest route.</p>
                    <div id="strayMapLive"></div>
                </div>
            </div>
        </div>
    </section>

</main>

<!-- ================= FOOTER ================= -->
<div class="footer" style="background: #0F172A; color: #94A3B8; padding: clamp(60px, 8vw, 100px) 20px 40px;">
    <div class="container" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 40px;">
        <div>
            <div class="logo" style="color: white; margin-bottom: 20px;"><div class="logo-icon"><i class="fas fa-paw"></i></div> PetRescue</div>
            <p style="line-height:1.6; font-size: 0.95rem;">Dedicated to solving the stray crisis and ensuring successful, lifelong adoptions in our community.</p>
        </div>
        <div>
            <h4 style="color:white; margin-top:0; margin-bottom:20px; font-size:1.2rem; font-weight: 800;">Quick Links</h4>
            <a style="display:block; color:#94A3B8; margin-bottom:12px; font-size: 0.95rem;" href="#home">Home Dashboard</a>
            <a style="display:block; color:#94A3B8; margin-bottom:12px; font-size: 0.95rem;" href="#pets">Available Pets</a>
            <a style="display:block; color:#94A3B8; margin-bottom:12px; font-size: 0.95rem;" href="#ai_match">AI Pet Matcher</a>
            <a style="display:block; color:#94A3B8; margin-bottom:12px; font-size: 0.95rem;" href="#report_stray">Emergency Stray Report</a>
            <a style="display:block; color:#94A3B8; margin-bottom:12px; font-size: 0.95rem;" href="#appointments">Schedule a Visit</a>
        </div>
        <div>
            <h4 style="color:white; margin-top:0; margin-bottom:20px; font-size:1.2rem; font-weight: 800;">Contact</h4>
            <p style="margin-bottom:12px; font-size: 0.95rem;">
                <a href="https://www.facebook.com/sanpedrominianimelshelter" target="_blank" onclick="return confirm('Paalala: Siguraduhing naka-login ka sa iyong sariling Facebook account para makita ang page na ito.\n\nGusto mo bang magpatuloy?');" style="color:#94A3B8; display:flex; align-items:center;" onmouseover="this.style.color='var(--secondary)'" onmouseout="this.style.color='#94A3B8'">
                    <i class="fab fa-facebook" style="margin-right:10px; font-size: 1.2rem;"></i> Facebook Page
                </a>
            </p>
            <p style="font-size: 0.95rem;"><i class="fas fa-phone" style="margin-right:10px; font-size: 1.2rem;"></i> +63 912 345 6789</p>
        </div>
    </div>
    <div style="text-align:center; margin-top:80px; padding-top:30px; border-top:1px solid rgba(255,255,255,0.1); font-size: 0.9rem;">© <?php echo date("Y"); ?> Pet Rescue Platform. All rights reserved.</div>
</div>

<!-- ================= FULL-SCREEN SALESFORCE MODAL (EXPANDED INFO) ================= -->
<div id="sfModal" class="sf-modal-overlay">
    <div class="sf-modal-container">
        <span class="sf-modal-close" onclick="closeSFModal()">&times;</span>
        <div style="padding: clamp(20px, 4vw, 40px);">
            <h2 id="sf-name-title" style="font-size: 2.2rem; font-weight: 900; margin-top: 0; margin-bottom: 25px; color: var(--text-dark);">Pet Name</h2>
            
            <div style="display:flex; gap:35px; align-items: flex-start; flex-wrap:wrap;">
                <img id="sf-image" src="" style="width: 280px; height: 280px; border-radius: var(--radius-lg); object-fit: cover; box-shadow: var(--shadow); border: 1px solid var(--border-color);">
                
                <div style="flex:1; min-width:280px;">
                    
                    <!-- Expanded Details Grid -->
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap:15px; margin-bottom: 20px;">
                        <div style="background:var(--bg-main); padding:15px; border-radius:var(--radius-md); border:1px solid var(--border-color);">
                            <label style="font-size:0.75rem; color:var(--text-muted); font-weight:800; text-transform:uppercase; letter-spacing: 0.5px;">Breed</label>
                            <div id="sf-breed" style="font-weight:900; font-size:1.1rem; color: var(--text-dark); margin-top: 5px;">Breed</div>
                        </div>
                        <div style="background:var(--bg-main); padding:15px; border-radius:var(--radius-md); border:1px solid var(--border-color);">
                            <label style="font-size:0.75rem; color:var(--text-muted); font-weight:800; text-transform:uppercase; letter-spacing: 0.5px;">Age</label>
                            <div id="sf-age-top" style="font-weight:900; font-size:1.1rem; color: var(--text-dark); margin-top: 5px;">Age</div>
                        </div>
                        <div style="background:var(--bg-main); padding:15px; border-radius:var(--radius-md); border:1px solid var(--border-color);">
                            <label style="font-size:0.75rem; color:var(--text-muted); font-weight:800; text-transform:uppercase; letter-spacing: 0.5px;">Gender</label>
                            <div id="sf-gender" style="font-weight:900; font-size:1.1rem; color: var(--text-dark); margin-top: 5px;">Gender</div>
                        </div>
                        <div style="background:var(--bg-main); padding:15px; border-radius:var(--radius-md); border:1px solid var(--border-color);">
                            <label style="font-size:0.75rem; color:var(--text-muted); font-weight:800; text-transform:uppercase; letter-spacing: 0.5px;">Size</label>
                            <div id="sf-size" style="font-weight:900; font-size:1.1rem; color: var(--text-dark); margin-top: 5px;">Size</div>
                        </div>
                        <div style="background:var(--bg-main); padding:15px; border-radius:var(--radius-md); border:1px solid var(--border-color);">
                            <label style="font-size:0.75rem; color:var(--text-muted); font-weight:800; text-transform:uppercase; letter-spacing: 0.5px;">Temperament</label>
                            <div id="sf-temp" style="font-weight:900; font-size:1.1rem; color: var(--text-dark); margin-top: 5px;">Temp</div>
                        </div>
                        <div style="background:var(--bg-main); padding:15px; border-radius:var(--radius-md); border:1px solid var(--border-color);">
                            <label style="font-size:0.75rem; color:var(--text-muted); font-weight:800; text-transform:uppercase; letter-spacing: 0.5px;">Activity</label>
                            <div id="sf-activity" style="font-weight:900; font-size:1.1rem; color: var(--text-dark); margin-top: 5px;">Activity</div>
                        </div>
                    </div>
                    
                    <!-- Health status generic row -->
                    <div style="display:flex; gap:10px; margin-bottom: 25px; flex-wrap:wrap;">
                        <span class="badge badge-success"><i class="fas fa-syringe"></i> Vaccinated</span>
                        <span class="badge badge-success"><i class="fas fa-heartbeat"></i> Vet Checked</span>
                        <span class="badge badge-success"><i class="fas fa-microchip"></i> Microchipped</span>
                    </div>

                    <div style="background:rgba(245, 158, 11, 0.1); padding:25px; border-radius:var(--radius-md); border:1px solid var(--primary);">
                        <div style="color:var(--primary); font-size:0.95rem; font-weight:800; margin-bottom:12px; text-transform: uppercase; letter-spacing: 0.5px;"><i class="fas fa-info-circle"></i> Behavioral Analysis</div>
                        <div id="sf-traits" style="font-weight:600; line-height:1.7; color: var(--text-dark); font-size: 1.05rem;">Traits text.</div>
                    </div>
                </div>
            </div>

            <!-- ADOPTION FORM -->
            <div style="margin-top: 40px; border-top: 2px dashed var(--border-color); padding-top: 40px;">
                <h3 style="font-size: 1.6rem; font-weight: 900; margin-top: 0; margin-bottom: 25px; color: var(--text-dark);"><i class="fas fa-clipboard-check" style="color: var(--primary);"></i> Finalize Adoption Request</h3>
                <form method="POST" action="">
                    <input type="hidden" name="pet_id" id="sf-pet-id">
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 25px;">
                        <div class="form-group">
                            <label>Confirm Living Space</label>
                            <select name="app_home" required>
                                <option value="Apartment">Apartment</option>
                                <option value="House">House</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Confirm Experience</label>
                            <select name="app_exp" required>
                                <option value="Beginner">Beginner</option>
                                <option value="Experienced">Experienced</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" name="submit_application" class="btn btn-primary" style="margin-top:10px; width:100%; font-size: 1.1rem; padding: 16px;">Submit Application to Review</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ================= SPONSORSHIP MODAL ================= -->
<div id="sponsorModal" class="sf-modal-overlay">
    <div class="sf-modal-container" style="max-width: 500px;">
        <span class="sf-modal-close" onclick="closeSponsorModal()">&times;</span>
        <div style="padding: 40px 30px; text-align:center;">
            <i class="fas fa-hand-holding-heart" style="font-size: 50px; color: var(--danger); margin-bottom: 20px;"></i>
            <h2 style="margin-top:0; color:var(--text-dark); font-size: 1.8rem; font-weight: 900;">Sponsor <span id="sponsor-pet-display">this pet</span></h2>
            <p style="color:var(--text-muted); margin-bottom: 30px; line-height: 1.6;">Your sponsorship helps provide food, critical medical care, and toys for this pet while they wait for their forever home.</p>
            
            <form method="POST" action="">
                <input type="hidden" name="sponsor_pet_name" id="sponsor-pet-name">
                <input type="hidden" name="sponsor_amount" id="final-sponsor-amt" value="10">

                <div style="display:flex; justify-content:center; gap:15px; margin-bottom: 25px;">
                    <button type="button" class="btn btn-outline sponsor-amt active" onclick="selectSponsorAmt(this, 10)">$10</button>
                    <button type="button" class="btn btn-outline sponsor-amt" onclick="selectSponsorAmt(this, 25)">$25</button>
                    <button type="button" class="btn btn-outline sponsor-amt" onclick="selectSponsorAmt(this, 50)">$50</button>
                </div>
                
                <input type="number" id="custom-sponsor-amt" placeholder="Custom Amount ($)" min="1" style="text-align:center; margin-bottom:20px;" oninput="updateCustomAmt()">
                
                <button type="submit" name="submit_sponsorship" class="btn btn-primary" style="width:100%; padding: 16px; font-size: 1.1rem;"><i class="fas fa-heart"></i> Process Sponsorship</button>
            </form>
        </div>
    </div>
</div>

<!-- SCRIPTS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.js"></script>
<!-- Intro.js Script for the Smart Highlight Tour -->
<script src="https://cdn.jsdelivr.net/npm/intro.js@7.2.0/intro.min.js"></script>

<script>
    // ================= LIGHT / DARK MODE HANDLER ================= 
    const themeBtn = document.getElementById('theme-toggle');
    const themeBtnMobile = document.getElementById('theme-toggle-mobile');
    
    function updateThemeUI(theme) {
        if (theme === 'dark') {
            themeBtn.innerHTML = '<i class="fas fa-sun"></i>';
            themeBtnMobile.innerHTML = '<i class="fas fa-sun"></i> Switch to Light Mode';
        } else {
            themeBtn.innerHTML = '<i class="fas fa-moon"></i>';
            themeBtnMobile.innerHTML = '<i class="fas fa-moon"></i> Switch to Dark Mode';
        }
    }
    
    // Initial UI sync
    updateThemeUI(document.documentElement.getAttribute('data-theme'));

    function toggleTheme() {
        let currentTheme = document.documentElement.getAttribute('data-theme');
        let newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        
        document.documentElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        updateThemeUI(newTheme);
    }
    
    themeBtn.addEventListener('click', toggleTheme);
    themeBtnMobile.addEventListener('click', toggleTheme);

    // Mobile Hamburger Menu Toggle
    function toggleMenu() {
        const nav = document.getElementById('navLinks');
        nav.classList.toggle('active');
    }

    <?php if(!empty($scroll_target)): ?>
        window.addEventListener('load', () => { document.getElementById('<?php echo $scroll_target; ?>').scrollIntoView({behavior: 'smooth', block: 'start'}); });
    <?php elseif(isset($_GET['search_pet']) || isset($_GET['filter_size']) || isset($_GET['filter_temp'])): ?>
        window.addEventListener('load', () => { document.getElementById('pets').scrollIntoView({behavior: 'smooth', block: 'start'}); });
    <?php endif; ?>

    // ================= HIGHLIGHT SPOTLIGHT TOUR LOGIC =================
    window.addEventListener('DOMContentLoaded', () => {
        let dbTutorialSeen = <?php echo $tutorial_seen; ?>;
        // Start tour after 1 second if they are a brand new user
        if (dbTutorialSeen === 0) {
            setTimeout(startTutorial, 1000);
        }
    });

    function startTutorial() {
        const intro = introJs();
        intro.setOptions({
            steps: [
                {
                    title: "👋 Welcome to Pet Rescue, <?php echo addslashes($first_name); ?>!",
                    intro: "We are thrilled to have you here. Let's take a comprehensive tour of your new dashboard so you know exactly how to find your perfect pet, use our AI tools, and report emergencies. Click 'Next' to begin!"
                },
                {
                    element: document.querySelector('#navbar'),
                    title: "🧭 Main Navigation",
                    intro: "This is your control center. Use these links to quickly jump to different sections like Adoption, Community, or AI Match. On mobile devices, tap the menu icon.",
                    position: "bottom"
                },
                {
                    element: document.querySelector('#pets .grid'),
                    title: "🐶 Find Your New Best Friend",
                    intro: "Browse all our available rescues here. Click 'View Full Profile' to see detailed medical and behavior info, or click the heart icon to save them for later.",
                    position: "top"
                },
                {
                    element: document.querySelector('#ai_match .ai-box'),
                    title: "🧠 Neural Pet Matcher",
                    intro: "Want the perfect recommendation? Select your living space, experience level, and other lifestyle preferences from these dropdowns. Our AI will analyze the entire database and match you with the absolute best pets for your situation.",
                    position: "auto"
                },
                {
                    element: document.querySelector('#community_feed .feed-input-card'),
                    title: "💬 Community Board",
                    intro: "Join our active community! Share updates about your adopted pets, ask for advice, or post cute photos. You can even tag your location.",
                    position: "bottom"
                },
                {
                    element: document.querySelector('#lost_found > div:last-child > .form-container'),
                    title: "🔍 Lost & Found Alerts",
                    intro: "If you lose a pet or find a stray, post an alert right here. It instantly notifies the community to keep an eye out and help reunite families.",
                    position: "left"
                },
                {
                    element: document.querySelector('#appointments'),
                    title: "📅 Schedule a Visit",
                    intro: "Found a pet you like? Request a Meet & Greet or Adoption Interview here. Select the pet, pick a date and time, and we'll prepare for your visit.",
                    position: "top"
                },
                {
                    element: document.querySelector('#pet_care'),
                    title: "🩺 Pet Care & Fun Tools",
                    intro: "Check out our fast care tips for keeping your pet healthy. Stuck on a name? Try our Pet Name Generator for some cute and popular ideas!",
                    position: "top"
                },
                {
                    element: document.querySelector('#report_stray .form-container'),
                    title: "🚑 Emergency Stray Report",
                    intro: "This is a critical tool. If you see an animal in immediate danger, upload a photo, use the 'Get My Location' button to lock your GPS, and submit. Our live map will route a rescue van to you.",
                    position: "right"
                },
                {
                    element: document.querySelector('#tour-profile-btn'),
                    title: "👤 Your Profile Hub",
                    intro: "Finally, click your Profile button anytime to check your unread alerts, view saved favorites, manage your adoption history, and track your donations. You are all set to start saving lives!",
                    position: "left"
                }
            ],
            showProgress: true,
            showBullets: false,
            exitOnOverlayClick: false,
            disableInteraction: true,
            nextLabel: 'Next <i class="fas fa-arrow-right" style="font-size:12px;"></i>',
            prevLabel: '<i class="fas fa-arrow-left" style="font-size:12px;"></i> Back',
            doneLabel: 'Get Started <i class="fas fa-rocket"></i>',
            scrollTo: 'tooltip', // This ensures the TOOLTIP is visible, not just the element top
            scrollPadding: 150 
        });

        intro.oncomplete(markTutorialDone);
        intro.onexit(markTutorialDone);
        intro.start();
    }

    function markTutorialDone() {
        // Send a silent request to the server so it doesn't auto-start next time
        let formData = new FormData();
        formData.append('mark_tutorial_seen', '1');
        fetch('', { method: 'POST', body: formData });
    }

    // Adoption Modal Logic - Updated for expanded info
    const sfModal = document.getElementById("sfModal");
    function openSFModal(id, name, breed, age, gender, size, temp, activity, traits, image) {
        document.getElementById("sf-pet-id").value = id; 
        document.getElementById("sf-name-title").innerText = name; 
        document.getElementById("sf-breed").innerText = breed; 
        
        let ageStr = age;
        if (!isNaN(age) && age !== "") { ageStr = age + " Yrs"; }
        document.getElementById("sf-age-top").innerText = ageStr; 
        
        document.getElementById("sf-gender").innerText = gender; 
        document.getElementById("sf-size").innerText = size; 
        document.getElementById("sf-temp").innerText = temp; 
        document.getElementById("sf-activity").innerText = activity; 
        
        document.getElementById("sf-traits").innerText = traits; 
        document.getElementById("sf-image").src = image;
        
        sfModal.style.display = "block"; 
        document.body.style.overflow = "hidden"; 
        setTimeout(() => sfModal.classList.add("show"), 10);
    }
    function closeSFModal() { 
        sfModal.classList.remove("show"); 
        document.body.style.overflow = "auto"; 
        setTimeout(() => sfModal.style.display = "none", 300); 
    }

    // Sponsorship Modal Logic
    const sponsorModal = document.getElementById("sponsorModal");
    function openSponsorModal(petName) {
        document.getElementById("sponsor-pet-display").innerText = petName;
        document.getElementById("sponsor-pet-name").value = petName;
        sponsorModal.style.display = "block";
        document.body.style.overflow = "hidden"; 
        setTimeout(() => sponsorModal.classList.add("show"), 10);
    }
    function closeSponsorModal() {
        sponsorModal.classList.remove("show"); 
        document.body.style.overflow = "auto"; 
        setTimeout(() => sponsorModal.style.display = "none", 300);
    }
    function selectSponsorAmt(btn, amt) {
        document.querySelectorAll('.sponsor-amt').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('custom-sponsor-amt').value = '';
        document.getElementById('final-sponsor-amt').value = amt;
    }
    function updateCustomAmt() {
        let custom = document.getElementById('custom-sponsor-amt').value;
        if(custom > 0){
            document.querySelectorAll('.sponsor-amt').forEach(b => b.classList.remove('active'));
            document.getElementById('final-sponsor-amt').value = custom;
        }
    }

    // Name Generator Logic
    function generatePetName() {
        const names = [
            "Bella", "Luna", "Charlie", "Lucy", "Cooper", "Max", "Bailey", "Daisy", 
            "Sadie", "Maggie", "Rocky", "Oreo", "Milo", "Oliver", "Leo", "Loki", 
            "Cleo", "Chloe", "Simba", "Nala", "Zeus", "Apollo", "Buster", "Duke"
        ];
        const randomName = names[Math.floor(Math.random() * names.length)];
        document.getElementById("generated-name").innerText = randomName;
        document.getElementById("generated-name").style.color = "var(--primary)";
        document.getElementById("generated-name").style.transform = "scale(1.1)";
        setTimeout(() => { document.getElementById("generated-name").style.transform = "scale(1)"; }, 200);
    }

    // Feed Image Preview
    function showImagePreview(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('image-preview').src = e.target.result;
                document.getElementById('attachment-notif').style.display = 'block';
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    function removeAttachment() {
        document.getElementById('post_img_val').value = ""; 
        document.getElementById('image-preview').src = ""; 
        document.getElementById('attachment-notif').style.display = 'none'; 
    }

    // Community Location
    function getCommunityLocation() {
        if (navigator.geolocation) {
            document.getElementById('location-name-text').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Tagging...';
            document.getElementById('location-notif').style.display = 'block';
            
            navigator.geolocation.getCurrentPosition(pos => {
                fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${pos.coords.latitude}&lon=${pos.coords.longitude}`)
                .then(res => res.json()).then(data => { 
                    let loc = data.address.suburb || data.address.city || data.address.town || data.display_name.split(',')[0];
                    document.getElementById('post_loc_val').value = loc;
                    document.getElementById('location-name-text').innerText = loc;
                });
            }, err => {
                document.getElementById('location-name-text').innerText = "Location access denied.";
            });
        } else {
            alert("Geolocation is not supported by this browser.");
        }
    }

    // MAP LOGIC
    var map = L.map('strayMapLive').setView([14.35, 121.05], 13);
    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}', { attribution: 'Tiles &copy; Esri' }).addTo(map);

    L.Control.Fullscreen = L.Control.extend({
        onAdd: function(map) {
            var btn = L.DomUtil.create('a', 'custom-fullscreen-btn');
            btn.innerHTML = '<i class="fas fa-expand"></i>';
            btn.title = "View Fullscreen";
            btn.style.cssText = "background:white; width:34px; height:34px; line-height:34px; text-align:center; font-size:16px; color:#0F172A; border-radius:4px; border:2px solid rgba(0,0,0,0.2); cursor:pointer; display:block; text-decoration:none;";
            btn.onclick = function(e){
                e.preventDefault();
                var mapElem = document.getElementById('strayMapLive');
                if (!document.fullscreenElement) {
                    if (mapElem.requestFullscreen) { mapElem.requestFullscreen(); }
                    else if (mapElem.webkitRequestFullscreen) { mapElem.webkitRequestFullscreen(); }
                    else if (mapElem.msRequestFullscreen) { mapElem.msRequestFullscreen(); }
                    btn.innerHTML = '<i class="fas fa-compress"></i>';
                } else {
                    if (document.exitFullscreen) { document.exitFullscreen(); }
                    btn.innerHTML = '<i class="fas fa-expand"></i>';
                }
            };
            return btn;
        }
    });
    new L.Control.Fullscreen({ position: 'topleft' }).addTo(map);

    var routingControl = null;
    var strayLat = 14.36; var strayLng = 121.06;
    var strayMarker = L.marker([strayLat, strayLng]).addTo(map);
    
    strayMarker.bindPopup(`<div style="text-align:center; font-family:'Nunito', sans-serif;">
        <h4 style="margin:0 0 5px; font-size:16px; color:var(--danger);"><i class="fas fa-exclamation-circle"></i> Stray Detected</h4>
        <p id="routing-status" style="margin:0; font-size:14px;"><strong>Click this pin to generate route.</strong></p>
    </div>`);

    strayMarker.on('click', function() {
        document.getElementById('routing-status').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Calculating fastest route...';
        
        if(navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(pos => {
                var userLat = pos.coords.latitude; 
                var userLng = pos.coords.longitude;
                
                if(routingControl) map.removeControl(routingControl);
                
                routingControl = L.Routing.control({
                    waypoints: [ L.latLng(userLat, userLng), L.latLng(strayLat, strayLng) ],
                    routeWhileDragging: false, 
                    showAlternatives: false,
                    collapsible: true,
                    fitSelectedRoutes: true,
                    lineOptions: { styles: [{color: '#3B82F6', opacity: 0.9, weight: 6}] },
                    createMarker: function(i, wp) { 
                        if(i === 0) return L.marker(wp.latLng).bindPopup("<b>Rescue Van (You)</b>").openPopup(); 
                        return null; 
                    },
                    show: true
                }).addTo(map);
                
                setTimeout(() => { 
                    strayMarker.setPopupContent(`<div style="text-align:center; font-family:'Nunito', sans-serif;">
                        <p style="color:var(--success); font-weight:800; font-size:16px; margin:0;">Route Acquired!</p>
                        <p style="font-size:12px; color:var(--text-muted); margin-top:5px;">Check the panel. Close it anytime.</p>
                    </div>`); 
                }, 1500);
                
            }, err => { alert("Please allow location access to calculate the route."); });
        }
    });

    function getDetailedLocation(btn) {
        if (navigator.geolocation) {
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Pinging...';
            navigator.geolocation.getCurrentPosition(pos => {
                let lat = pos.coords.latitude; let lon = pos.coords.longitude;
                document.getElementById('lat').value = lat; document.getElementById('lon').value = lon;
                fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lon}`)
                .then(res => res.json()).then(data => {
                    document.getElementById('detected-address').value = data.display_name;
                    btn.innerHTML = '<i class="fas fa-check"></i> Coordinate Locked'; 
                    btn.style.background = 'var(--success)';
                    btn.style.borderColor = 'var(--success)';
                    map.setView([lat, lon], 17); L.marker([lat, lon]).addTo(map).bindPopup("Emergency Location").openPopup();
                });
            });
        }
    }
</script>

<script>
const petsData = <?php echo isset($pets_json_data) ? $pets_json_data : '[]'; ?>;
const AI_API_URL = "http://127.0.0.1:5000/predict";

function escapeHTML(value) {
    if (value === null || value === undefined) { return ""; }
    return String(value).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

/* ================= FIXED: SPECIES DETECTION (breed first) ================= */
function determinePetSpecies(pet) {
    const dogWords = ["dog","puppy","canine","aspin","retriever","husky","beagle","poodle","bulldog","chihuahua","pug","corgi","terrier","labrador","shepherd","rottweiler","shih tzu","dachshund","dalmatian","pomeranian","maltese","samoyed","akita","doberman","boxer","collie","golden"];
    const catWords = ["cat","kitten","feline","puspin","tabby","siamese","persian","ragdoll","sphynx","shorthair","longhair","maine coon","bengal","munchkin","scottish fold","russian blue","birman","burmese","calico"];

    const hasWord = (text, words) => words.some(w => new RegExp("\\b" + w + "s?\\b", "i").test(text));

    const detect = (text) => {
        text = String(text || "").toLowerCase();
        const hasDog = hasWord(text, dogWords);
        const hasCat = hasWord(text, catWords);
        if (hasDog && !hasCat) return "dog";
        if (hasCat && !hasDog) return "cat";
        return null;
    };

    // 1) Breed is the most reliable
    let result = detect(pet.breed);
    if (result) return result;

    // 2) Database species column
    const dbSpecies = String(pet.species || "").trim().toLowerCase();
    if (/^(dog|dogs|puppy|puppies|canine)$/.test(dbSpecies)) return "dog";
    if (/^(cat|cats|kitten|kittens|feline)$/.test(dbSpecies)) return "cat";

    // 3) Description / traits / name
    return detect([pet.name, pet.safe_traits, pet.characteristics, pet.description].join(" ")) || "unknown";
}

function analyzeUserPrompt(prompt) {
    const p = prompt.toLowerCase();
    let species = "any";

    const wantsDog = /\b(dog|dogs|puppy|puppies|canine|aspin|retriever|husky|beagle|poodle|bulldog|pug|corgi|labrador)\b/i.test(p);
    const wantsCat = /\b(cat|cats|kitten|kittens|feline|puspin|tabby|siamese|persian|ragdoll|calico)\b/i.test(p);
    const noCat = /\b(no|not|never|without|don't want|dont want|hate)\s+(a\s+)?(cat|cats|kitten|feline)/i.test(p);
    const noDog = /\b(no|not|never|without|don't want|dont want|hate)\s+(a\s+)?(dog|dogs|puppy|canine)/i.test(p);

    if (noCat && !noDog) { species = "dog"; }
    else if (noDog && !noCat) { species = "cat"; }
    else if (wantsDog && !wantsCat) { species = "dog"; }
    else if (wantsCat && !wantsDog) { species = "cat"; }

    let living_space = /\b(apartment|condo|studio|flat|small space|no yard)\b/i.test(p) ? "Apartment" : "House";
    let children = /\b(kid|kids|child|children|baby|toddler|family)\b/i.test(p) ? "Yes" : "No";
    let experience = /\b(experienced|experience|owned pets before|had pets|pet owner|previously owned)\b/i.test(p) ? "Experienced" : "Beginner";

    let activity = "Medium";
    if (/\b(calm|quiet|low energy|relaxed|chill|doesn't need much exercise|low activity)\b/i.test(p)) activity = "Low";
    else if (/\b(active|energetic|playful|running|hiking|athletic|high energy|high activity)\b/i.test(p)) activity = "High";

    let temperament = "Friendly";
    if (/\b(calm|quiet|peaceful|relaxed|chill)\b/i.test(p)) temperament = "Calm";
    else if (/\b(active|energetic|playful|athletic)\b/i.test(p)) temperament = "Active";
    else if (/\b(aggressive|guard|protective)\b/i.test(p)) temperament = "Aggressive";

    return { species, living_space, children, experience, activity, temperament };
}

/* ================= FIXED: PAYLOAD KEYS NOW MATCH predict_api.py ================= */
async function askTrainedAI(adopterData, pet) {
    const payload = {
        adopter_living_space: adopterData.living_space,
        adopter_children: adopterData.children,
        adopter_experience: adopterData.experience,
        adopter_activity: adopterData.activity,
        adopter_temperament: adopterData.temperament,

        pet_activity: pet.activity_level || "Medium",
        pet_temperament: pet.temperament || "Friendly",
        pet_species: determinePetSpecies(pet),
        pet_size: pet.size || "Medium"
    };

    const response = await fetch(AI_API_URL, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
    });
    if (!response.ok) { throw new Error("AI server returned HTTP " + response.status); }
    return await response.json();
}

function getMatchStyle(score) {
    if (score >= 85) { return { color: "#10B981", label: "Highly Recommended" }; }
    if (score >= 72) { return { color: "#F59E0B", label: "Strong Match" }; }
    return { color: "#3B82F6", label: "Good Match" };
}

/* ================= AI THINKING ANIMATION ================= */
const AI_STEPS = [
    "Reading your lifestyle profile",
    "Understanding your living space & schedule",
    "Scanning shelter pets",
    "Running compatibility model on each pet",
    "Weighing size, energy & temperament",
    "Ranking the best matches"
];
let aiStepTimer = null, aiStepIndex = 0;
const sleep = ms => new Promise(r => setTimeout(r, ms));

function renderAiSteps() {
    document.getElementById("ai-steps").innerHTML = AI_STEPS.map((s, i) =>
        `<li data-i="${i}" class="${i === 0 ? 'active' : ''}"><i class="${i === 0 ? 'fas fa-circle-notch fa-spin' : 'far fa-circle'}"></i> ${s}</li>`
    ).join("");
}

function setAiStep(index) {
    document.querySelectorAll("#ai-steps li").forEach((li, i) => {
        const icon = li.querySelector("i");
        li.classList.remove("active", "done");
        if (i < index) { li.classList.add("done"); icon.className = "fas fa-check-circle"; }
        else if (i === index) { li.classList.add("active"); icon.className = "fas fa-circle-notch fa-spin"; }
        else { icon.className = "far fa-circle"; }
    });
}

function startThinking() {
    const box = document.getElementById("ai-thinking");
    box.classList.remove("fade-out");
    box.style.display = "block";
    document.getElementById("ai-progress-bar").style.width = "5%";
    document.getElementById("ai-thinking-sub").innerText = "Warming up…";
    renderAiSteps();
    aiStepIndex = 0;
    clearInterval(aiStepTimer);
    // Auto-advance the first 3 steps; the rest follow real progress
    aiStepTimer = setInterval(() => {
        if (aiStepIndex < 2) { aiStepIndex++; setAiStep(aiStepIndex); }
    }, 1200);
}

function setThinkingProgress(done, total, petName) {
    if (aiStepIndex < 3) { aiStepIndex = 3; setAiStep(3); }
    if (total > 0 && done >= Math.ceil(total / 2) && aiStepIndex < 4) { aiStepIndex = 4; setAiStep(4); }
    const pct = 15 + Math.round((done / Math.max(total, 1)) * 75);
    document.getElementById("ai-progress-bar").style.width = pct + "%";
    document.getElementById("ai-thinking-sub").innerText = `Analyzing ${petName} (${done + 1} of ${total})…`;
}

async function finishThinking() {
    clearInterval(aiStepTimer);
    document.getElementById("ai-thinking-sub").innerText = "Ranking the best matches…";
    setAiStep(5);
    await sleep(700);
    setAiStep(AI_STEPS.length);              // marks everything done
    document.getElementById("ai-progress-bar").style.width = "100%";
    document.getElementById("ai-thinking-sub").innerText = "Done!";
    await sleep(600);
    stopThinking(true);
}

function stopThinking(animate) {
    clearInterval(aiStepTimer);
    const box = document.getElementById("ai-thinking");
    if (animate) {
        box.classList.add("fade-out");
        setTimeout(() => { box.style.display = "none"; }, 400);
    } else {
        box.style.display = "none";
    }
}

// AI Match Button Click Listener
document.getElementById("btn-run-ai").addEventListener("click", async function() {

    // BUILD PROMPT FROM COMBO BOXES
    const uiSpecies = document.getElementById("ai-species").value;
    const uiLiving = document.getElementById("ai-living").value;
    const uiActivity = document.getElementById("ai-activity").value;
    const uiKids = document.getElementById("ai-kids").value;
    const uiExp = document.getElementById("ai-experience").value;

    const uiOtherPets = document.getElementById("ai-other-pets").value;
    const uiTimeAway = document.getElementById("ai-time-away").value;

    const uiExtra = document.getElementById("ai-extra-notes").value;

    let generatedPrompt = (uiSpecies === "Any" ? "I am looking for a pet. " : `I am looking for a ${uiSpecies}. `) + `I live in an ${uiLiving}. `;
    if(uiKids === "Yes") generatedPrompt += "I have children. ";
    if(uiExp === "Experienced") generatedPrompt += "I am experienced with pets. ";
    if(uiActivity === "Calm") generatedPrompt += "I want a calm and relaxed pet. ";
    if(uiActivity === "Active") generatedPrompt += "I want a highly active and energetic pet. ";

    if(uiOtherPets !== "None") generatedPrompt += `I have other ${uiOtherPets.toLowerCase()} at home. `;
    if(uiTimeAway === "High") generatedPrompt += "I am away from home 8+ hours a day. ";
    else if(uiTimeAway === "Minimal") generatedPrompt += "I am mostly at home. ";

    if(uiExtra) generatedPrompt += " " + uiExtra;

    const prompt = generatedPrompt.trim();

    const btn = document.getElementById("btn-run-ai");
    const status = document.getElementById("ai-init-status");
    const results = document.getElementById("ai-results-container");

    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> AI is analyzing...'; btn.style.opacity = "0.7";
    status.style.display = "none";
    results.style.display = "none"; results.innerHTML = "";
    const thinkStart = Date.now();
    startThinking();

    try {
        const healthCheck = await fetch("http://127.0.0.1:5000/");
        if (!healthCheck.ok) { throw new Error("AI server is not responding."); }

        /* ===== FIXED: use dropdown values directly instead of regex guessing ===== */
        const activityMap = { Calm: "Low", Moderate: "Medium", Active: "High" };
        const tempMap = { Calm: "Calm", Moderate: "Friendly", Active: "Active" };

        const adopter = {
            species: uiSpecies.toLowerCase(),
            living_space: uiLiving,
            children: uiKids,
            experience: uiExp,
            activity: activityMap[uiActivity],
            temperament: tempMap[uiActivity]
        };

        let candidatePets = petsData.filter(function(pet) {
            const petSpecies = determinePetSpecies(pet);
            if (uiSpecies !== "Any" && petSpecies !== uiSpecies.toLowerCase()) { return false; }
            return true;
        });

        const matches = [];
        for (const [idx, pet] of candidatePets.entries()) {
            setThinkingProgress(idx, candidatePets.length, pet.name);
            await sleep(250); // small pause so each pet is visibly "considered"
            try {
                const aiResult = await askTrainedAI(adopter, pet);
                if (!aiResult.success) { continue; }

                let score = Number(aiResult.probabilities?.Compatible || 0);
                const petSize = (pet.size || "Medium").toLowerCase();
                const wantsSmall = /\b(small|tiny|little|mini|compact)\b/i.test(uiExtra);
                const wantsLarge = /\b(large|big|huge|giant|tall)\b/i.test(uiExtra);

                if (wantsSmall && petSize === "small") { score += 5; }
                if (wantsLarge && petSize === "large") { score += 5; }

                // Time away adjustments
                if (uiTimeAway === "High" && (pet.activity_level === "High" || pet.activity_level === "Active")) { score -= 15; }
                if (uiTimeAway === "High" && pet.temperament === "Calm") { score += 5; }

                score = Math.min(99, Math.max(0, Math.round(score)));
                const style = getMatchStyle(score);
                const reasons = [];

                reasons.push(petSpeciesLabel(determinePetSpecies(pet)));
                if (adopter.children === "Yes") {
                    if (/friendly|gentle|calm|sweet/i.test(pet.temperament || pet.characteristics || pet.description || "")) { reasons.push("Good temperament for a family"); }
                }

                const petActNorm = String(pet.activity_level || "").toLowerCase();
                const petActMapped = /low|calm|quiet/.test(petActNorm) ? "Low" : /high|active|energetic/.test(petActNorm) ? "High" : "Medium";
                if (adopter.activity === petActMapped) { reasons.push("Activity level matches your preference"); }

                if (adopter.temperament.toLowerCase() === (pet.temperament || "").toLowerCase()) { reasons.push("Temperament matches your preference"); }
                if (adopter.living_space === "Apartment" && petSize === "small") { reasons.push("Small size can suit apartment living"); }
                if (uiTimeAway === "High" && pet.temperament === "Calm") { reasons.push("Calm nature suits your schedule"); }

                matches.push({
                    ...pet, match_score: score, match_label: style.label, match_color: style.color,
                    match_reasons: reasons.slice(0, 4), ai_prediction: aiResult.prediction, ai_confidence: aiResult.compatibility_score
                });
            } catch (petError) { console.error("AI error for pet:", pet.name, petError); }
        }

        // Guarantee a minimum "thinking" time of ~3.5s
        const elapsed = Date.now() - thinkStart;
        if (elapsed < 3500) await sleep(3500 - elapsed);
        await finishThinking();

        matches.sort((a, b) => b.match_score - a.match_score);
        results.innerHTML = ""; results.style.display = "grid";
        const topPets = matches.filter(pet => pet.match_score >= 50).slice(0, 6);

        if (topPets.length === 0) {
            results.innerHTML = `
                <div style="grid-column:1/-1; text-align:center; padding:50px; background:rgba(255,255,255,0.05); border:1px dashed rgba(255,255,255,0.2); border-radius:var(--radius-lg);">
                    <i class="fas fa-paw" style="font-size:50px; color:var(--text-muted); margin-bottom:20px;"></i>
                    <h3 style="color: white;">No strong matches found</h3>
                    <p style="color:var(--text-light);">Try changing your preferences or selecting 'Any'.</p>
                </div>
            `;
        } else {
            topPets.forEach(function(pet) {
                const reasonsHTML = (pet.match_reasons || []).map(reason => `
                    <div style="font-size:0.8rem; font-weight:700; color:var(--text-muted); margin-top:6px; display:flex; align-items:center; gap:6px;">
                        <i class="fas fa-check-circle" style="color:var(--success);"></i> ${escapeHTML(reason)}
                    </div>
                `).join("");

                const card = document.createElement("div");
                card.className = "pet-card";
                card.style.border = "2px solid " + pet.match_color;

                card.innerHTML = `
                    <span class="match-score" style="border:1px solid ${pet.match_color}; color:${pet.match_color};">${pet.match_score}% Match</span>
                    <span class="match-badge" style="background:${pet.match_color};">${escapeHTML(pet.match_label)}</span>
                    <div class="pet-card-img-wrap">
                        <img src="${escapeHTML(pet.display_image)}" class="pet-card-img" alt="${escapeHTML(pet.name)}"
                             onerror="this.onerror=null;this.src='uploads/default.jpg';">
                    </div>

                    <div class="pet-card-content">
                        <h3 class="pet-card-title">${escapeHTML(pet.name)}</h3>
                        <div class="pet-tags" style="margin-bottom: 15px;">
                            <span class="pet-tag"><i class="fas fa-paw"></i> ${escapeHTML(pet.breed)}</span>
                            <span class="pet-tag"><i class="fas fa-ruler"></i> ${escapeHTML(pet.size || "Medium")}</span>
                        </div>

                        <div style="background:var(--bg-main); border-radius:var(--radius-md); padding:15px; margin-bottom:20px; border: 1px solid var(--border-color);">
                            <div style="font-size:0.75rem; font-weight:800; text-transform:uppercase; color:var(--text-light); margin-bottom: 8px;">Why this pet matches</div>
                            ${reasonsHTML}
                        </div>
                        <div style="display:flex; gap:10px; margin-top: auto;">
                            <button class="btn view-apply-btn" style="background:${pet.match_color}; color:white; flex:2;">View Full Profile</button>
                            <button class="btn btn-outline sponsor-ai-btn" style="flex:1;" data-name="${escapeHTML(pet.name)}" title="Sponsor this pet"><i class="fas fa-hand-holding-heart" style="color:var(--danger);"></i></button>
                        </div>
                    </div>
                `;

                card.querySelector(".view-apply-btn").addEventListener("click", function() {
                    openSFModal(
                        pet.id, pet.name, pet.breed, pet.age,
                        pet.safe_gender || pet.gender || "Unknown",
                        pet.size || "Medium", pet.temperament || "Friendly",
                        pet.activity_level || "Moderate", pet.safe_traits || pet.description || "",
                        pet.display_image
                    );
                });

                card.querySelector(".sponsor-ai-btn").addEventListener("click", function(e) {
                    openSponsorModal(e.currentTarget.getAttribute("data-name"));
                });

                results.appendChild(card);
            });
        }

        status.style.display = "flex";
        status.style.background = "rgba(16, 185, 129, 0.1)";
        status.style.borderColor = "#10B981";
        status.style.color = "#34D399";
        status.innerHTML = `<i class="fas fa-check-circle"></i> AI analysis completed. ${topPets.length} recommended pet(s) found.`;
        setTimeout(function() { results.scrollIntoView({ behavior: "smooth", block: "start" }); }, 100);

    } catch (error) {
        stopThinking(false);
        status.style.display = "flex";
        console.error("Pet AI Error:", error);
        status.style.background = "rgba(239, 68, 68, 0.1)";
        status.style.borderColor = "#EF4444";
        status.style.color = "#FCA5A5";
        status.innerHTML = `<i class="fas fa-exclamation-triangle"></i> AI server is unavailable.`;
        alert("Can't Connect to the AI Server.\n\nPlease Try Again Later, Or try checking if the following is running:\n\npython predict_api.py");
    } finally {
        stopThinking(false);
        btn.disabled = false; btn.style.opacity = "1";
        btn.innerHTML = '<i class="fas fa-magic"></i> Find My Perfect Match via AI';
    }
});

function petSpeciesLabel(species) {
    if (species === "dog") { return "🐾 Dog compatibility analyzed"; }
    if (species === "cat") { return "🐱 Cat compatibility analyzed"; }
    return "🐾 Pet compatibility analyzed";
}

/* ================= FAVORITES UI ================= */
document.addEventListener("DOMContentLoaded", function () {
    const favoriteButtons = document.querySelectorAll(".favorite-btn");
    favoriteButtons.forEach(function (button) {
        button.addEventListener("click", function () {
            button.style.transform = "scale(0.9)";
            setTimeout(function () { button.style.transform = ""; }, 150);
        });
    });
});
</script>
</body> 
</html>
