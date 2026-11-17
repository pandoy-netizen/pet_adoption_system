<?php
session_start();
ob_start();

/* =========================
   LOGOUT HANDLER
========================= */
if (
    isset($_GET['action']) && 
    $_GET['action'] === 'logout'
) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'] ?? '/',
            'domain' => $params['domain'] ?? '',
            'secure' => $params['secure'] ?? false,
            'httponly' => $params['httponly'] ?? true,
            'samesite' => $params['samesite'] ?? 'Lax'
        ]);
    }
    session_destroy();
    header("Location: /index.php", true, 302);
    exit;
}

/* =========================
   TAB SYSTEM
========================= */
$activeTab = isset($_GET['tab']) 
    ? $_GET['tab'] 
    : 'dashboard';

/* =========================
   DATABASE CONNECTION & SETUP
   Uses Railway environment variables via config/db.php
========================= */
require_once __DIR__ . '/config/db.php';

/* =========================
   CREATE TABLES IF NEEDED
========================= */

mysqli_query(
    $conn, 
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100),
        email VARCHAR(100) UNIQUE,
        password VARCHAR(255),
        role VARCHAR(50) DEFAULT 'Visitor',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )"
);

mysqli_query(
    $conn, 
    "CREATE TABLE IF NOT EXISTS pets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100),
        species VARCHAR(50),
        breed VARCHAR(100),
        age INT,
        gender VARCHAR(20),
        size VARCHAR(50),
        activity_level VARCHAR(50),
        vaccinated VARCHAR(20),
        temperament VARCHAR(100),
        description TEXT,
        image VARCHAR(255),
        status VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )"
);

mysqli_query(
    $conn, 
    "CREATE TABLE IF NOT EXISTS adoption_applications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        pet_id INT NOT NULL,
        living_space VARCHAR(255),
        experience_level VARCHAR(255),
        activity_level VARCHAR(255),
        with_children VARCHAR(50),
        status VARCHAR(50) DEFAULT 'Pending',
        application_date DATETIME DEFAULT CURRENT_TIMESTAMP
    )"
);

mysqli_query(
    $conn, 
    "CREATE TABLE IF NOT EXISTS rescue_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        reporter_name VARCHAR(100),
        contact_info VARCHAR(100),
        location VARCHAR(255),
        latitude VARCHAR(50),
        longitude VARCHAR(50),
        description TEXT,
        image VARCHAR(255),
        status VARCHAR(50) DEFAULT 'Action Needed',
        reported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )"
);

mysqli_query(
    $conn, 
    "CREATE TABLE IF NOT EXISTS pet_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pet_id INT NOT NULL,
        action_desc VARCHAR(255),
        date_logged TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )"
);

mysqli_query(
    $conn, 
    "CREATE TABLE IF NOT EXISTS community_posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        user_display_name VARCHAR(100),
        content TEXT,
        location VARCHAR(255),
        image VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )"
);

mysqli_query(
    $conn, 
    "CREATE TABLE IF NOT EXISTS staff_attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100),
        role VARCHAR(50),
        status VARCHAR(50) DEFAULT 'Offline',
        last_login TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )"
);

/* =========================================================
   NEW SHELTER MANAGEMENT FEATURES
   - Pet medical / vaccination records
   - Rescue priority and assignment
   - Adoption appointments
   - Foster care management
   - User notifications
   - Pet favorites foundation
========================================================= */

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS pet_medical_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pet_id INT NOT NULL,
    record_type VARCHAR(100) NOT NULL,
    diagnosis VARCHAR(255) DEFAULT '',
    treatment TEXT,
    medication VARCHAR(255) DEFAULT '',
    veterinarian VARCHAR(100) DEFAULT '',
    record_date DATE DEFAULT NULL,
    next_checkup DATE DEFAULT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS adoption_appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    pet_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    purpose VARCHAR(100) DEFAULT 'Meet the Pet',
    notes TEXT,
    status VARCHAR(50) DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS foster_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    pet_id INT NOT NULL,
    start_date DATE DEFAULT NULL,
    expected_end_date DATE DEFAULT NULL,
    foster_reason TEXT,
    notes TEXT,
    status VARCHAR(50) DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'System',
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS pet_favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    pet_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_favorite (user_id, pet_id)
)");

/* Add rescue priority / assignment columns only when they do not exist. */
$check_priority = mysqli_query($conn, "SHOW COLUMNS FROM rescue_reports LIKE 'priority'");
if ($check_priority && mysqli_num_rows($check_priority) == 0) {
    mysqli_query($conn, "ALTER TABLE rescue_reports ADD COLUMN priority VARCHAR(20) DEFAULT 'Medium'");
}

$check_assigned = mysqli_query($conn, "SHOW COLUMNS FROM rescue_reports LIKE 'assigned_to'");
if ($check_assigned && mysqli_num_rows($check_assigned) == 0) {
    mysqli_query($conn, "ALTER TABLE rescue_reports ADD COLUMN assigned_to VARCHAR(100) DEFAULT ''");
}

/* =========================================================
   END NEW SHELTER MANAGEMENT FEATURES
========================================================= */

/* Ensure every table's id column is AUTO_INCREMENT */
$tables_to_fix = [
    'users', 'pets', 'adoption_applications', 'rescue_reports',
    'pet_history', 'community_posts', 'staff_attendance',
    'pet_medical_records', 'adoption_appointments',
    'foster_applications', 'notifications', 'pet_favorites'
];

foreach ($tables_to_fix as $tbl) {
    try {
        $col = mysqli_query($conn, "SHOW COLUMNS FROM `$tbl` LIKE 'id'");
        if ($col && ($c = mysqli_fetch_assoc($col)) && stripos($c['Extra'], 'auto_increment') === false) {
            mysqli_query($conn, "DELETE FROM `$tbl` WHERE id = 0");
            if ($c['Key'] !== 'PRI') {
                mysqli_query($conn, "ALTER TABLE `$tbl` ADD PRIMARY KEY (id)");
            }
            mysqli_query($conn, "ALTER TABLE `$tbl` MODIFY id INT NOT NULL AUTO_INCREMENT");
        }
    } catch (mysqli_sql_exception $e) {
        error_log("Could not fix $tbl.id: " . $e->getMessage());
    }
}

/* =========================================================
   IMPORTANT DATABASE FIX
   Existing pets table may not have activity_level/vaccinated.
   We check each column separately before adding it.
========================================================= */

/* Check activity_level */
$check_activity = mysqli_query(
    $conn, 
    "SHOW COLUMNS FROM pets LIKE 'activity_level'"
);

if ($check_activity && mysqli_num_rows($check_activity) == 0) {
    mysqli_query(
        $conn, 
        "ALTER TABLE pets
         ADD COLUMN activity_level VARCHAR(50) DEFAULT ''"
    );
}

/* Check vaccinated */
$check_vaccine = mysqli_query(
    $conn, 
    "SHOW COLUMNS FROM pets LIKE 'vaccinated'"
);

if ($check_vaccine && mysqli_num_rows($check_vaccine) == 0) {
    mysqli_query(
        $conn, 
        "ALTER TABLE pets
         ADD COLUMN vaccinated VARCHAR(20) DEFAULT 'No'"
    );
}

/* Check temperament */
$check_temperament = mysqli_query(
    $conn, 
    "SHOW COLUMNS FROM pets LIKE 'temperament'"
);

if ($check_temperament && mysqli_num_rows($check_temperament) == 0) {
    mysqli_query(
        $conn, 
        "ALTER TABLE pets
         ADD COLUMN temperament VARCHAR(100) DEFAULT ''"
    );
}

/* Check size */
$check_size = mysqli_query(
    $conn, 
    "SHOW COLUMNS FROM pets LIKE 'size'"
);

if ($check_size && mysqli_num_rows($check_size) == 0) {
    mysqli_query(
        $conn, 
        "ALTER TABLE pets
         ADD COLUMN size VARCHAR(50) DEFAULT ''"
    );
}

/* Check species */
$check_species = mysqli_query(
    $conn, 
    "SHOW COLUMNS FROM pets LIKE 'species'"
);

if ($check_species && mysqli_num_rows($check_species) == 0) {
    mysqli_query(
        $conn, 
        "ALTER TABLE pets
         ADD COLUMN species VARCHAR(50) DEFAULT 'Dog'"
    );
}

/* Check breed */
$check_breed = mysqli_query(
    $conn, 
    "SHOW COLUMNS FROM pets LIKE 'breed'"
);

if ($check_breed && mysqli_num_rows($check_breed) == 0) {
    mysqli_query(
        $conn, 
        "ALTER TABLE pets
         ADD COLUMN breed VARCHAR(100) DEFAULT ''"
    );
}

/* Check gender */
$check_gender = mysqli_query(
    $conn, 
    "SHOW COLUMNS FROM pets LIKE 'gender'"
);

if ($check_gender && mysqli_num_rows($check_gender) == 0) {
    mysqli_query(
        $conn, 
        "ALTER TABLE pets
         ADD COLUMN gender VARCHAR(20) DEFAULT ''"
    );
}

/* Check description */
$check_description = mysqli_query(
    $conn, 
    "SHOW COLUMNS FROM pets LIKE 'description'"
);

if ($check_description && mysqli_num_rows($check_description) == 0) {
    mysqli_query(
        $conn, 
        "ALTER TABLE pets
         ADD COLUMN description TEXT"
    );
}

/* Check image */
$check_image = mysqli_query(
    $conn, 
    "SHOW COLUMNS FROM pets LIKE 'image'"
);

if ($check_image && mysqli_num_rows($check_image) == 0) {
    mysqli_query(
        $conn, 
        "ALTER TABLE pets
         ADD COLUMN image VARCHAR(255) DEFAULT 'default.jpg'"
    );
}

/* Check status */
$check_status = mysqli_query(
    $conn, 
    "SHOW COLUMNS FROM pets LIKE 'status'"
);

if ($check_status && mysqli_num_rows($check_status) == 0) {
    mysqli_query(
        $conn, 
        "ALTER TABLE pets
         ADD COLUMN status VARCHAR(50) DEFAULT 'Intake'"
    );
}

/* =========================
   USERS NAME FIELD CHECK
========================= */
$user_col_check = mysqli_query(
    $conn, 
    "SHOW COLUMNS FROM users LIKE 'name'"
);

if ($user_col_check && mysqli_num_rows($user_col_check) > 0) {
    $u_name_field = "name";
} else {
    $username_check = mysqli_query(
        $conn, 
        "SHOW COLUMNS FROM users LIKE 'username'"
    );

    if ($username_check && mysqli_num_rows($username_check) > 0) {
        $u_name_field = "username";
    } else {
        $u_name_field = "name";
    }
}

/* =========================
   STAFF DEFAULT DATA
========================= */
$check_staff = mysqli_query(
    $conn, 
    "SELECT COUNT(*) as count FROM staff_attendance"
);

if ($check_staff) {
    $staff_count_data = mysqli_fetch_assoc($check_staff);

    if ($staff_count_data['count'] == 0) {
        mysqli_query(
            $conn, 
            "INSERT INTO staff_attendance (name, role, status)
             VALUES
             ('Dr. Santos', 'Veterinarian', 'Present'),
             ('Mark Reyes', 'Shelter Staff', 'Present'),
             ('Anna Cruz', 'Admin', 'Offline')"
        );
    }
}

/* =========================
   HANDLE POST ACTIONS
========================= */


foreach (['profile_photo' => 'VARCHAR(255) DEFAULT NULL', 'phone' => 'VARCHAR(30) DEFAULT NULL', 'address' => 'VARCHAR(255) DEFAULT NULL', 'bio' => 'TEXT NULL'] as $column => $definition) {
    try {
        $safeColumn = str_replace('`', '', $column);
        $check = $conn->query("SHOW COLUMNS FROM users LIKE '$safeColumn'");
        if ($check && $check->num_rows === 0) {
            $conn->query("ALTER TABLE users ADD COLUMN `$safeColumn` $definition");
        }
    } catch (Throwable $e) {
        error_log('Profile field setup: ' . $e->getMessage());
    }
}


if (empty($_SESSION['user_id']) || strtolower((string)($_SESSION['role'] ?? '')) !== 'admin') {
    header('Location: login.php');
    exit;
}

$adminId = (int)$_SESSION['user_id'];
$adminProfileMessage = '';
$adminProfileError = '';
$adminNameColumn = $u_name_field === 'username' ? 'username' : 'name';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_admin_profile'])) {
    if (!hash_equals((string)($_SESSION['admin_profile_csrf'] ?? ''), (string)($_POST['admin_profile_csrf'] ?? ''))) {
        $adminProfileError = 'Your session expired. Refresh the page and try again.';
    } else {
        $newName = trim((string)($_POST['admin_profile_name'] ?? ''));
        $newPhone = trim((string)($_POST['admin_profile_phone'] ?? ''));
        $newBio = trim((string)($_POST['admin_profile_bio'] ?? ''));
        if ($newName === '' || mb_strlen($newName) > 100 || mb_strlen($newPhone) > 30 || mb_strlen($newBio) > 1000) {
            $adminProfileError = 'Please enter a valid name, phone number and short bio.';
        } else {
            $newPhoto = null;
            if (isset($_FILES['admin_profile_photo']) && $_FILES['admin_profile_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
                $upload = $_FILES['admin_profile_photo'];
                if ($upload['error'] !== UPLOAD_ERR_OK || $upload['size'] > 2 * 1024 * 1024) {
                    $adminProfileError = 'Profile image must be smaller than 2 MB.';
                } else {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($upload['tmp_name']);
                    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                    if (!isset($extensions[$mime])) {
                        $adminProfileError = 'Only JPG, PNG and WebP images are allowed.';
                    } else {
                        $dir = __DIR__ . '/uploads/admin_profiles';
                        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                            $adminProfileError = 'Could not create the profile photo folder.';
                        } else {
                            $filename = 'admin_' . $adminId . '_' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
                            if (move_uploaded_file($upload['tmp_name'], $dir . '/' . $filename)) {
                                $newPhoto = 'uploads/admin_profiles/' . $filename;
                            } else {
                                $adminProfileError = 'Could not save the profile photo.';
                            }
                        }
                    }
                }
            }
            if ($adminProfileError === '') {
                try {
                    if ($newPhoto !== null) {
                        $update = $conn->prepare("UPDATE users SET `$adminNameColumn`=?, phone=?, bio=?, profile_photo=? WHERE id=? AND LOWER(role)='admin'");
                        $update->bind_param('ssssi', $newName, $newPhone, $newBio, $newPhoto, $adminId);
                    } else {
                        $update = $conn->prepare("UPDATE users SET `$adminNameColumn`=?, phone=?, bio=? WHERE id=? AND LOWER(role)='admin'");
                        $update->bind_param('sssi', $newName, $newPhone, $newBio, $adminId);
                    }
                    $update->execute();
                    $update->close();
                    $_SESSION['name'] = $newName;
                    header('Location: admin_dashboard.php?tab=' . urlencode($activeTab) . '&profile_saved=1');
                    exit;
                } catch (Throwable $e) {
                    error_log('Admin profile save: ' . $e->getMessage());
                    $adminProfileError = 'Unable to update profile. Please try again.';
                }
            }
        }
    }
}

if (empty($_SESSION['admin_profile_csrf'])) {
    $_SESSION['admin_profile_csrf'] = bin2hex(random_bytes(32));
}
$adminProfile = ['display_name' => $_SESSION['name'] ?? 'Administrator', 'email' => $_SESSION['email'] ?? '', 'phone' => '', 'bio' => '', 'profile_photo' => ''];
try {
    $adminQuery = $conn->prepare("SELECT `$adminNameColumn` AS display_name, email, phone, bio, profile_photo FROM users WHERE id=? AND LOWER(role)='admin' LIMIT 1");
    $adminQuery->bind_param('i', $adminId);
    $adminQuery->execute();
    $adminRecord = $adminQuery->get_result()->fetch_assoc();
    $adminQuery->close();
    if (!$adminRecord) {
        header('Location: login.php');
        exit;
    }
    $adminProfile = $adminRecord;
} catch (Throwable $e) {
    error_log('Admin profile load: ' . $e->getMessage());
}
$adminDisplayName = trim((string)($adminProfile['display_name'] ?? '')) ?: 'Administrator';
$adminInitials = strtoupper(substr($adminDisplayName, 0, 1));
$adminPhoto = trim((string)($adminProfile['profile_photo'] ?? ''));
if ($adminPhoto && !preg_match('~^uploads/[a-zA-Z0-9_./-]+$~', $adminPhoto)) $adminPhoto = '';
$adminEscape = static function ($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); };

// Additive migration: existing shelter records and tables are preserved.
try {
    $hoursColumn = $conn->query("SHOW COLUMNS FROM shelters LIKE 'opening_hours'");
    if ($hoursColumn && $hoursColumn->num_rows === 0) {
        $conn->query("ALTER TABLE shelters ADD COLUMN opening_hours TEXT NULL");
    }
} catch (Throwable $e) { error_log('Shelter hours migration: '.$e->getMessage()); }

if (empty($_SESSION['shelter_admin_csrf'])) $_SESSION['shelter_admin_csrf'] = bin2hex(random_bytes(32));
$shelterAdminNotice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['shelter_action'])) {
    $action = (string)$_POST['shelter_action'];
    if (!hash_equals($_SESSION['shelter_admin_csrf'], (string)($_POST['shelter_csrf'] ?? ''))) {
        $shelterAdminNotice = 'Invalid session token. Refresh and try again.';
    } else {
        try {
            $sid = (int)($_POST['shelter_id'] ?? 0);
            if ($action === 'delete' && $sid > 0) {
                $stmt = $conn->prepare('DELETE FROM shelters WHERE id=?');
                $stmt->bind_param('i', $sid); $stmt->execute(); $stmt->close();
                $shelterAdminNotice = 'Shelter deleted.';
            } elseif ($action === 'status' && $sid > 0) {
                $status = (string)($_POST['verification_status'] ?? 'Pending');
                if (!in_array($status, ['Pending','Approved','Rejected','Suspended'], true)) throw new RuntimeException('Invalid verification status.');
                $stmt = $conn->prepare('UPDATE shelters SET verification_status=? WHERE id=?');
                $stmt->bind_param('si', $status, $sid); $stmt->execute(); $stmt->close();
                $shelterAdminNotice = 'Shelter verification updated.';
            } elseif (in_array($action, ['add','edit'], true)) {
                $name = trim((string)($_POST['shelter_name'] ?? ''));
                $address = trim((string)($_POST['address'] ?? ''));
                $city = trim((string)($_POST['city'] ?? ''));
                $province = trim((string)($_POST['province'] ?? ''));
                $latInput = trim((string)($_POST['latitude'] ?? ''));
                $lngInput = trim((string)($_POST['longitude'] ?? ''));
                $lat = $latInput === '' ? null : (float)$latInput;
                $lng = $lngInput === '' ? null : (float)$lngInput;
                $phone = trim((string)($_POST['contact_number'] ?? ''));
                $email = trim((string)($_POST['contact_email'] ?? ''));
                $description = trim((string)($_POST['description'] ?? ''));
                $descriptionOptions = ['Animal Rescue and Rehabilitation','Adoption and Rehoming','Temporary Animal Shelter','Community Animal Welfare','Veterinary and Recovery Support','Stray Animal Rescue','Wildlife Rescue and Rehabilitation','Animal Sanctuary'];
                if (!in_array($description, $descriptionOptions, true)) throw new RuntimeException('Please choose a valid shelter service.');
                $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
                $openingHours = [];
                foreach ($days as $day) {
                    $enabled = isset($_POST['day_open'][$day]);
                    $from = (string)($_POST['open_time'][$day] ?? '08:00');
                    $to = (string)($_POST['close_time'][$day] ?? '17:00');
                    if ($enabled && (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$from) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$to) || $from >= $to)) throw new RuntimeException('Please check the opening and closing times for '.$day.'.');
                    $openingHours[$day] = ['open'=>$enabled,'from'=>$enabled?$from:null,'to'=>$enabled?$to:null];
                }
                $openingHoursJson = json_encode($openingHours, JSON_THROW_ON_ERROR);
                $animals = trim((string)($_POST['animal_types'] ?? ''));
                $capacity = (int)($_POST['capacity'] ?? 0);
                $slots = (int)($_POST['available_slots'] ?? 0);
                $rescue = isset($_POST['rescue_available']) ? 1 : 0;
                $status = (string)($_POST['verification_status'] ?? 'Pending');
                if ($name === '' || $address === '' || $city === '' || $province === '' || strlen($name)>150 || strlen($address)>255 || strlen($city)>100 || strlen($province)>100 || strlen($phone)>30 || strlen($email)>150 || strlen($animals)>255 || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) || $capacity<0 || $slots<0 || $slots>$capacity || !in_array($status,['Pending','Approved','Rejected','Suspended'],true) || ($latInput!=='' && (!is_numeric($latInput)||$lat < -90||$lat>90)) || ($lngInput!=='' && (!is_numeric($lngInput)||$lng < -180||$lng>180)) || (($latInput==='') !== ($lngInput===''))) throw new RuntimeException('Check required fields, capacity, email and coordinates. Both GPS coordinates are required for the map.');
                if ($action === 'add') {
                    $stmt = $conn->prepare('INSERT INTO shelters (shelter_name,address,city,province,latitude,longitude,contact_number,contact_email,description,animal_types,capacity,available_slots,rescue_available,verification_status,opening_hours) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                    $stmt->bind_param('ssssddssssiisss', $name,$address,$city,$province,$lat,$lng,$phone,$email,$description,$animals,$capacity,$slots,$rescue,$status,$openingHoursJson);
                } else {
                    if ($sid<=0) throw new RuntimeException('Invalid shelter ID.');
                    $stmt = $conn->prepare('UPDATE shelters SET shelter_name=?,address=?,city=?,province=?,latitude=?,longitude=?,contact_number=?,contact_email=?,description=?,animal_types=?,capacity=?,available_slots=?,rescue_available=?,verification_status=?,opening_hours=? WHERE id=?');
                    $stmt->bind_param('ssssddssssiisssi', $name,$address,$city,$province,$lat,$lng,$phone,$email,$description,$animals,$capacity,$slots,$rescue,$status,$openingHoursJson,$sid);
                }
                $stmt->execute(); $stmt->close();
                $shelterAdminNotice = $action === 'add' ? 'Shelter added successfully.' : 'Shelter updated successfully.';
            }
        } catch (Throwable $e) {
            error_log('Shelter admin action: '.$e->getMessage());
            $shelterAdminNotice = $e instanceof RuntimeException ? $e->getMessage() : 'Unable to save shelter. Check the database tables.';
        }
    }
}
$shelterAdminRows = [];
try {
    $shelterQuery = $conn->query('SELECT s.*, (SELECT ROUND(AVG(r.rating),1) FROM shelter_ratings r WHERE r.shelter_id=s.id) AS average_rating, (SELECT COUNT(*) FROM shelter_ratings r WHERE r.shelter_id=s.id) AS rating_count FROM shelters s ORDER BY s.id DESC');
    while ($srow = $shelterQuery->fetch_assoc()) $shelterAdminRows[] = $srow;
} catch (Throwable $e) { error_log('Shelter admin list: '.$e->getMessage()); $shelterAdminNotice = 'Shelter tables could not be loaded.'; }


/* USER ACTIONS (ADD, EDIT, DELETE) */

if (isset($_POST['add_user'])) {
    
    $name = mysqli_real_escape_string(
        $conn, 
        $_POST['user_name']
    );
    
    $email = mysqli_real_escape_string(
        $conn, 
        $_POST['user_email']
    );
    
    $role = mysqli_real_escape_string(
        $conn, 
        $_POST['user_role']
    );
    
    $password = password_hash(
        $_POST['user_password'], 
        PASSWORD_DEFAULT
    );

    mysqli_query(
        $conn, 
        "INSERT INTO users (
            $u_name_field, 
            email, 
            role, 
            password
         ) 
         VALUES (
            '$name', 
            '$email', 
            '$role', 
            '$password'
         )"
    );

    header("Location: ?tab=manage-users");
    exit;
}

if (isset($_POST['edit_user'])) {
    
    $id = intval($_POST['edit_user_id']);
    
    $name = mysqli_real_escape_string(
        $conn, 
        $_POST['user_name']
    );
    
    $email = mysqli_real_escape_string(
        $conn, 
        $_POST['user_email']
    );
    
    $role = mysqli_real_escape_string(
        $conn, 
        $_POST['user_role']
    );

    $updateQ = "UPDATE users 
                SET $u_name_field='$name', 
                    email='$email', 
                    role='$role'";

    if (!empty($_POST['user_password'])) {
        
        $password = password_hash(
            $_POST['user_password'], 
            PASSWORD_DEFAULT
        );
        
        $updateQ .= ", password='$password'";
        
    }

    $updateQ .= " WHERE id=$id";
    
    mysqli_query($conn, $updateQ);

    header("Location: ?tab=manage-users");
    exit;
}

if (isset($_POST['delete_user'])) {
    
    $user_id = intval($_POST['user_id']);
    
    mysqli_query(
        $conn, 
        "DELETE FROM users WHERE id = $user_id"
    );

    header("Location: ?tab=manage-users");
    exit;
}

/* POST ACTIONS */

if (isset($_POST['delete_post'])) {

    $post_id = intval($_POST['post_id']);

    mysqli_query(
        $conn, 
        "DELETE FROM community_posts
         WHERE id = $post_id"
    );

    header("Location: ?tab=community-feed");
    exit;
}

if (isset($_POST['update_staff'])) {

    $staff_id = intval($_POST['staff_id']);
    
    $s_status = mysqli_real_escape_string(
        $conn, 
        $_POST['staff_status']
    );

    mysqli_query(
        $conn, 
        "UPDATE staff_attendance
         SET status='$s_status'
         WHERE id=$staff_id"
    );

    header("Location: ?tab=" . $activeTab);
    exit;
}

/* =========================
   RESCUE REPORT ACTIONS
========================= */

if (isset($_POST['update_report'])) {

    $rep_id = intval($_POST['report_id']);

    $new_status = mysqli_real_escape_string(
        $conn,
        $_POST['report_status']
    );

    mysqli_query(
        $conn,
        "UPDATE rescue_reports
         SET status='$new_status'
         WHERE id=$rep_id"
    );

    $report_owner_q = mysqli_query($conn, "SELECT user_id, location FROM rescue_reports WHERE id=$rep_id LIMIT 1");
    if ($report_owner_q && ($report_owner = mysqli_fetch_assoc($report_owner_q))) {
        if (!empty($report_owner['user_id'])) {
            createAdminNotification(
                $conn,
                $report_owner['user_id'],
                'Rescue report status updated',
                'Your rescue report at ' . ($report_owner['location'] ?? 'the reported location') . ' is now ' . $new_status . '.',
                'Rescue'
            );
        }
    }

    header("Location: ?tab=rescue-reports");
    exit;
}

if (isset($_POST['edit_rescue_report'])) {

    $rep_id = intval($_POST['edit_report_id']);
    
    $loc = mysqli_real_escape_string(
        $conn, 
        $_POST['rep_location']
    );
    
    $desc = mysqli_real_escape_string(
        $conn, 
        $_POST['rep_description']
    );
    
    $rep = mysqli_real_escape_string(
        $conn, 
        $_POST['rep_reporter']
    );
    
    $con = mysqli_real_escape_string(
        $conn, 
        $_POST['rep_contact']
    );
    
    mysqli_query(
        $conn, 
        "UPDATE rescue_reports 
         SET 
            location='$loc', 
            description='$desc', 
            reporter_name='$rep', 
            contact_info='$con' 
         WHERE id=$rep_id"
    );

    header("Location: ?tab=rescue-reports");
    exit;
}

if (isset($_POST['delete_report'])) {

    $rep_id = intval($_POST['report_id']);
    
    mysqli_query(
        $conn, 
        "DELETE FROM rescue_reports 
         WHERE id=$rep_id"
    );

    header("Location: ?tab=rescue-reports");
    exit;
}

/* =========================
   ADD PET
========================= */
if (isset($_POST['add_pet'])) {

    $name = mysqli_real_escape_string($conn, $_POST['pet_name']);
    $species = mysqli_real_escape_string($conn, $_POST['species']);
    $breed = mysqli_real_escape_string($conn, $_POST['breed']);
    $age = intval($_POST['age']);
    $gender = mysqli_real_escape_string($conn, $_POST['gender']);
    $size = mysqli_real_escape_string($conn, $_POST['size']);
    $activity = mysqli_real_escape_string($conn, $_POST['activity_level']);
    $temperament = mysqli_real_escape_string($conn, $_POST['temperament']);
    $vaccine = mysqli_real_escape_string($conn, $_POST['vaccinated']);
    $desc = mysqli_real_escape_string($conn, $_POST['description']);

    $imageName = "default.jpg";

    if (!empty($_FILES["pet_image"]["name"])) {

        $uploadDir = "uploads/";

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $imageName = time() . "_" . basename($_FILES["pet_image"]["name"]);

        move_uploaded_file(
            $_FILES["pet_image"]["tmp_name"],
            $uploadDir . $imageName
        );
    }

    $q = "
        INSERT INTO pets
        (
            name,
            species,
            breed,
            age,
            gender,
            size,
            activity_level,
            temperament,
            vaccinated,
            description,
            image,
            status
        )
        VALUES
        (
            '$name',
            '$species',
            '$breed',
            '$age',
            '$gender',
            '$size',
            '$activity',
            '$temperament',
            '$vaccine',
            '$desc',
            '$imageName',
            'Intake'
        )
    ";

        try {
        if (mysqli_query($conn, $q)) {

            $new_pet_id = mysqli_insert_id($conn);

            mysqli_query(
                $conn, 
                "INSERT INTO pet_history
                 (pet_id, action_desc)
                 VALUES
                 ($new_pet_id, 'Pet registered and taken into Intake.')"
            );
        }
    } catch (mysqli_sql_exception $e) {
        error_log("Add pet failed: " . $e->getMessage());
    }

    header("Location: ?tab=manage-pets");
    exit;
}

/* =========================
   EDIT PET
========================= */
if (isset($_POST['edit_pet'])) {

    $id = intval($_POST['edit_pet_id']);

    $name = mysqli_real_escape_string($conn, $_POST['pet_name']);
    $species = mysqli_real_escape_string($conn, $_POST['species']);
    $breed = mysqli_real_escape_string($conn, $_POST['breed']);
    $age = intval($_POST['age']);
    $gender = mysqli_real_escape_string($conn, $_POST['gender']);
    $size = mysqli_real_escape_string($conn, $_POST['size']);
    $activity = mysqli_real_escape_string($conn, $_POST['activity_level']);
    $temperament = mysqli_real_escape_string($conn, $_POST['temperament']);
    $vaccine = mysqli_real_escape_string($conn, $_POST['vaccinated']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $desc = mysqli_real_escape_string($conn, $_POST['description']);

    $old_query = mysqli_query(
        $conn,
        "SELECT status FROM pets WHERE id=$id"
    );

    $old_data = $old_query ? mysqli_fetch_assoc($old_query) : null;

    $updateQ = "
        UPDATE pets
        SET
            name='$name',
            species='$species',
            breed='$breed',
            age='$age',
            gender='$gender',
            size='$size',
            activity_level='$activity',
            temperament='$temperament',
            vaccinated='$vaccine',
            status='$status',
            description='$desc'
    ";

    if (!empty($_FILES["pet_image"]["name"])) {

        $uploadDir = "uploads/";

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $imageName = time() . "_" . basename($_FILES["pet_image"]["name"]);

        move_uploaded_file(
            $_FILES["pet_image"]["tmp_name"],
            $uploadDir . $imageName
        );

        $updateQ .= ", image='$imageName'";
    }

    $updateQ .= " WHERE id=$id";

    mysqli_query($conn, $updateQ);

    if ($old_data && $old_data['status'] !== $status) {

        mysqli_query(
            $conn, 
            "INSERT INTO pet_history
             (pet_id, action_desc)
             VALUES
             ($id, 'Status updated to: $status')"
        );

    } else {

        mysqli_query(
            $conn, 
            "INSERT INTO pet_history
             (pet_id, action_desc)
             VALUES
             ($id, 'Profile information updated.')"
        );
    }

    header("Location: ?tab=manage-pets");
    exit;
}

/* =========================
   DELETE PET
========================= */
if (isset($_POST['delete_pet'])) {

    $pet_id = intval($_POST['pet_id']);

    /* Delete adoption applications first */
    mysqli_query(
        $conn, 
        "DELETE FROM adoption_applications
         WHERE pet_id = $pet_id"
    );

    /* Delete pet history */
    mysqli_query(
        $conn, 
        "DELETE FROM pet_history
         WHERE pet_id = $pet_id"
    );

    /* Delete pet */
    mysqli_query(
        $conn, 
        "DELETE FROM pets
         WHERE id = $pet_id"
    );

    header("Location: ?tab=manage-pets");
    exit;
}

/* =========================
   ADOPTION ACTION
========================= */
if (isset($_POST['action_adoption'])) {

    $adopt_id = intval($_POST['adoption_id']);
    $pet_id = intval($_POST['adopt_pet_id']);

    $new_status = mysqli_real_escape_string(
        $conn,
        $_POST['action_adoption']
    );

    mysqli_query(
        $conn, 
        "UPDATE adoption_applications
         SET status='$new_status'
         WHERE id=$adopt_id"
    );

    /* Notify the applicant about the decision. */
    $applicant_query = mysqli_query(
        $conn,
        "SELECT user_id FROM adoption_applications WHERE id=$adopt_id LIMIT 1"
    );
    if ($applicant_query && ($applicant = mysqli_fetch_assoc($applicant_query))) {
        $decision_message = ($new_status === 'Approved')
            ? 'Congratulations! Your adoption application has been approved.'
            : (($new_status === 'Rejected')
                ? 'Your adoption application was not approved. You may review other available pets.'
                : 'Your adoption application status is now ' . $new_status . '.');

        createAdminNotification(
            $conn,
            $applicant['user_id'],
            'Adoption application update',
            $decision_message,
            'Adoption'
        );
    }

    if ($new_status === 'Approved') {

        mysqli_query(
            $conn, 
            "UPDATE pets
             SET status='Adopted'
             WHERE id=$pet_id"
        );

        mysqli_query(
            $conn, 
            "INSERT INTO pet_history
             (pet_id, action_desc)
             VALUES
             (
                 $pet_id,
                 'Adoption application approved. Status changed to Adopted.'
             )"
        );
    }

    header("Location: ?tab=review-applications");
    exit;
}

/* =========================================================
   NEW FEATURE POST ACTIONS
========================================================= */

function createAdminNotification($conn, $user_id, $title, $message, $type = 'System') {
    $user_id = intval($user_id);
    $title = mysqli_real_escape_string($conn, $title);
    $message = mysqli_real_escape_string($conn, $message);
    $type = mysqli_real_escape_string($conn, $type);

    if ($user_id > 0) {
        mysqli_query(
            $conn,
            "INSERT INTO notifications (user_id, title, message, type)
             VALUES ($user_id, '$title', '$message', '$type')"
        );
    }
}

/* Rescue priority / assignment */
if (isset($_POST['update_rescue_priority'])) {
    $report_id = intval($_POST['priority_report_id']);
    $priority = mysqli_real_escape_string($conn, $_POST['rescue_priority']);
    $assigned_to = mysqli_real_escape_string($conn, $_POST['assigned_to']);

    mysqli_query(
        $conn,
        "UPDATE rescue_reports
         SET priority='$priority', assigned_to='$assigned_to'
         WHERE id=$report_id"
    );

    $rq = mysqli_query($conn, "SELECT user_id, location FROM rescue_reports WHERE id=$report_id LIMIT 1");
    if ($rq && ($rr = mysqli_fetch_assoc($rq))) {
        if (!empty($rr['user_id'])) {
            createAdminNotification(
                $conn,
                $rr['user_id'],
                'Rescue report updated',
                'Your rescue report at ' . ($rr['location'] ?? 'the reported location') . ' is now marked as ' . $priority . ' priority.',
                'Rescue'
            );
        }
    }

    header("Location: ?tab=rescue-reports");
    exit;
}

/* Pet medical record */
if (isset($_POST['add_medical_record'])) {
    $pet_id = intval($_POST['medical_pet_id']);
    $record_type = mysqli_real_escape_string($conn, $_POST['record_type']);
    $diagnosis = mysqli_real_escape_string($conn, $_POST['diagnosis']);
    $treatment = mysqli_real_escape_string($conn, $_POST['treatment']);
    $medication = mysqli_real_escape_string($conn, $_POST['medication']);
    $veterinarian = mysqli_real_escape_string($conn, $_POST['veterinarian']);
    $record_date = !empty($_POST['record_date']) ? mysqli_real_escape_string($conn, $_POST['record_date']) : null;
    $next_checkup = !empty($_POST['next_checkup']) ? mysqli_real_escape_string($conn, $_POST['next_checkup']) : null;
    $notes = mysqli_real_escape_string($conn, $_POST['medical_notes']);

    $record_date_sql = $record_date ? "'$record_date'" : "NULL";
    $next_checkup_sql = $next_checkup ? "'$next_checkup'" : "NULL";

    mysqli_query(
        $conn,
        "INSERT INTO pet_medical_records
         (pet_id, record_type, diagnosis, treatment, medication, veterinarian, record_date, next_checkup, notes)
         VALUES
         ($pet_id, '$record_type', '$diagnosis', '$treatment', '$medication', '$veterinarian', $record_date_sql, $next_checkup_sql, '$notes')"
    );

    mysqli_query(
        $conn,
        "INSERT INTO pet_history (pet_id, action_desc)
         VALUES ($pet_id, 'Medical record added: $record_type.')"
    );

    header("Location: ?tab=medical-records");
    exit;
}

if (isset($_POST['delete_medical_record'])) {
    $record_id = intval($_POST['medical_record_id']);
    mysqli_query($conn, "DELETE FROM pet_medical_records WHERE id=$record_id");
    header("Location: ?tab=medical-records");
    exit;
}

/* Adoption appointment status update (from clients) */
if (isset($_POST['update_appointment'])) {
    $appointment_id = intval($_POST['appointment_id']);
    $appointment_status = mysqli_real_escape_string($conn, $_POST['appointment_status']);

    mysqli_query(
        $conn,
        "UPDATE adoption_appointments
         SET status='$appointment_status'
         WHERE id=$appointment_id"
    );

    $aq = mysqli_query($conn, "SELECT user_id, appointment_date, appointment_time FROM adoption_appointments WHERE id=$appointment_id LIMIT 1");
    if ($aq && ($aa = mysqli_fetch_assoc($aq))) {
        createAdminNotification(
            $conn,
            $aa['user_id'],
            'Appointment status updated',
            'Your adoption appointment on ' . $aa['appointment_date'] . ' at ' . $aa['appointment_time'] . ' is now ' . $appointment_status . '.',
            'Appointment'
        );
    }

    header("Location: ?tab=appointments");
    exit;
}

/* Foster care status update */
if (isset($_POST['update_foster'])) {
    $foster_id = intval($_POST['foster_id']);
    $foster_status = mysqli_real_escape_string($conn, $_POST['foster_status']);

    mysqli_query(
        $conn,
        "UPDATE foster_applications
         SET status='$foster_status'
         WHERE id=$foster_id"
    );

    $fq = mysqli_query($conn, "SELECT user_id, pet_id FROM foster_applications WHERE id=$foster_id LIMIT 1");
    if ($fq && ($ff = mysqli_fetch_assoc($fq))) {
        createAdminNotification(
            $conn,
            $ff['user_id'],
            'Foster application updated',
            'Your foster care application has been updated to: ' . $foster_status . '.',
            'Foster'
        );
    }

    header("Location: ?tab=foster-care");
    exit;
}

/* =========================================================
   END NEW FEATURE POST ACTIONS
========================================================= */

/* =========================
   FETCH STATS
========================= */
function getStatCount($conn, $query) {

    $res = mysqli_query($conn, $query);

    if ($res) {
        $row = mysqli_fetch_assoc($res);
        return (int)$row['count'];
    }

    return 0;
}

$total_pets = getStatCount(
    $conn,
    "SELECT COUNT(*) as count FROM pets"
);

$available_pets = getStatCount(
    $conn,
    "SELECT COUNT(*) as count FROM pets WHERE status='Available'"
);

$pending_apps = getStatCount(
    $conn,
    "SELECT COUNT(*) as count FROM adoption_applications WHERE status='Pending'"
);

$pending_rescues = getStatCount(
    $conn,
    "SELECT COUNT(*) as count FROM rescue_reports WHERE status='Action Needed'"
);

$total_users = getStatCount(
    $conn,
    "SELECT COUNT(*) as count FROM users"
);

$pending_appointments = getStatCount(
    $conn,
    "SELECT COUNT(*) as count FROM adoption_appointments WHERE status='Pending'"
);

$active_fosters = getStatCount(
    $conn,
    "SELECT COUNT(*) as count FROM foster_applications WHERE status='Approved' OR status='Active'"
);

$medical_records_count = getStatCount(
    $conn,
    "SELECT COUNT(*) as count FROM pet_medical_records"
);

/* =========================
   RECENT USERS
========================= */
$recent_users_q = mysqli_query(
    $conn,
    "SELECT * FROM users ORDER BY id DESC LIMIT 4"
);

/* =========================
   PET HISTORY
========================= */
$histories = [];

$hq = mysqli_query(
    $conn,
    "SELECT * FROM pet_history ORDER BY id DESC"
);

if ($hq) {

    while ($h = mysqli_fetch_assoc($hq)) {

        $h_date = isset($h['date_logged'])
            ? date('M j, Y - g:i A', strtotime($h['date_logged']))
            : 'Recent';

        $histories[$h['pet_id']][] = [
            'desc' => $h['action_desc'],
            'date' => $h_date
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - San Pedro Animal Shelter</title>

    <!-- External Resources (Client Theme) -->
    <link 
        href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&display=swap" 
        rel="stylesheet"
    >
    <link 
        rel="stylesheet" 
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >

    <!-- PRE-LOAD THEME SCRIPT TO PREVENT FLASH -->
    <script>
        const storedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', storedTheme);
    </script>

<style>
    /* ================= GLOBAL LIGHT/DARK THEME VARIABLES ================= */
    :root {
        --primary: #F59E0B;
        --primary-hover: #D97706; 
        --primary-light: #FEF3C7;
        
        --secondary: #3B82F6;
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
        
        --badge-gray-bg: #F1F5F9;
        --badge-gray-text: #475569;
        
        --badge-blue-bg: #DBEAFE;
        --badge-blue-text: #1D4ED8;
        
        --border-color: #E2E8F0;
        
        --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 
                  0 2px 4px -1px rgba(0, 0, 0, 0.03);
        --shadow-lg: 0 10px 25px -3px rgba(0, 0, 0, 0.05), 
                     0 4px 6px -2px rgba(0, 0, 0, 0.025);
        --shadow-hover: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 
                        0 10px 10px -5px rgba(0, 0, 0, 0.02);
        
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
        
        --badge-gray-bg: rgba(148, 163, 184, 0.1);
        --badge-gray-text: #CBD5E1;
        
        --badge-blue-bg: rgba(59, 130, 246, 0.2);
        --badge-blue-text: #60A5FA;

        --border-color: #334155; 
        
        --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.3);
        --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3), 
                  0 2px 4px -1px rgba(0, 0, 0, 0.2);
        --shadow-lg: 0 10px 25px -3px rgba(0, 0, 0, 0.4);
        --shadow-hover: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
    }

    body { 
        margin: 0; 
        font-family: 'Nunito', sans-serif; 
        background: var(--bg-main); 
        color: var(--text-dark); 
        scroll-behavior: smooth; 
        overflow-x: hidden; 
        -webkit-font-smoothing: antialiased; 
        transition: background 0.3s ease, color 0.3s ease; 
    }

    a { 
        text-decoration: none; 
        transition: 0.2s ease; 
    }

    section { 
        padding: 50px 0; 
        min-height: calc(100vh - 150px); 
    }

    .container { 
        width: 92%; 
        max-width: 1400px; 
        margin: auto; 
    }

    .text-center { 
        text-align: center; 
    }

    .section-title { 
        font-size: clamp(1.8rem, 3vw, 2.2rem); 
        font-weight: 800; 
        color: var(--text-dark); 
        margin-bottom: 10px; 
    }

    .section-subtitle { 
        color: var(--text-muted); 
        font-size: 1.05rem; 
        margin-bottom: 30px; 
        line-height: 1.6; 
    }

    /* ================= UTILITY CLASSES ================= */
    .bg-white { 
        background-color: var(--bg-surface); 
    }

    .bg-light { 
        background-color: var(--bg-main); 
    }

    .badge { 
        display: inline-flex; 
        align-items: center; 
        padding: 4px 10px; 
        border-radius: var(--radius-full); 
        font-size: 0.75rem; 
        font-weight: 800; 
        text-transform: uppercase; 
        letter-spacing: 0.5px; 
    }

    .badge-success { 
        background: var(--badge-success-bg); 
        color: var(--badge-success-text); 
    }

    .badge-pending { 
        background: var(--badge-pending-bg); 
        color: var(--badge-pending-text); 
    }

    .badge-danger { 
        background: var(--badge-danger-bg); 
        color: var(--badge-danger-text); 
    }

    .badge-gray { 
        background: var(--badge-gray-bg); 
        color: var(--badge-gray-text); 
    }

    .badge-primary { 
        background: var(--badge-blue-bg); 
        color: var(--badge-blue-text); 
    }

    /* ================= BUTTONS ================= */
    .btn { 
        display: inline-flex; 
        align-items: center; 
        justify-content: center; 
        gap: 8px; 
        font-family: inherit; 
        font-weight: 700; 
        border: none; 
        cursor: pointer; 
        transition: all 0.25s ease; 
        padding: 12px 24px; 
        border-radius: var(--radius-full); 
        font-size: 0.95rem; 
    }

    .btn-primary { 
        background: var(--primary); 
        color: #fff; 
        box-shadow: 0 4px 10px rgba(245, 158, 11, 0.2); 
    }

    .btn-primary:hover { 
        background: var(--primary-hover); 
        transform: translateY(-2px); 
        box-shadow: 0 6px 15px rgba(245, 158, 11, 0.3); 
    }

    .btn-secondary { 
        background: var(--secondary); 
        color: #fff; 
        box-shadow: 0 4px 10px rgba(59, 130, 246, 0.2); 
    }

    .btn-secondary:hover { 
        background: var(--secondary-hover); 
        transform: translateY(-2px); 
        box-shadow: 0 6px 15px rgba(59, 130, 246, 0.3); 
    }

    .btn-danger { 
        background: var(--danger); 
        color: #fff; 
        box-shadow: 0 4px 10px rgba(239, 68, 68, 0.2); 
    }

    .btn-danger:hover { 
        background: #DC2626; 
        transform: translateY(-2px); 
    }

    .btn-success { 
        background: var(--success); 
        color: #fff; 
        box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2); 
    }

    .btn-success:hover { 
        background: #059669; 
        transform: translateY(-2px); 
    }

    .btn-outline { 
        background: transparent; 
        border: 2px solid var(--border-color); 
        color: var(--text-dark); 
    }

    .btn-outline:hover { 
        border-color: var(--primary); 
        color: var(--primary); 
        background: var(--primary-light); 
    }

    /* ================= FORMS & INPUTS ================= */
    input, 
    select, 
    textarea { 
        width: 100%; 
        padding: 14px 18px; 
        margin: 8px 0 20px; 
        border-radius: var(--radius-md); 
        border: 1px solid var(--border-color); 
        font-family: inherit; 
        font-size: 0.95rem; 
        background-color: var(--bg-main); 
        color: var(--text-dark); 
        box-sizing: border-box; 
        transition: all 0.2s ease; 
        outline: none; 
    }

    input:focus, 
    select:focus, 
    textarea:focus { 
        border-color: var(--primary); 
        box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.15); 
        background-color: var(--bg-surface); 
    }

    label { 
        font-weight: 800; 
        font-size: 0.85rem; 
        color: var(--text-muted); 
        text-transform: uppercase; 
        letter-spacing: 0.5px; 
        display: block; 
        margin-bottom: -4px;
    }

    .form-group { 
        margin-bottom: 5px; 
    }

    .form-row { 
        display: grid; 
        grid-template-columns: 1fr 1fr; 
        gap: 15px; 
    }

    /* ================= NAVIGATION & HEADER ================= */
    .topbar { 
        background: #0F172A; 
        color: #F8FAFC; 
        text-align: center; 
        padding: 10px; 
        font-size: 13px; 
        font-weight: 600; 
        letter-spacing: 0.5px; 
    }

    .topbar span { 
        color: var(--primary); 
        margin-right: 5px; 
    }

    .header { 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        padding: 15px 40px; 
        background: var(--header-bg); 
        backdrop-filter: blur(12px); 
        -webkit-backdrop-filter: blur(12px); 
        box-shadow: var(--shadow-sm); 
        position: sticky; 
        top: 0; 
        z-index: 1000; 
        border-bottom: 1px solid rgba(0,0,0,0.02); 
        transition: background 0.3s; 
    }

    .logo { 
        font-size: 1.4rem; 
        font-weight: 900; 
        color: var(--text-dark); 
        display: flex; 
        align-items: center; 
        gap: 12px; 
        letter-spacing: -0.5px; 
    }

    .logo .logo-icon { 
        background: linear-gradient(135deg, var(--primary), var(--primary-hover)); 
        color: white; 
        width: 40px; 
        height: 40px; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        border-radius: var(--radius-md); 
        font-size: 18px; 
        box-shadow: 0 4px 10px rgba(245, 158, 11, 0.3); 
    }

    .nav-links { 
        display: flex; 
        align-items: center; 
        gap: 4px; 
        background: var(--bg-main); 
        padding: 4px; 
        border-radius: var(--radius-full); 
        border: 1px solid var(--border-color); 
    }

    .nav-link { 
        position: relative; 
        color: var(--text-muted); 
        font-weight: 700; 
        font-size: 0.9rem; 
        padding: 8px 18px; 
        border-radius: var(--radius-full); 
        transition: 0.3s; 
    }

    .nav-link:hover, 
    .nav-link.active { 
        color: var(--primary); 
        background: var(--bg-surface); 
        box-shadow: var(--shadow-sm); 
    }

    .notif-badge { 
        position: absolute; 
        top: -4px; 
        right: 0px; 
        background: var(--danger); 
        color: white; 
        font-size: 10px; 
        font-weight: 800; 
        min-width: 18px; 
        height: 18px; 
        border-radius: 50%; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        border: 2px solid var(--bg-surface); 
    }

    .nav-utils { 
        display: flex; 
        align-items: center; 
        gap: 15px; 
    }

    .icon-btn { 
        position: relative; 
        color: var(--text-muted); 
        font-size: 1.1rem; 
        width: 42px; 
        height: 42px; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        border-radius: 50%; 
        background: var(--bg-surface); 
        border: 1px solid var(--border-color); 
        transition: 0.3s; 
        cursor: pointer; 
    }

    .icon-btn:hover { 
        color: var(--primary); 
        border-color: var(--primary); 
        background: var(--primary-light); 
        transform: translateY(-2px); 
    }

    .icon-btn.logout-btn:hover { 
        color: var(--danger); 
        border-color: var(--danger); 
        background: var(--danger-light); 
    }

    .user-profile-btn { 
        display: flex; 
        align-items: center; 
        gap: 10px; 
        padding: 4px 16px 4px 4px; 
        background: var(--bg-main); 
        border: 1px solid var(--border-color); 
        border-radius: var(--radius-full); 
        color: var(--text-dark); 
        font-weight: 700; 
        font-size: 0.9rem; 
        transition: 0.3s; 
        cursor: pointer; 
    }

    .user-profile-btn:hover { 
        background: var(--bg-surface); 
        border-color: var(--primary); 
        box-shadow: var(--shadow-sm); 
    }

    .avatar-small { 
        width: 34px; 
        height: 34px; 
        background: var(--secondary); 
        color: white; 
        border-radius: 50%; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        font-weight: 800; 
        font-size: 0.8rem; 
    }

    /* Mobile Menu Setup */
    .mobile-toggle { 
        display: none; 
        background: none; 
        border: none; 
        font-size: 24px; 
        color: var(--text-dark); 
        cursor: pointer; 
        transition: 0.2s; 
    }

    .mobile-only-links { 
        display: none; 
    }

    @media (max-width: 1200px) {
        .nav-links, 
        .nav-utils { 
            display: none; 
        }

        .mobile-toggle { 
            display: block; 
        }

        .header { 
            padding: 15px 20px; 
        }
        
        .nav-links.active-menu {
            display: flex; 
            flex-direction: column; 
            position: absolute; 
            top: 100%; 
            left: 0; 
            width: 100%; 
            background: var(--bg-surface); 
            box-shadow: var(--shadow-lg); 
            padding: 20px 0; 
            border: none; 
            border-radius: 0; 
            z-index: 1000;
            animation: slideDown 0.3s ease;
        }

        .nav-links.active-menu .nav-link { 
            width: 90%; 
            text-align: left; 
            padding: 15px 25px; 
            margin: 2px auto; 
            background: var(--bg-main); 
            border-radius: var(--radius-md); 
            box-shadow: none;
        }

        .nav-links.active-menu .mobile-only-links { 
            display: flex; 
            flex-direction: column; 
            width: 100%; 
            border-top: 1px solid var(--border-color); 
            margin-top: 15px; 
            padding-top: 15px; 
        }
    }

    @keyframes slideDown { 
        from { opacity: 0; transform: translateY(-10px); } 
        to { opacity: 1; transform: translateY(0); } 
    }

    /* ================= MAIN CONTAINER SYSTEM ================= */
    .main-container { 
        display: none; 
    }

    .main-container.active { 
        display: block; 
        animation: fadeIn 0.4s ease forwards; 
    }

    @keyframes fadeIn { 
        from { opacity: 0; transform: translateY(15px); } 
        to { opacity: 1; transform: translateY(0); } 
    }

    /* ================= DASHBOARD & STATS ================= */
    .stat-cards { 
        display: grid; 
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); 
        gap: 25px; 
        margin-bottom: 40px; 
    }

    .stat-card { 
        background: var(--bg-surface); 
        padding: 30px; 
        border-radius: var(--radius-lg); 
        box-shadow: var(--shadow); 
        border: 1px solid var(--border-color); 
        transition: 0.3s; 
        display: flex; 
        flex-direction: column; 
        justify-content: center; 
    }

    .stat-card:hover { 
        transform: translateY(-5px); 
        box-shadow: var(--shadow-hover); 
        border-color: var(--primary-light); 
    }

    .stat-card div { 
        color: var(--text-muted); 
        font-size: 0.9rem; 
        font-weight: 800; 
        text-transform: uppercase; 
        letter-spacing: 0.5px;
    }

    .stat-card h2 { 
        font-size: 2.5rem; 
        font-weight: 900; 
        color: var(--text-dark); 
        margin: 10px 0 0 0; 
    }

    .form-container { 
        background: var(--bg-surface); 
        padding: clamp(25px, 4vw, 35px); 
        border-radius: var(--radius-lg); 
        box-shadow: var(--shadow); 
        border: 1px solid var(--border-color); 
        margin-bottom: 30px; 
    }

    .form-container h3 { 
        font-size: 1.4rem; 
        color: var(--text-dark); 
        margin-top: 0; 
        margin-bottom: 20px; 
        font-weight: 800; 
    }

    .grid-layout { 
        display: grid; 
        grid-template-columns: 2fr 1fr; 
        gap: 30px; 
    }

    @media(max-width:992px){ 
        .grid-layout { 
            grid-template-columns: 1fr; 
        } 
    }

    /* ================= PET CARDS / GENERAL CARDS ================= */
    .grid { 
        display: grid; 
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); 
        gap: 30px; 
        margin-top: 20px;
    }

    .pet-card { 
        background: var(--bg-surface); 
        border-radius: var(--radius-lg); 
        overflow: hidden; 
        box-shadow: var(--shadow); 
        display: flex; 
        flex-direction: column; 
        transition: all 0.3s ease; 
        position: relative; 
        border: 1px solid var(--border-color); 
        cursor: pointer;
    }

    .pet-card:hover { 
        transform: translateY(-8px); 
        box-shadow: var(--shadow-hover); 
        border-color: rgba(245, 158, 11, 0.3); 
    }

    .pet-card-img-wrap { 
        position: relative; 
        width: 100%; 
        height: 200px; 
        overflow: hidden; 
        background: var(--bg-main); 
    }

    .pet-card-img { 
        width: 100%; 
        height: 100%; 
        object-fit: cover; 
        transition: transform 0.5s ease; 
    }

    .pet-card:hover .pet-card-img { 
        transform: scale(1.05); 
    }

    .pet-card-content { 
        padding: 25px; 
        display: flex; 
        flex-direction: column; 
        flex-grow: 1; 
    }

    .pet-card-title { 
        margin: 0 0 5px; 
        font-size: 1.3rem; 
        font-weight: 900; 
        color: var(--text-dark); 
    }

    .pet-card-subtitle { 
        font-size: 0.9rem; 
        color: var(--text-muted); 
        font-weight: 700; 
        margin-bottom: 15px;
    }

    .pet-tags { 
        display: flex; 
        flex-wrap: wrap; 
        gap: 8px; 
        margin-bottom: 20px; 
    }

    .pet-tag { 
        display: inline-flex; 
        align-items: center; 
        gap: 6px; 
        padding: 6px 10px; 
        background: var(--bg-main); 
        border-radius: var(--radius-md); 
        font-size: 0.75rem; 
        font-weight: 800; 
        color: var(--text-muted); 
        border: 1px solid var(--border-color); 
    }

    .pet-card-status { 
        position: absolute; 
        top: 15px; 
        right: 15px; 
        z-index: 10; 
        box-shadow: var(--shadow-sm);
    }

    /* ================= TABLES ================= */
    .history-table { 
        width: 100%; 
        border-collapse: separate; 
        border-spacing: 0; 
        border-radius: var(--radius-md); 
        overflow: hidden; 
        border: 1px solid var(--border-color); 
        background: var(--bg-surface); 
    }

    .history-table th, 
    .history-table td { 
        padding: 16px 20px; 
        text-align: left; 
        font-size: 0.95rem; 
    }

    .history-table th { 
        background: var(--bg-main); 
        font-weight: 800; 
        color: var(--text-muted); 
        text-transform: uppercase; 
        font-size: 0.8rem; 
        letter-spacing: 0.5px; 
        border-bottom: 1px solid var(--border-color); 
    }

    .history-table td { 
        background: var(--bg-surface); 
        border-bottom: 1px solid var(--border-color); 
        color: var(--text-dark); 
        font-weight: 600; 
    }

    .history-table tr:last-child td { 
        border-bottom: none; 
    }

    /* ================= TABS ================= */
    .profile-tabs { 
        display: flex; 
        gap: 10px; 
        margin-bottom: 25px; 
        background: var(--bg-main); 
        padding: 5px; 
        border-radius: var(--radius-full); 
        border: 1px solid var(--border-color); 
        display: inline-flex;
    }

    .ptab { 
        padding: 10px 25px; 
        font-weight: 800; 
        font-size: 0.9rem; 
        color: var(--text-muted); 
        cursor: pointer; 
        border-radius: var(--radius-full); 
        transition: 0.3s; 
    }

    .ptab.active { 
        background: var(--bg-surface); 
        color: var(--primary); 
        box-shadow: var(--shadow-sm); 
    }

    /* ================= TIMELINE ================= */
    .timeline { 
        border-left: 2px dashed var(--border-color); 
        padding-left: 20px; 
        margin-top: 10px; 
        display: flex; 
        flex-direction: column; 
        gap: 20px; 
    }

    .timeline-item { 
        position: relative; 
    }

    .timeline-item::before { 
        content: ''; 
        position: absolute; 
        left: -27px; 
        top: 2px; 
        width: 12px; 
        height: 12px; 
        background: var(--primary); 
        border-radius: 50%; 
        border: 3px solid var(--bg-surface); 
    }

    .timeline-date { 
        font-size: 0.8rem; 
        font-weight: 800; 
        color: var(--text-muted); 
        margin-bottom: 4px; 
    }

    .timeline-desc { 
        font-size: 0.95rem; 
        font-weight: 600; 
        color: var(--text-dark); 
    }

    /* ================= MODAL STYLES (Client Theme) ================= */
    .sf-modal-overlay { 
        display: none; 
        position: fixed; 
        z-index: 9999; 
        left: 0; 
        top: 0; 
        width: 100%; 
        height: 100%; 
        background-color: rgba(15, 23, 42, 0.8); 
        backdrop-filter: blur(5px); 
        overflow-y: auto; 
        padding: 40px 0; 
        opacity: 0; 
        transition: opacity 0.3s ease; 
    }

    .sf-modal-overlay.show { 
        opacity: 1; 
    }

    .sf-modal-container { 
        background: var(--bg-surface); 
        width: clamp(300px, 92%, 900px); 
        margin: auto; 
        border-radius: var(--radius-lg); 
        position: relative; 
        box-shadow: 0 30px 60px rgba(0,0,0,0.5); 
        transform: scale(0.95) translateY(20px); 
        opacity: 0; 
        transition: all 0.3s ease; 
        overflow: hidden; 
        border: 1px solid var(--border-color); 
        display: flex; 
        flex-direction: column; 
        max-height: 90vh;
    }

    .sf-modal-overlay.show .sf-modal-container { 
        transform: scale(1) translateY(0); 
        opacity: 1; 
    }

    .sf-modal-close { 
        position: absolute; 
        right: 25px; 
        top: 25px; 
        font-size: 24px; 
        color: var(--text-muted); 
        cursor: pointer; 
        z-index: 10; 
        transition: 0.3s; 
        background: var(--bg-main); 
        width: 45px; 
        height: 45px; 
        border-radius: 50%; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        border: 1px solid var(--border-color); 
    }

    .sf-modal-close:hover { 
        color: var(--danger); 
        background: var(--danger-light); 
        border-color: #FECACA; 
        transform: rotate(90deg); 
    }

    .modal-header { 
        padding: 25px 35px; 
        border-bottom: 1px solid var(--border-color); 
        background: var(--bg-surface); 
        position: sticky; 
        top: 0; 
        z-index: 5;
    }

    .modal-body { 
        padding: 35px; 
        overflow-y: auto;
    }

.admin-insights{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:22px;margin:0 0 30px}.admin-panel{background:var(--bg-surface);border:1px solid var(--border-color);border-radius:20px;padding:25px;box-shadow:var(--shadow)}.admin-panel h3{margin:0 0 18px;font-size:1.15rem}.insight-row{display:grid;grid-template-columns:125px 1fr 48px;align-items:center;gap:12px;margin:14px 0;font-size:.85rem;font-weight:800}.insight-track{height:12px;border-radius:20px;background:var(--bg-main);overflow:hidden}.insight-fill{height:100%;border-radius:20px;background:linear-gradient(90deg,#f59e0b,#f97316);transition:width .6s}.insight-row:nth-of-type(2) .insight-fill{background:linear-gradient(90deg,#3b82f6,#60a5fa)}.insight-row:nth-of-type(3) .insight-fill{background:linear-gradient(90deg,#10b981,#34d399)}.admin-quicklinks{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.admin-quicklinks a{display:flex;gap:10px;align-items:center;padding:14px;border:1px solid var(--border-color);border-radius:12px;background:var(--bg-main);color:var(--text-dark);font-weight:800}.admin-quicklinks a:hover{border-color:var(--primary);transform:translateY(-2px)}.admin-quicklinks i{color:var(--primary)}.admin-tools{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin:0 0 20px;padding:15px 18px;background:var(--bg-surface);border:1px solid var(--border-color);border-radius:14px}.admin-tools input{max-width:360px;margin:0}.admin-tools button{padding:12px 16px;background:var(--bg-main);color:var(--text-dark);border:1px solid var(--border-color);border-radius:12px;cursor:pointer;font-weight:800}.admin-tools button:hover{border-color:var(--primary)}.admin-search-count{font-size:.85rem;color:var(--text-muted);font-weight:800}.admin-empty-search{padding:20px;color:var(--text-muted);text-align:center}.stat-card{position:relative;overflow:hidden}.stat-card:after{content:'';position:absolute;right:-28px;top:-28px;width:105px;height:105px;border-radius:50%;background:var(--primary-light);opacity:.6}.stat-card h2{position:relative;z-index:1}.admin-section-head{display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap}.admin-section-head .btn{white-space:nowrap}@media(max-width:850px){.admin-insights{grid-template-columns:1fr}.admin-quicklinks{grid-template-columns:1fr 1fr}.admin-tools input{max-width:100%}}@media(max-width:480px){.admin-quicklinks{grid-template-columns:1fr}.insight-row{grid-template-columns:95px 1fr 32px}.admin-panel{padding:18px}}

.application-identity{display:flex;gap:15px;align-items:center;margin-bottom:20px;padding-bottom:20px;border-bottom:1px dashed var(--border-color)}
.application-avatar,.application-pet-photo{position:relative;flex-shrink:0;display:flex;align-items:center;justify-content:center;overflow:hidden;width:68px;height:68px;border-radius:18px;background:linear-gradient(145deg,#fef3c7,#fde68a);color:#b45309;border:1px solid rgba(245,158,11,.25)}
.application-avatar{border-radius:50%;background:linear-gradient(135deg,#dbeafe,#c7d2fe);color:#4338ca}
.application-avatar img,.application-pet-photo img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:2}
.application-avatar-fallback,.application-pet-fallback{font-size:25px}
.application-initial{position:absolute;font-weight:900;font-size:25px;z-index:1}
.application-avatar:has(.application-initial) .application-avatar-fallback{display:none}
.application-identity-details{flex:1;min-width:0}
.application-identity-details h4{font-size:1.1rem;font-weight:800;margin:0 0 5px;color:var(--text-dark);overflow-wrap:anywhere}
.application-identity-details p{font-size:.85rem;color:var(--text-muted);margin:0}
.application-identity-details strong{color:var(--primary)}
.application-pet-photo{width:62px;height:62px;border-radius:14px}
@media(max-width:420px){.application-identity{gap:10px}.application-avatar{width:54px;height:54px}.application-pet-photo{width:50px;height:50px}}

.admin-nav-avatar{overflow:hidden;flex-shrink:0}.admin-nav-avatar img{width:100%;height:100%;object-fit:cover}
.admin-nav-name{max-width:130px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.admin-profile-overlay{display:none;position:fixed;inset:0;background:rgba(2,6,23,.72);z-index:9999;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(5px)}
.admin-profile-overlay.is-open{display:flex}
.admin-profile-panel{width:min(100%,510px);max-height:90vh;overflow:auto;background:var(--bg-surface);color:var(--text-dark);border:1px solid var(--border-color);border-radius:22px;box-shadow:0 22px 65px rgba(0,0,0,.35);padding:26px;position:relative}
.admin-profile-header{display:flex;align-items:center;gap:18px;padding-bottom:20px;border-bottom:1px solid var(--border-color);margin-bottom:20px}
.admin-profile-photo{width:82px;height:82px;flex-shrink:0;border-radius:50%;background:linear-gradient(135deg,#f59e0b,#d97706);display:flex;align-items:center;justify-content:center;color:white;font-size:32px;font-weight:900;overflow:hidden}
.admin-profile-photo img{width:100%;height:100%;object-fit:cover}
.admin-profile-header h2{margin:0 0 5px;font-size:1.35rem;overflow-wrap:anywhere}.admin-profile-header p{margin:0;color:var(--text-muted);overflow-wrap:anywhere;font-size:.85rem}
.admin-profile-close{position:absolute;right:14px;top:12px;background:transparent;border:0;color:var(--text-muted);font-size:24px;cursor:pointer}
.admin-profile-panel label{margin:0 0 6px}.admin-profile-panel input,.admin-profile-panel textarea{margin:0 0 16px}.admin-profile-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center}.admin-profile-actions .btn{flex:1}.admin-profile-hint{font-size:.8rem;color:var(--text-muted);margin:-7px 0 16px}.admin-profile-notice{padding:12px 16px;border-radius:12px;margin:12px auto;max-width:1000px;background:var(--badge-danger-bg);color:var(--badge-danger-text)}
@media(max-width:520px){.admin-profile-panel{padding:22px 16px}.admin-profile-header{gap:12px}.admin-profile-photo{width:64px;height:64px}}

.shelter-map-layout{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(250px,.6fr);gap:16px;margin:18px 0}.shelter-admin-map{height:380px;border-radius:16px;overflow:hidden;border:1px solid var(--border-color);z-index:1}.shelter-map-help{padding:18px;border-radius:16px;background:var(--bg-main);color:var(--text-muted);line-height:1.6}.shelter-map-help button{margin-top:10px}.shelter-schedule{border:1px solid var(--border-color);border-radius:15px;padding:16px;margin-top:16px}.shelter-schedule h3{margin:0 0 6px}.shelter-day{display:grid;grid-template-columns:minmax(110px,1fr) minmax(110px,1fr) minmax(110px,1fr);gap:10px;align-items:center;padding:8px 0;border-bottom:1px solid var(--border-color)}.shelter-day:last-child{border:0}.shelter-day label{display:flex;gap:7px;align-items:center;font-weight:700}.shelter-day input[type=time]{width:100%;box-sizing:border-box;border:1px solid var(--border-color);border-radius:9px;background:var(--bg-surface);color:var(--text-dark);padding:10px}.shelter-day input[type=checkbox]{width:auto}.shelter-hours-badge{display:inline-flex;padding:5px 10px;border-radius:999px;font-size:.78rem;font-weight:800;background:var(--badge-pending-bg);color:var(--badge-pending-text)}.shelter-hours-badge.open{background:var(--badge-success-bg);color:var(--badge-success-text)}.shelter-hours-badge.closed{background:var(--badge-danger-bg);color:var(--badge-danger-text)}@media(max-width:800px){.shelter-map-layout{grid-template-columns:1fr}.shelter-day{grid-template-columns:1fr 1fr}.shelter-day label{grid-column:1/-1}}
</style>
<style>
.pet-card-img-wrap:has(.rescue-photo-placeholder){position:relative;background:linear-gradient(135deg,#172b45,#263e56);overflow:hidden}
.rescue-photo-placeholder{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;color:#cbd5e1;text-align:center;padding:20px;box-sizing:border-box}
.rescue-photo-placeholder i{font-size:48px;color:#f59e0b;opacity:.9}
.rescue-photo-placeholder span{font-size:13px;font-weight:800;letter-spacing:.02em}
.rescue-report-photo{position:relative;z-index:1;display:block;width:100%;height:100%;object-fit:cover}
</style>
<style>
.shelter-admin-panel{background:var(--bg-surface);border:1px solid var(--border-color);border-radius:20px;padding:24px;box-shadow:var(--shadow)}
.shelter-admin-panel h2{margin:0 0 20px}.shelter-form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px}
.shelter-form-grid label{display:flex;flex-direction:column;gap:7px;font-weight:700;font-size:.88rem}.shelter-form-grid input,.shelter-form-grid textarea,.shelter-form-grid select,.shelter-admin-actions select{box-sizing:border-box;width:100%;padding:12px;border:1px solid var(--border-color);border-radius:10px;background:var(--bg-main);color:var(--text-dark);font:inherit}
.shelter-form-grid .shelter-wide{grid-column:1/-1}.shelter-form-grid .shelter-check{flex-direction:row;align-items:center}.shelter-check input{width:auto}.shelter-notice{padding:14px;background:var(--primary-light);border-radius:12px;margin:16px 0}.shelter-table-scroll{overflow-x:auto}.shelter-admin-table{width:100%;border-collapse:collapse;min-width:900px}.shelter-admin-table th,.shelter-admin-table td{padding:12px;text-align:left;border-bottom:1px solid var(--border-color);vertical-align:top}.shelter-admin-actions{display:flex;gap:7px;align-items:center;flex-wrap:wrap}.shelter-admin-actions form{display:flex;gap:5px;align-items:center}.shelter-admin-actions .btn{padding:8px 12px;font-size:.8rem}
</style>

<style>
/* Shelter registration form: compact, aligned like the AI matching questionnaire */
#admin-shelters .shelter-admin-panel{max-width:1260px;margin-left:auto;margin-right:auto;padding:clamp(18px,3vw,34px);border-radius:22px}
#admin-shelters .shelter-form-grid{grid-template-columns:repeat(4,minmax(0,1fr));column-gap:18px;row-gap:23px;align-items:end}
#admin-shelters .shelter-form-grid label{min-width:0;gap:9px;font-size:13px;font-weight:800;letter-spacing:.025em;text-transform:uppercase;color:var(--text-muted)}
#admin-shelters .shelter-form-grid input:not([type=checkbox]),#admin-shelters .shelter-form-grid select{min-height:52px;padding:13px 16px;border-radius:13px;background:var(--bg-main);border:1px solid var(--border-color);font-size:14px;font-weight:700;color:var(--text-dark);letter-spacing:normal;text-transform:none}
#admin-shelters .shelter-form-grid input:focus,#admin-shelters .shelter-form-grid select:focus{outline:2px solid var(--primary);outline-offset:1px}
#admin-shelters .shelter-form-grid .shelter-check{align-self:center;padding-top:25px;gap:12px;line-height:1.5}
#admin-shelters .shelter-form-grid .shelter-check input{width:18px;height:18px;accent-color:var(--primary);flex-shrink:0}
#admin-shelters .shelter-map-layout{grid-template-columns:minmax(0,1.5fr) minmax(270px,.65fr);gap:20px;margin:30px 0}
#admin-shelters .shelter-map-help{border:1px solid var(--border-color);padding:22px;border-radius:18px}
#admin-shelters .shelter-place-result{margin-top:20px;padding:16px;background:var(--bg-surface);border:1px solid var(--border-color);border-radius:14px;display:flex;flex-direction:column;gap:7px;overflow-wrap:anywhere}
#admin-shelters .shelter-place-caption{font-size:12px;text-transform:uppercase;font-weight:800;letter-spacing:.06em;color:var(--text-muted)}
#adminShelterPlaceName{font-size:17px;color:var(--text-dark);line-height:1.45}
#adminShelterMapMessage{font-size:12px;color:var(--text-muted);line-height:1.5}
@media(max-width:1100px){#admin-shelters .shelter-form-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:800px){#admin-shelters .shelter-form-grid{grid-template-columns:repeat(2,minmax(0,1fr))}#admin-shelters .shelter-map-layout{grid-template-columns:1fr}}
@media(max-width:520px){#admin-shelters .shelter-form-grid{grid-template-columns:1fr}#admin-shelters .shelter-admin-panel{padding:16px}}
</style>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" defer></script>
</head>

<body>

<div class="topbar">
    <span>
        <i class="fas fa-shield-alt"></i>
    </span> 
    San Pedro Animal Shelter - Administration Portal
</div>

<header class="header">

    <div class="logo">
        <div class="logo-icon">
            <i class="fas fa-paw"></i>
        </div> 
        Admin Panel
    </div>

    <button class="mobile-toggle" onclick="toggleMenu()">
        <i class="fas fa-bars"></i>
    </button>

    <nav class="nav-links" id="navLinks">
        <a href="?tab=shelters" class="nav-link <?php echo $activeTab === 'shelters' ? 'active' : ''; ?>"><i class="fas fa-map-marker-alt"></i> Shelters</a>
        

        <a 
            href="?tab=dashboard" 
            class="nav-link <?php echo $activeTab == 'dashboard' ? 'active' : ''; ?>"
        >
            Dashboard
        </a>

        <a 
            href="?tab=rescue-reports" 
            class="nav-link <?php echo $activeTab == 'rescue-reports' ? 'active' : ''; ?>"
        >
            Reports 
            <?php if($pending_rescues > 0): ?>
                <span class="notif-badge">
                    <?php echo $pending_rescues; ?>
                </span>
            <?php endif; ?>
        </a>

        <a 
            href="?tab=manage-pets" 
            class="nav-link <?php echo $activeTab == 'manage-pets' ? 'active' : ''; ?>"
        >
            Manage Pets
        </a>

        <a 
            href="?tab=review-applications" 
            class="nav-link <?php echo $activeTab == 'review-applications' ? 'active' : ''; ?>"
        >
            Applications 
            <?php if($pending_apps > 0): ?>
                <span class="notif-badge">
                    <?php echo $pending_apps; ?>
                </span>
            <?php endif; ?>
        </a>

        <a
            href="?tab=medical-records"
            class="nav-link <?php echo $activeTab == 'medical-records' ? 'active' : ''; ?>"
        >
            Health
        </a>

        <a
            href="?tab=appointments"
            class="nav-link <?php echo $activeTab == 'appointments' ? 'active' : ''; ?>"
        >
            Appointments
            <?php if($pending_appointments > 0): ?>
                <span class="notif-badge">
                    <?php echo $pending_appointments; ?>
                </span>
            <?php endif; ?>
        </a>

        <a
            href="?tab=foster-care"
            class="nav-link <?php echo $activeTab == 'foster-care' ? 'active' : ''; ?>"
        >
            Foster
        </a>

        <a 
            href="?tab=community-feed" 
            class="nav-link <?php echo $activeTab == 'community-feed' ? 'active' : ''; ?>"
        >
            Feed
        </a>

        <a 
            href="?tab=manage-users" 
            class="nav-link <?php echo $activeTab == 'manage-users' ? 'active' : ''; ?>"
        >
            Users
        </a>

        <!-- Mobile Only Links -->
        <div class="mobile-only-links">
            <button type="button" class="nav-link" style="border:none;cursor:pointer;text-align:left" onclick="openAdminProfile()"><i class="fas fa-user-circle"></i> My Admin Profile</button>
            <button 
                id="theme-toggle-mobile" 
                class="nav-link" 
                style="text-align:left; border:none; background:transparent; width:100%; cursor:pointer;"
            >
                <i class="fas fa-moon"></i> Toggle Dark Mode
            </button>
            <a 
                href="#" 
                class="nav-link" 
                onclick="openStaffModal()"
            >
                <i class="fas fa-user-md"></i> Staff / Vets
            </a>
            <a href="?tab=medical-records" class="nav-link">
                <i class="fas fa-notes-medical"></i> Health Records
            </a>
            <a href="?tab=appointments" class="nav-link">
                <i class="fas fa-calendar-check"></i> Appointments
            </a>
            <a href="?tab=foster-care" class="nav-link">
                <i class="fas fa-home"></i> Foster Care
            </a>
            <a href="admin_donations.php" class="nav-link">
    <i class="fas fa-hand-holding-heart"></i> Donation Management
</a>
            <a 
                href="?action=logout" 
                class="nav-link" 
                style="color: var(--danger);"
            >
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>

    </nav>

    <div class="nav-utils">

        <button 
            id="theme-toggle" 
            class="icon-btn" 
            title="Toggle Dark/Light Mode"
        >
            <i class="fas fa-moon"></i>
        </button>

        <button type="button" class="user-profile-btn" title="View your admin profile" onclick="openAdminProfile()" aria-label="Open admin profile">
            <span class="avatar-small admin-nav-avatar">
                <?php if ($adminPhoto): ?><img src="<?php echo $adminEscape($adminPhoto); ?>" alt="" onerror="this.style.display='none';this.nextElementSibling.style.display='inline'"/><span style="display:none"><?php echo $adminEscape($adminInitials); ?></span><?php else: ?><?php echo $adminEscape($adminInitials); ?><?php endif; ?>
            </span>
            <span class="admin-nav-name"><?php echo $adminEscape($adminDisplayName); ?></span>
            <i class="fas fa-chevron-down" aria-hidden="true"></i>
        </button>

        <a 
            href="?action=logout" 
            class="icon-btn logout-btn" 
            title="Logout"
        >
            <i class="fas fa-sign-out-alt"></i>
        </a>

    </div>

</header>



<section class="main-container <?php echo $activeTab === 'shelters' ? 'active' : ''; ?>" id="admin-shelters">
 <div class="container">
  <h1 style="font-size:2rem;font-weight:900;margin-bottom:8px"><i class="fas fa-house-medical"></i> Shelter Management</h1>
  <p style="color:var(--text-muted)">Add shelters across the Philippines. Only Approved shelters with valid GPS coordinates appear on the client map.</p>
  <?php if ($shelterAdminNotice !== ''): ?><div class="shelter-notice"><?php echo $adminEscape($shelterAdminNotice); ?></div><?php endif; ?>
  <div class="shelter-admin-panel">
   <h2 id="shelter-form-title">Add Animal Shelter</h2>
   <form method="post" action="?tab=shelters" id="shelter-admin-form">
    <input type="hidden" name="shelter_csrf" value="<?php echo $adminEscape($_SESSION['shelter_admin_csrf']); ?>">
    <input type="hidden" name="shelter_action" id="shelter_action" value="add">
    <input type="hidden" name="shelter_id" id="shelter_id" value="0">
    <div class="shelter-form-grid">
     <label>Shelter name *<input name="shelter_name" required maxlength="150"></label>
     <label>Street address *<input name="address" required maxlength="255"></label>
     <label>City / Municipality *<input name="city" required maxlength="100"></label>
     <label>Province *<input name="province" required maxlength="100"></label>
     <label>Latitude (GPS)<input name="latitude" type="number" step="any" min="-90" max="90" placeholder="14.5995"></label>
     <label>Longitude (GPS)<input name="longitude" type="number" step="any" min="-180" max="180" placeholder="120.9842"></label>
     <label>Phone<input name="contact_number" maxlength="30"></label>
     <label>Email<input name="contact_email" type="email" maxlength="150"></label>
     <label>Animal types<input name="animal_types" maxlength="255" placeholder="Dogs, Cats"></label>
     <label>Capacity<input name="capacity" type="number" min="0" value="0" required></label>
     <label>Available slots<input name="available_slots" type="number" min="0" value="0" required></label>
     <label>Verification status<select name="verification_status"><option>Pending</option><option>Approved</option><option>Rejected</option><option>Suspended</option></select></label>
     <label class="shelter-check"><input type="checkbox" name="rescue_available" value="1"> Accepting rescue requests</label>
     <label class="shelter-wide">Shelter service / description *<select name="description" required><option value="">Choose shelter service</option><?php foreach (['Animal Rescue and Rehabilitation','Adoption and Rehoming','Temporary Animal Shelter','Community Animal Welfare','Veterinary and Recovery Support','Stray Animal Rescue','Wildlife Rescue and Rehabilitation','Animal Sanctuary'] as $service): ?><option value="<?php echo $adminEscape($service); ?>"><?php echo $adminEscape($service); ?></option><?php endforeach; ?></select></label>
    </div>
    <div class="shelter-map-layout"><div id="adminShelterMap" class="shelter-admin-map" aria-label="Map for selecting and monitoring shelter locations"></div><div class="shelter-map-help"><strong><i class="fas fa-map-location-dot"></i> Shelter Location Map</strong><p>Click on the map to set the GPS coordinates automatically. Approved shelters will appear as markers. Click a marker to inspect the shelter and its schedule.</p><button type="button" class="btn" id="adminShelterLocate"><i class="fas fa-location-crosshairs"></i> Use My Location</button><div class="shelter-place-result" aria-live="polite"><span class="shelter-place-caption"><i class="fas fa-location-dot"></i> Exact selected location</span><strong id="adminShelterPlaceName">Pumili ng lugar sa mapa</strong><small id="adminShelterMapMessage">I-click ang mapa para makita ang barangay, lungsod at probinsya.</small></div></div></div>
    <div class="shelter-schedule"><h3><i class="fas fa-clock"></i> Operating Days & Office Hours (Philippine Time)</h3><p style="color:var(--text-muted);margin-top:5px">Choose open days and set the hours. Unchecked days are closed. Status is calculated from the saved schedule, not from a live attendance feed.</p><?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $day): ?><div class="shelter-day"><label><input type="checkbox" name="day_open[<?php echo $day; ?>]" value="1" <?php echo in_array($day,['Monday','Tuesday','Wednesday','Thursday','Friday'],true)?'checked':''; ?>> <?php echo $day; ?></label><input type="time" name="open_time[<?php echo $day; ?>]" value="08:00" aria-label="<?php echo $day; ?> opening time"><input type="time" name="close_time[<?php echo $day; ?>]" value="17:00" aria-label="<?php echo $day; ?> closing time"></div><?php endforeach; ?></div>
    <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:16px"><button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> Save Shelter</button><button class="btn" type="button" onclick="resetShelterAdminForm()">Clear / New</button></div>
   </form>
  </div>
  <div class="shelter-admin-panel" style="margin-top:24px">
   <h2>Registered Shelters (<?php echo count($shelterAdminRows); ?>)</h2>
   <div class="shelter-table-scroll"><table class="shelter-admin-table"><thead><tr><th>Shelter</th><th>Location</th><th>Rescue / Slots</th><th>Rating</th><th>Office Hours</th><th>Status</th><th>Actions</th></tr></thead><tbody>
   <?php foreach ($shelterAdminRows as $sh): ?>
    <tr><td><strong><?php echo $adminEscape($sh['shelter_name']); ?></strong><div style="font-size:.8rem;color:var(--text-muted)"><?php echo $adminEscape($sh['contact_number']); ?></div></td>
    <td><?php echo $adminEscape($sh['city'].', '.$sh['province']); ?></td>
    <td><?php echo (int)$sh['rescue_available'] ? 'Available' : 'Unavailable'; ?> · <?php echo (int)$sh['available_slots']; ?>/<?php echo (int)$sh['capacity']; ?></td>
    <td><?php echo $sh['average_rating'] === null ? 'No ratings' : $adminEscape($sh['average_rating']).' / 5 ('.(int)$sh['rating_count'].')'; ?></td>
    <td><span class="shelter-hours-badge" data-shelter-hours="<?php echo $adminEscape($sh['opening_hours'] ?? ''); ?>">Checking hours…</span><div style="font-size:.76rem;color:var(--text-muted);margin-top:5px">Asia/Manila</div></td>
    <td><strong><?php echo $adminEscape($sh['verification_status']); ?></strong></td>
    <td><div class="shelter-admin-actions">
     <button type="button" class="btn" onclick='editShelterAdmin(<?php echo htmlspecialchars(json_encode($sh, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT), ENT_QUOTES, "UTF-8"); ?>)'>Edit</button>
     <form method="post" action="?tab=shelters"><input type="hidden" name="shelter_csrf" value="<?php echo $adminEscape($_SESSION['shelter_admin_csrf']); ?>"><input type="hidden" name="shelter_action" value="status"><input type="hidden" name="shelter_id" value="<?php echo (int)$sh['id']; ?>"><select name="verification_status" aria-label="Verification status"><?php foreach (['Pending','Approved','Rejected','Suspended'] as $opt): ?><option value="<?php echo $opt; ?>" <?php echo $sh['verification_status']===$opt?'selected':''; ?>><?php echo $opt; ?></option><?php endforeach; ?></select><button type="submit" class="btn">Update</button></form>
     <form method="post" action="?tab=shelters" onsubmit="return confirm('Delete this shelter and all its ratings?')"><input type="hidden" name="shelter_csrf" value="<?php echo $adminEscape($_SESSION['shelter_admin_csrf']); ?>"><input type="hidden" name="shelter_action" value="delete"><input type="hidden" name="shelter_id" value="<?php echo (int)$sh['id']; ?>"><button type="submit" class="btn" style="color:#ef4444">Delete</button></form>
    </div></td></tr>
   <?php endforeach; ?>
   <?php if (!$shelterAdminRows): ?><tr><td colspan="7">No shelters added yet.</td></tr><?php endif; ?>
   </tbody></table></div>
  </div>
 </div>
</section>

<!-- =========================
     DASHBOARD
========================= -->
<section class="main-container <?php echo $activeTab == 'dashboard' ? 'active' : ''; ?>">
    
    <div class="container">
        
        <div style="margin-bottom: 40px;">
            <h1 style="font-size: clamp(2rem, 4vw, 2.5rem); font-weight: 900; margin-bottom: 10px;">
                Good <?php echo (date('H') < 12) ? 'Morning' : 'Afternoon'; ?>, Team.
            </h1>
            <p class="section-subtitle">
                You have 
                <b style="color:var(--primary);">
                    <?php echo $pending_rescues; ?> pending rescues
                </b> 
                and 
                <b style="color:var(--secondary);">
                    <?php echo $pending_apps; ?> adoption applications
                </b> 
                today.
            </p>
        </div>

        <div class="stat-cards">
            
            <div class="stat-card" style="border-bottom: 4px solid var(--text-muted);">
                <div>
                    <i class="fas fa-paw"></i> Total Pets Housed
                </div>
                <h2><?php echo $total_pets; ?></h2>
            </div>
            
            <div class="stat-card" style="border-bottom: 4px solid var(--success);">
                <div>
                    <i class="fas fa-home"></i> Ready for Adoption
                </div>
                <h2><?php echo $available_pets; ?></h2>
            </div>
            
            <div class="stat-card" style="border-bottom: 4px solid var(--danger);">
                <div>
                    <i class="fas fa-exclamation-triangle"></i> Action Needed
                </div>
                <h2><?php echo $pending_rescues; ?></h2>
            </div>
            
            <div class="stat-card" style="border-bottom: 4px solid var(--secondary);">
                <div>
                    <i class="fas fa-file-signature"></i> Adoptions Pending
                </div>
                <h2><?php echo $pending_apps; ?></h2>
            </div>

        </div>


<div class="admin-insights">
<div class="admin-panel"><h3><i class="fas fa-chart-bar" style="color:var(--primary)"></i> Shelter Overview</h3>
<?php $petBase=max(1,$total_pets); $taskBase=max(1,$pending_apps+$pending_rescues+$pending_appointments); ?>
<div class="insight-row"><span>Available Pets</span><div class="insight-track"><div class="insight-fill" style="width:<?php echo min(100,round($available_pets/$petBase*100)); ?>%"></div></div><span><?php echo $available_pets; ?></span></div>
<div class="insight-row"><span>Pending Adoptions</span><div class="insight-track"><div class="insight-fill" style="width:<?php echo round($pending_apps/$taskBase*100); ?>%"></div></div><span><?php echo $pending_apps; ?></span></div>
<div class="insight-row"><span>Rescue Alerts</span><div class="insight-track"><div class="insight-fill" style="width:<?php echo round($pending_rescues/$taskBase*100); ?>%"></div></div><span><?php echo $pending_rescues; ?></span></div>
<div class="insight-row"><span>Appointments</span><div class="insight-track"><div class="insight-fill" style="width:<?php echo round($pending_appointments/$taskBase*100); ?>%"></div></div><span><?php echo $pending_appointments; ?></span></div>
</div>
<div class="admin-panel"><h3><i class="fas fa-bolt" style="color:var(--primary)"></i> Quick Actions</h3><div class="admin-quicklinks">
<a href="?tab=manage-pets"><i class="fas fa-paw"></i> Manage Pets</a><a href="?tab=rescue-reports"><i class="fas fa-ambulance"></i> Rescue Reports</a><a href="?tab=review-applications"><i class="fas fa-clipboard-check"></i> Review Adoptions</a><a href="?tab=appointments"><i class="fas fa-calendar-check"></i> Appointments</a><a href="?tab=medical-records"><i class="fas fa-notes-medical"></i> Medical Records</a><a href="?tab=manage-users"><i class="fas fa-users"></i> Manage Users</a>
</div></div></div>

        <div class="grid-layout">
            
            <div class="form-container">
                
                <h3>
                    <i class="fas fa-user-plus" style="color:var(--primary);"></i> 
                    Recent Registrations
                </h3>
                
                <div style="display:flex; flex-direction:column; gap:15px;">
                    
                    <?php
                    if($recent_users_q && mysqli_num_rows($recent_users_q) > 0) {
                        
                        while($ru = mysqli_fetch_assoc($recent_users_q)) {
                            
                            $uName = $ru[$u_name_field] ?? 'Unknown';
                            $initial = strtoupper(substr($uName, 0, 1));
                            $u_date = isset($ru['created_at']) ? date('M j, Y', strtotime($ru['created_at'])) : 'Recent';
                    ?>
                    
                    <div style="display:flex; align-items:center; justify-content:space-between; padding:15px; background:var(--bg-main); border-radius:var(--radius-md); border:1px solid var(--border-color);">
                        
                        <div style="display:flex; align-items:center; gap:15px;">
                            
                            <div style="width:45px; height:45px; background:var(--primary-light); color:var(--primary); border: 1px solid var(--primary); border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:1.1rem;">
                                <?php echo $initial; ?>
                            </div>
                            
                            <div>
                                <h4 style="font-size:1rem; margin:0 0 5px; color:var(--text-dark); font-weight:800;">
                                    <?php echo htmlspecialchars($uName); ?>
                                </h4>
                                <p style="font-size:0.85rem; margin:0; color:var(--text-muted); font-weight:700;">
                                    <?php echo htmlspecialchars($ru['role'] ?? 'Visitor'); ?>
                                </p>
                            </div>

                        </div>
                        
                        <span style="font-size:0.85rem; color:var(--text-light); font-weight:700;">
                            <i class="far fa-clock"></i> 
                            <?php echo $u_date; ?>
                        </span>

                    </div>
                    
                    <?php
                        }
                    } else {
                        echo "<p style='color:var(--text-muted);'>No recent sign-ups.</p>";
                    }
                    ?>

                </div>

            </div>

            <div class="form-container" style="background: linear-gradient(135deg, var(--primary), var(--primary-hover)); color:#fff; text-align:center; border:none; display:flex; flex-direction:column; justify-content:center;">
                
                <i class="fas fa-server" style="font-size: 45px; margin-bottom: 20px; color: rgba(255,255,255,0.8);"></i>
                
                <h3 style="font-size:1.8rem; margin-bottom:10px; color: white; border:none; padding:0; justify-content:center;">
                    System Live
                </h3>
                
                <p style="font-size:1rem; margin-bottom:30px; font-weight:600; color: rgba(255,255,255,0.9);">
                    Community stands at <br>
                    <b style="font-size: 1.5rem; color:white;">
                        <?php echo $total_users; ?> active users
                    </b>.
                </p>
                
                <a href="?tab=manage-pets" class="btn" style="background:#fff; color:var(--primary); width:100%;">
                    <i class="fas fa-cog"></i> 
                    Manage Shelter
                </a>

            </div>

        </div>

    </div>

</section>


<!-- =========================
     RESCUE REPORTS
========================= -->
<section class="main-container <?php echo $activeTab == 'rescue-reports' ? 'active' : ''; ?>">
    
    <div class="container">
        
        <h1 class="section-title">
            <i class="fas fa-ambulance" style="color:var(--danger);"></i> 
            Rescue Reports
        </h1>
        
        <p class="section-subtitle">
            Review and action strays or animals reported in need.
        </p>

        <div class="profile-tabs">
            
            <div 
                class="ptab active" 
                id="tab-btn-active" 
                onclick="switchRescueTab('active')"
            >
                Active Reports
            </div>
            
            <div 
                class="ptab" 
                id="tab-btn-history" 
                onclick="switchRescueTab('history')"
            >
                History / Resolved
            </div>

        </div>

        <div id="view-active-reports">
            
            <div class="grid">
                
                <?php
                $active_reports = mysqli_query(
                    $conn, 
                    "SELECT * FROM rescue_reports WHERE status IN ('Action Needed','In Progress') ORDER BY id DESC"
                );
                
                if($active_reports && mysqli_num_rows($active_reports) > 0) {
                    
                    while($rep = mysqli_fetch_assoc($active_reports)) {
                        
                        $stat_color = ($rep['status'] == 'In Progress') ? 'badge-pending' : 'badge-danger';
                        $report_photo = trim((string)($rep['image'] ?? ''));
                        $r_img = '';
                        if ($report_photo !== '') {
                            if (filter_var($report_photo, FILTER_VALIDATE_URL)) {
                                $r_img = $report_photo;
                            } else {
                                $r_img = '/' . ltrim(str_replace('\\', '/', $report_photo), '/');
                                if (strpos($r_img, '/uploads/') !== 0) {
                                    $r_img = '/uploads/' . basename($report_photo);
                                }
                            }
                        }
                        $r_date = isset($rep['reported_at']) ? date('M j, g:i A', strtotime($rep['reported_at'])) : 'Recent';
                ?>
                
                <div class="pet-card">
                    
                    <span class="badge <?php echo $stat_color; ?> pet-card-status">
                        <?php echo htmlspecialchars($rep['status']); ?>
                    </span>
                    
                    <div class="pet-card-img-wrap">
                        <div class="rescue-photo-placeholder" aria-label="Rescue report photo unavailable">
                            <i class="fas fa-paw"></i>
                            <span>No rescue photo available</span>
                        </div>
                        <?php if ($r_img !== ''): ?>
                        <img
                            src="<?php echo htmlspecialchars($r_img, ENT_QUOTES, 'UTF-8'); ?>"
                            class="pet-card-img rescue-report-photo"
                            alt="Rescue report photo"
                            loading="lazy"
                            onerror="this.style.display='none';"
                        >
                        <?php endif; ?>
                    </div>
                    
                    <div class="pet-card-content">
                        
                        <h4 style="font-size:1.1rem; font-weight:800; margin:0 0 10px; color:var(--text-dark);">
                            <i class="fas fa-map-marker-alt" style="color:var(--secondary);"></i> 
                            <?php echo htmlspecialchars($rep['location'] ?? 'Unknown'); ?>
                        </h4>
                        
                        <p style="font-size:0.95rem; color:var(--text-muted); margin-bottom:20px; flex:1; line-height: 1.5;">
                            <?php echo htmlspecialchars($rep['description'] ?? ''); ?>
                        </p>
                        
                        <div style="background:var(--bg-main); padding:15px; border-radius:var(--radius-md); font-size:0.85rem; margin-bottom:20px; border: 1px dashed var(--border-color); color: var(--text-dark);">
                            
                            <div style="margin-bottom: 5px;">
                                <b>Reporter:</b> 
                                <?php echo htmlspecialchars($rep['reporter_name'] ?? 'Anonymous'); ?>
                            </div>
                            
                            <div>
                                <b>Contact:</b> 
                                <?php echo htmlspecialchars($rep['contact_info'] ?? 'N/A'); ?>
                            </div>
                            
                            <div style="margin-top: 5px; color: var(--text-light); font-weight: 700;">
                                <i class="far fa-clock"></i> 
                                <?php echo $r_date; ?>
                            </div>

                        </div>

                        <form method="POST" style="margin-bottom:15px; padding:12px; background:var(--bg-main); border:1px solid var(--border-color); border-radius:var(--radius-md);">
                            <input type="hidden" name="priority_report_id" value="<?php echo $rep['id']; ?>">
                            <div class="form-row" style="gap:10px;">
                                <div class="form-group">
                                    <label>Priority</label>
                                    <select name="rescue_priority" style="margin-bottom:8px; padding:10px;">
                                        <option value="Critical" <?php echo (($rep['priority'] ?? 'Medium') === 'Critical') ? 'selected' : ''; ?>>Critical</option>
                                        <option value="High" <?php echo (($rep['priority'] ?? 'Medium') === 'High') ? 'selected' : ''; ?>>High</option>
                                        <option value="Medium" <?php echo (($rep['priority'] ?? 'Medium') === 'Medium') ? 'selected' : ''; ?>>Medium</option>
                                        <option value="Low" <?php echo (($rep['priority'] ?? 'Medium') === 'Low') ? 'selected' : ''; ?>>Low</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Assigned Staff</label>
                                    <select name="assigned_to" style="margin-bottom:8px; padding:10px;">
                                        <option value="">Unassigned</option>
                                        <?php
                                        $priority_staff = mysqli_query($conn, "SELECT name, role FROM staff_attendance ORDER BY name ASC");
                                        if ($priority_staff) {
                                            while ($ps = mysqli_fetch_assoc($priority_staff)) {
                                                $ps_name = $ps['name'];
                                                $ps_role = $ps['role'];
                                                $selected_staff = (($rep['assigned_to'] ?? '') === $ps_name) ? 'selected' : '';
                                                echo '<option value="'.htmlspecialchars($ps_name, ENT_QUOTES).'" '.$selected_staff.'>'.htmlspecialchars($ps_name).' - '.htmlspecialchars($ps_role).'</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" name="update_rescue_priority" class="btn btn-outline" style="width:100%; padding:9px; font-size:0.82rem;">
                                <i class="fas fa-flag"></i> Save Priority & Assignment
                            </button>
                        </form>

                        <form method="POST" style="margin-bottom:15px; display:flex; gap:10px;">
                            
                            <input type="hidden" name="report_id" value="<?php echo $rep['id']; ?>">
                            
                            <?php if($rep['status'] == 'Action Needed') { ?>
                                <button 
                                    type="submit" 
                                    name="report_status" 
                                    value="In Progress" 
                                    class="btn btn-secondary" 
                                    style="flex:1; padding:10px; font-size:0.85rem;"
                                >
                                    <i class="fas fa-walking"></i> Acknowledge
                                </button>
                            <?php } ?>
                            
                            <button 
                                type="submit" 
                                name="report_status" 
                                value="Resolved" 
                                class="btn btn-success" 
                                style="flex:1; padding:10px; font-size:0.85rem;" 
                                title="Mark as Done"
                            >
                                <i class="fas fa-check-circle"></i> Done
                            </button>
                            
                            <input type="hidden" name="update_report" value="1">
                        
                        </form>

                        <div style="display:flex; gap:10px;">
                            
                            <button 
                                type="button" 
                                class="btn btn-outline" 
                                style="flex:1; padding:10px; font-size:0.85rem;" 
                                data-id="<?php echo $rep['id']; ?>" 
                                data-loc="<?php echo htmlspecialchars($rep['location'] ?? '', ENT_QUOTES); ?>" 
                                data-desc="<?php echo htmlspecialchars($rep['description'] ?? '', ENT_QUOTES); ?>" 
                                data-rep="<?php echo htmlspecialchars($rep['reporter_name'] ?? '', ENT_QUOTES); ?>" 
                                data-con="<?php echo htmlspecialchars($rep['contact_info'] ?? '', ENT_QUOTES); ?>" 
                                onclick="openRescueModal(this)"
                            >
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            
                            <form method="POST" style="margin:0; flex:1;" onsubmit="return confirm('Permanently delete this report?');">
                                <input type="hidden" name="report_id" value="<?php echo $rep['id']; ?>">
                                <button 
                                    type="submit" 
                                    name="delete_report" 
                                    class="btn btn-danger" 
                                    style="width:100%; padding:10px; font-size:0.85rem;"
                                >
                                    <i class="fas fa-trash"></i> Trash
                                </button>
                            </form>

                        </div>

                    </div>

                </div>
                
                <?php
                    }
                } else {
                    echo "<div style='grid-column:1/-1; padding:40px; text-align:center; color:var(--text-muted); border:1px dashed var(--border-color); border-radius:var(--radius-lg);'>No active reports at this time.</div>";
                }
                ?>

            </div>

        </div>

        <div id="view-history-reports" style="display:none;">
            
            <div class="grid">
                
                <?php
                $history_reports = mysqli_query(
                    $conn, 
                    "SELECT * FROM rescue_reports WHERE status IN ('Resolved','Rescued','Rejected') ORDER BY id DESC"
                );
                
                if($history_reports && mysqli_num_rows($history_reports) > 0) {
                    
                    while($rep = mysqli_fetch_assoc($history_reports)) {
                        
                        $stat_color = ($rep['status'] == 'Rejected') ? 'badge-gray' : 'badge-success';
                        $report_photo = trim((string)($rep['image'] ?? ''));
                        $r_img = '';
                        if ($report_photo !== '') {
                            if (filter_var($report_photo, FILTER_VALIDATE_URL)) {
                                $r_img = $report_photo;
                            } else {
                                $r_img = '/' . ltrim(str_replace('\\', '/', $report_photo), '/');
                                if (strpos($r_img, '/uploads/') !== 0) {
                                    $r_img = '/uploads/' . basename($report_photo);
                                }
                            }
                        }
                        $r_date = isset($rep['reported_at']) ? date('M j, g:i A', strtotime($rep['reported_at'])) : 'Recent';
                ?>
                
                <div class="pet-card" style="opacity: 0.85;">
                    
                    <span class="badge <?php echo $stat_color; ?> pet-card-status">
                        <?php echo htmlspecialchars($rep['status']); ?>
                    </span>
                    
                    <div class="pet-card-img-wrap" style="height: 140px;">
                        <div class="rescue-photo-placeholder" aria-label="Rescue report photo unavailable">
                            <i class="fas fa-paw"></i>
                            <span>No rescue photo available</span>
                        </div>
                        <?php if ($r_img !== ''): ?>
                        <img
                            src="<?php echo htmlspecialchars($r_img, ENT_QUOTES, 'UTF-8'); ?>"
                            class="pet-card-img rescue-report-photo"
                            alt="Rescue report photo"
                            loading="lazy"
                            onerror="this.style.display='none';"
                        >
                        <?php endif; ?>
                    </div>
                    
                    <div class="pet-card-content">
                        
                        <h4 style="font-size:1.05rem; font-weight:800; margin:0 0 10px; color:var(--text-dark);">
                            <i class="fas fa-map-marker-alt" style="color:var(--secondary);"></i> 
                            <?php echo htmlspecialchars($rep['location'] ?? 'Unknown'); ?>
                        </h4>
                        
                        <p style="font-size:0.9rem; color:var(--text-muted); margin-bottom:15px; flex:1; line-height: 1.5;">
                            <?php echo htmlspecialchars($rep['description'] ?? ''); ?>
                        </p>
                        
                        <div style="background:var(--bg-main); padding:10px; border-radius:var(--radius-md); font-size:0.8rem; margin-bottom:15px; border: 1px dashed var(--border-color); color: var(--text-dark);">
                            
                            <div>
                                <b>Reporter:</b> 
                                <?php echo htmlspecialchars($rep['reporter_name'] ?? 'Anonymous'); ?> | 
                                <b>Contact:</b> 
                                <?php echo htmlspecialchars($rep['contact_info'] ?? 'N/A'); ?>
                            </div>
                            
                            <div style="margin-top: 5px; color: var(--text-light); font-weight: 700;">
                                <i class="far fa-clock"></i> 
                                <?php echo $r_date; ?>
                            </div>

                        </div>

                        <div style="display:flex; gap:10px;">
                            
                            <button 
                                type="button" 
                                class="btn btn-outline" 
                                style="flex:1; padding:8px; font-size:0.8rem;" 
                                data-id="<?php echo $rep['id']; ?>" 
                                data-loc="<?php echo htmlspecialchars($rep['location'] ?? '', ENT_QUOTES); ?>" 
                                data-desc="<?php echo htmlspecialchars($rep['description'] ?? '', ENT_QUOTES); ?>" 
                                data-rep="<?php echo htmlspecialchars($rep['reporter_name'] ?? '', ENT_QUOTES); ?>" 
                                data-con="<?php echo htmlspecialchars($rep['contact_info'] ?? '', ENT_QUOTES); ?>" 
                                onclick="openRescueModal(this)"
                            >
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            
                            <form method="POST" style="margin:0; flex:1;" onsubmit="return confirm('Permanently delete this report history?');">
                                <input type="hidden" name="report_id" value="<?php echo $rep['id']; ?>">
                                <button 
                                    type="submit" 
                                    name="delete_report" 
                                    class="btn btn-danger" 
                                    style="width:100%; padding:8px; font-size:0.8rem;"
                                >
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>

                        </div>

                    </div>

                </div>
                
                <?php
                    }
                } else {
                    echo "<div style='grid-column:1/-1; padding:40px; text-align:center; color:var(--text-muted); border:1px dashed var(--border-color); border-radius:var(--radius-lg);'>No report history available.</div>";
                }
                ?>

            </div>

        </div>

    </div>

</section>


<!-- =========================
     MEDICAL RECORDS
========================= -->
<section class="main-container <?php echo $activeTab == 'medical-records' ? 'active' : ''; ?>">
    <div class="container">
        <h1 class="section-title">
            <i class="fas fa-notes-medical" style="color:var(--danger);"></i>
            Pet Health & Medical Records
        </h1>
        <p class="section-subtitle">
            Record vaccinations, checkups, treatments, medications, diagnoses, and upcoming veterinary visits.
        </p>

        <div class="form-container">
            <h3><i class="fas fa-plus-circle" style="color:var(--primary);"></i> Add Medical Record</h3>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Pet</label>
                        <select name="medical_pet_id" required>
                            <option value="">Select Pet</option>
                            <?php
                            $medical_pets = mysqli_query($conn, "SELECT id, name, species, breed FROM pets ORDER BY name ASC");
                            if ($medical_pets) {
                                while ($mp = mysqli_fetch_assoc($medical_pets)) {
                                    echo '<option value="'.$mp['id'].'">'.htmlspecialchars($mp['name']).' - '.htmlspecialchars($mp['species']).' / '.htmlspecialchars($mp['breed']).'</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Record Type</label>
                        <select name="record_type" required>
                            <option value="General Checkup">General Checkup</option>
                            <option value="Vaccination">Vaccination</option>
                            <option value="Deworming">Deworming</option>
                            <option value="Treatment">Treatment</option>
                            <option value="Medication">Medication</option>
                            <option value="Dental Care">Dental Care</option>
                            <option value="Emergency Care">Emergency Care</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Diagnosis / Finding</label>
                        <input type="text" name="diagnosis" placeholder="Example: Healthy / Skin infection">
                    </div>
                    <div class="form-group">
                        <label>Veterinarian</label>
                        <input type="text" name="veterinarian" placeholder="Veterinarian name">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Treatment</label>
                        <input type="text" name="treatment" placeholder="Treatment performed">
                    </div>
                    <div class="form-group">
                        <label>Medication</label>
                        <input type="text" name="medication" placeholder="Medication, if any">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Record Date</label>
                        <input type="date" name="record_date" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Next Checkup</label>
                        <input type="date" name="next_checkup">
                    </div>
                </div>

                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="medical_notes" rows="3" placeholder="Additional medical notes..."></textarea>
                </div>

                <button type="submit" name="add_medical_record" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Medical Record
                </button>
            </form>
        </div>

        <div class="form-container">
            <h3><i class="fas fa-history" style="color:var(--secondary);"></i> Recent Health Records</h3>
            <div style="overflow-x:auto;">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Pet</th>
                            <th>Type</th>
                            <th>Finding</th>
                            <th>Veterinarian</th>
                            <th>Record Date</th>
                            <th>Next Checkup</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $medical_records = mysqli_query($conn, "SELECT pmr.*, p.name AS pet_name, p.species FROM pet_medical_records pmr LEFT JOIN pets p ON p.id=pmr.pet_id ORDER BY pmr.id DESC");
                    if ($medical_records && mysqli_num_rows($medical_records) > 0) {
                        while ($mr = mysqli_fetch_assoc($medical_records)) {
                    ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($mr['pet_name'] ?? 'Unknown'); ?></strong><br><small><?php echo htmlspecialchars($mr['species'] ?? ''); ?></small></td>
                            <td><span class="badge badge-primary"><?php echo htmlspecialchars($mr['record_type']); ?></span></td>
                            <td><?php echo htmlspecialchars($mr['diagnosis'] ?: '—'); ?></td>
                            <td><?php echo htmlspecialchars($mr['veterinarian'] ?: '—'); ?></td>
                            <td><?php echo !empty($mr['record_date']) ? date('M j, Y', strtotime($mr['record_date'])) : '—'; ?></td>
                            <td>
                                <?php
                                if (!empty($mr['next_checkup'])) {
                                    $check_date = strtotime($mr['next_checkup']);
                                    $is_due = $check_date <= strtotime('+30 days');
                                    echo '<span class="badge '.($is_due ? 'badge-pending' : 'badge-success').'">'.date('M j, Y', $check_date).'</span>';
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                            <td>
                                <form method="POST" onsubmit="return confirm('Delete this medical record?');">
                                    <input type="hidden" name="medical_record_id" value="<?php echo $mr['id']; ?>">
                                    <button type="submit" name="delete_medical_record" class="btn btn-danger" style="padding:8px 12px; font-size:0.8rem;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php
                        }
                    } else {
                        echo '<tr><td colspan="7" style="text-align:center; color:var(--text-muted);">No medical records yet.</td></tr>';
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>


<!-- =========================
     ADOPTION APPOINTMENTS
========================= -->
<section class="main-container <?php echo $activeTab == 'appointments' ? 'active' : ''; ?>">
    <div class="container">
        
        <h1 class="section-title">
            <i class="fas fa-calendar-check" style="color:var(--success);"></i>
            Manage Appointments
        </h1>
        <p class="section-subtitle">
            Review and manage appointment requests submitted by clients. Confirm, cancel, or mark them as completed.
        </p>

        <div class="form-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0;"><i class="fas fa-bell" style="color:var(--secondary);"></i> Client Appointment Requests</h3>
                
                <?php if($pending_appointments > 0): ?>
                    <span class="badge badge-pending" style="font-size: 0.9rem; padding: 6px 15px;">
                        <?php echo $pending_appointments; ?> Pending Review
                    </span>
                <?php endif; ?>
            </div>
            
            <div style="overflow-x:auto;">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Date / Time</th>
                            <th>Client Info</th>
                            <th>Pet Requested</th>
                            <th>Purpose & Notes</th>
                            <th>Current Status</th>
                            <th>Admin Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $appointments = mysqli_query($conn, "SELECT aa.*, p.name AS pet_name, p.species, u.$u_name_field AS user_name, u.email FROM adoption_appointments aa LEFT JOIN pets p ON p.id=aa.pet_id LEFT JOIN users u ON u.id=aa.user_id ORDER BY aa.status = 'Pending' DESC, aa.appointment_date ASC, aa.appointment_time ASC");
                    if ($appointments && mysqli_num_rows($appointments) > 0) {
                        while ($ap = mysqli_fetch_assoc($appointments)) {
                            $appointment_badge = ($ap['status'] === 'Completed') ? 'badge-success' : (($ap['status'] === 'Cancelled' || $ap['status'] === 'No Show') ? 'badge-danger' : (($ap['status'] === 'Confirmed') ? 'badge-primary' : 'badge-pending'));
                    ?>
                        <tr style="<?php echo ($ap['status'] === 'Pending') ? 'background-color: var(--hero-bg-top);' : ''; ?>">
                            <td>
                                <strong><?php echo date('M j, Y', strtotime($ap['appointment_date'])); ?></strong><br>
                                <span style="color: var(--text-muted); font-weight: 700;">
                                    <i class="far fa-clock"></i> <?php echo date('g:i A', strtotime($ap['appointment_time'])); ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($ap['user_name'] ?? 'Unknown'); ?></strong><br>
                                <small style="color: var(--text-light);"><?php echo htmlspecialchars($ap['email'] ?? ''); ?></small>
                            </td>
                            <td>
                                <strong style="color: var(--primary);"><?php echo htmlspecialchars($ap['pet_name'] ?? 'Unknown'); ?></strong>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($ap['purpose']); ?></strong>
                                <?php if(!empty($ap['notes'])): ?>
                                    <br><small style="color: var(--text-muted);"><?php echo htmlspecialchars($ap['notes']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?php echo $appointment_badge; ?>"><?php echo htmlspecialchars($ap['status']); ?></span></td>
                            <td>
                                <form method="POST" style="display:flex; gap:6px; align-items:center;">
                                    <input type="hidden" name="appointment_id" value="<?php echo $ap['id']; ?>">
                                    <select name="appointment_status" style="margin:0; padding:8px; min-width:130px; border-color: var(--border-color);">
                                        <?php foreach(['Pending','Confirmed','Completed','Cancelled','No Show'] as $as): ?>
                                            <option value="<?php echo $as; ?>" <?php echo $ap['status'] === $as ? 'selected' : ''; ?>><?php echo $as; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" name="update_appointment" class="btn btn-secondary" style="padding:8px 15px;" title="Update Status">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php
                        }
                    } else {
                        echo '<tr><td colspan="6" style="text-align:center; padding: 40px; color:var(--text-muted);">No client appointment requests currently available.</td></tr>';
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>


<!-- =========================
     FOSTER CARE
========================= -->
<section class="main-container <?php echo $activeTab == 'foster-care' ? 'active' : ''; ?>">
    <div class="container">
        <h1 class="section-title">
            <i class="fas fa-home" style="color:var(--secondary);"></i>
            Manage Foster Applications
        </h1>
        <p class="section-subtitle">
            Review and manage foster care requests submitted by community members. Approve them to active status when confirmed.
        </p>

        <div class="stat-cards">
            <div class="stat-card">
                <div><i class="fas fa-hourglass-half"></i> Pending Foster</div>
                <h2><?php echo getStatCount($conn, "SELECT COUNT(*) as count FROM foster_applications WHERE status='Pending'"); ?></h2>
            </div>
            <div class="stat-card">
                <div><i class="fas fa-house-user"></i> Active Foster</div>
                <h2><?php echo $active_fosters; ?></h2>
            </div>
            <div class="stat-card">
                <div><i class="fas fa-paw"></i> Pets in Foster</div>
                <h2><?php echo getStatCount($conn, "SELECT COUNT(*) as count FROM foster_applications WHERE status IN ('Approved','Active')"); ?></h2>
            </div>
        </div>

        <div class="form-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0;"><i class="fas fa-inbox" style="color:var(--primary);"></i> Foster Applications Queue</h3>
                
                <?php $pending_fosters = getStatCount($conn, "SELECT COUNT(*) as count FROM foster_applications WHERE status='Pending'"); ?>
                <?php if($pending_fosters > 0): ?>
                    <span class="badge badge-pending" style="font-size: 0.9rem; padding: 6px 15px;">
                        <?php echo $pending_fosters; ?> Pending Review
                    </span>
                <?php endif; ?>
            </div>
            
            <div style="overflow-x:auto;">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Caregiver Info</th>
                            <th>Pet Requested</th>
                            <th>Proposed Schedule</th>
                            <th>Reason & Notes</th>
                            <th>Current Status</th>
                            <th>Admin Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $fosters = mysqli_query($conn, "SELECT fa.*, p.name AS pet_name, p.species, u.$u_name_field AS caregiver_name, u.email FROM foster_applications fa LEFT JOIN pets p ON p.id=fa.pet_id LEFT JOIN users u ON u.id=fa.user_id ORDER BY fa.status = 'Pending' DESC, fa.id DESC");
                    if ($fosters && mysqli_num_rows($fosters) > 0) {
                        while ($fo = mysqli_fetch_assoc($fosters)) {
                            $foster_badge = ($fo['status'] === 'Approved' || $fo['status'] === 'Active') ? 'badge-success' : (($fo['status'] === 'Rejected' || $fo['status'] === 'Completed') ? 'badge-gray' : 'badge-pending');
                    ?>
                        <tr style="<?php echo ($fo['status'] === 'Pending') ? 'background-color: var(--hero-bg-top);' : ''; ?>">
                            <td>
                                <strong><?php echo htmlspecialchars($fo['caregiver_name'] ?? 'Unknown'); ?></strong><br>
                                <small style="color: var(--text-light);"><?php echo htmlspecialchars($fo['email'] ?? ''); ?></small>
                            </td>
                            <td>
                                <strong style="color: var(--primary);"><?php echo htmlspecialchars($fo['pet_name'] ?? 'Unknown'); ?></strong><br>
                                <small style="color: var(--text-muted);"><?php echo htmlspecialchars($fo['species'] ?? ''); ?></small>
                            </td>
                            <td>
                                <?php echo !empty($fo['start_date']) ? date('M j, Y', strtotime($fo['start_date'])) : '—'; ?><br>
                                to <span style="font-weight: 700; color: var(--text-muted);"><?php echo !empty($fo['expected_end_date']) ? date('M j, Y', strtotime($fo['expected_end_date'])) : 'Open'; ?></span>
                            </td>
                            <td style="max-width:250px;">
                                <strong><?php echo htmlspecialchars($fo['foster_reason'] ?: '—'); ?></strong>
                                <?php if(!empty($fo['notes'])): ?>
                                    <br><small style="color: var(--text-muted);"><?php echo htmlspecialchars($fo['notes']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?php echo $foster_badge; ?>"><?php echo htmlspecialchars($fo['status']); ?></span></td>
                            <td>
                                <form method="POST" style="display:flex; gap:6px; align-items:center;">
                                    <input type="hidden" name="foster_id" value="<?php echo $fo['id']; ?>">
                                    <select name="foster_status" style="margin:0; padding:8px; min-width:120px; border-color: var(--border-color);">
                                        <?php foreach(['Pending','Approved','Active','Completed','Rejected'] as $fs): ?>
                                            <option value="<?php echo $fs; ?>" <?php echo $fo['status'] === $fs ? 'selected' : ''; ?>><?php echo $fs; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" name="update_foster" class="btn btn-secondary" style="padding:8px 15px;" title="Update Status">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php
                        }
                    } else {
                        echo '<tr><td colspan="6" style="text-align:center; padding: 40px; color:var(--text-muted);">No foster applications currently available.</td></tr>';
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>


<!-- =========================
     MANAGE PETS
========================= -->
<section class="main-container <?php echo $activeTab == 'manage-pets' ? 'active' : ''; ?>">
    
    <div class="container">
        
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:20px; margin-bottom:30px;">
            
            <div>
                <h1 class="section-title" style="margin-bottom:0;">
                    <i class="fas fa-dog" style="color:var(--primary);"></i> 
                    Pet Database
                </h1>
                <p class="section-subtitle" style="margin-bottom:0;">
                    Manage shelter animals, medical holds, and adoptions.
                </p>
            </div>
            
            <button 
                type="button" 
                class="btn btn-primary" 
                onclick="openPetModal(null);"
            >
                <i class="fas fa-plus"></i> Register New Pet
            </button>

        </div>

        <div class="grid">
            
            <?php
            $pets = mysqli_query($conn, "SELECT * FROM pets ORDER BY id DESC");
            if ($pets && mysqli_num_rows($pets) > 0) {
                while($p = mysqli_fetch_assoc($pets)) {
                    $img = empty($p['image']) ? 'uploads/default.jpg' : (filter_var($p['image'], FILTER_VALIDATE_URL) ? $p['image'] : 'uploads/'.$p['image']);
                    $stat_class = "badge-gray";
                    if(strtolower($p['status'] ?? '') == 'available') $stat_class = "badge-success";
                    if(strtolower($p['status'] ?? '') == 'intake') $stat_class = "badge-pending";
                    if(strtolower($p['status'] ?? '') == 'adopted') $stat_class = "badge-primary"; // using primary for blue
            ?>
            
            <div 
                class="pet-card" 
                data-id="<?php echo $p['id']; ?>"
                data-name="<?php echo htmlspecialchars($p['name'] ?? '', ENT_QUOTES); ?>"
                data-species="<?php echo htmlspecialchars($p['species'] ?? 'Dog', ENT_QUOTES); ?>"
                data-breed="<?php echo htmlspecialchars($p['breed'] ?? '', ENT_QUOTES); ?>"
                data-age="<?php echo $p['age'] ?? 0; ?>"
                data-gender="<?php echo htmlspecialchars($p['gender'] ?? '', ENT_QUOTES); ?>"
                data-size="<?php echo htmlspecialchars($p['size'] ?? '', ENT_QUOTES); ?>"
                data-activity="<?php echo htmlspecialchars($p['activity_level'] ?? '', ENT_QUOTES); ?>"
                data-temperament="<?php echo htmlspecialchars($p['temperament'] ?? '', ENT_QUOTES); ?>"
                data-vaccine="<?php echo htmlspecialchars($p['vaccinated'] ?? '', ENT_QUOTES); ?>"
                data-status="<?php echo htmlspecialchars($p['status'] ?? '', ENT_QUOTES); ?>"
                data-desc="<?php echo htmlspecialchars($p['description'] ?? '', ENT_QUOTES); ?>"
                data-image="<?php echo htmlspecialchars($img, ENT_QUOTES); ?>"
                onclick="openPetModal(this)"
            >

                <div class="pet-card-img-wrap">
                    <span 
                        class="badge <?php echo $stat_class; ?> pet-card-status" 
                        style="<?php if($stat_class==='badge-primary') echo 'background:var(--badge-blue-bg); color:var(--badge-blue-text); border:1px solid #BFDBFE;'; ?>"
                    >
                        <?php echo htmlspecialchars($p['status'] ?? ''); ?>
                    </span>
                    <img 
                        src="<?php echo htmlspecialchars($img); ?>" 
                        class="pet-card-img" 
                        onerror="this.onerror=null; this.src='uploads/default.jpg';"
                    >
                </div>

                <div class="pet-card-content">
                    
                    <h3 class="pet-card-title">
                        <?php echo htmlspecialchars($p['name'] ?? ''); ?>
                    </h3>
                    
                    <p class="pet-card-subtitle">
                        <?php echo htmlspecialchars($p['species'] ?? 'Unknown'); ?> - 
                        <?php echo htmlspecialchars($p['breed'] ?? ''); ?>
                    </p>

                    <div class="pet-tags">
                        <?php 
                        if(!empty($p['gender'])) { 
                            echo '<span class="pet-tag"><i class="fas fa-venus-mars"></i> ' . htmlspecialchars($p['gender']) . '</span>'; 
                        } 
                        ?>
                        <?php 
                        if(!empty($p['size'])) { 
                            echo '<span class="pet-tag"><i class="fas fa-ruler"></i> ' . htmlspecialchars($p['size']) . '</span>'; 
                        } 
                        ?>
                        <?php 
                        if(!empty($p['vaccinated']) && $p['vaccinated'] == 'Yes') { 
                            echo '<span class="pet-tag"><i class="fas fa-syringe" style="color:var(--success);"></i> Vaxxed</span>'; 
                        } 
                        ?>
                    </div>

                    <div style="margin-top: auto; display: flex; justify-content: space-between; align-items: center; padding-top: 15px; border-top: 1px solid var(--border-color);">
                        
                        <span style="font-size:0.85rem; font-weight:800; color: var(--text-light);">
                            #PT-<?php echo sprintf('%04d', $p['id']); ?>
                        </span>
                        
                        <span style="background:var(--bg-main); padding:4px 10px; border-radius:var(--radius-md); font-size:0.8rem; font-weight:800; color:var(--text-muted); border:1px solid var(--border-color);">
                            <i class="fas fa-birthday-cake" style="color:var(--primary);"></i> 
                            <?php echo $p['age'] ?? 0; ?> yrs
                        </span>

                    </div>

                </div>

                <!-- Hidden data for modal injection -->
                <div class="hidden-history-data" style="display:none;">
                    <?php
                    if(isset($histories[$p['id']])) {
                        foreach($histories[$p['id']] as $h) {
                            echo '<div class="timeline-item"><div class="timeline-date">'. htmlspecialchars($h['date']). '</div><div class="timeline-desc">'. htmlspecialchars($h['desc']). '</div></div>';
                        }
                    } else {
                        echo '<p style="color:var(--text-muted); font-size:0.9rem; font-weight:700;">No history logged for this pet yet.</p>';
                    }
                    ?>
                </div>

            </div>
            
            <?php
                }
            } else {
                echo "<div style='grid-column:1/-1; padding:40px; text-align:center; color:var(--text-muted); border:1px dashed var(--border-color); border-radius:var(--radius-lg);'>No pets found.</div>";
            }
            ?>

        </div>

    </div>

</section>


<!-- =========================
     APPLICATIONS
========================= -->
<section class="main-container <?php echo $activeTab == 'review-applications' ? 'active' : ''; ?>">
    
    <div class="container">
        
        <h1 class="section-title">
            <i class="fas fa-file-signature" style="color:var(--secondary);"></i> 
            Adoption Applications
        </h1>
        
        <p class="section-subtitle">
            Review potential adopters and finalize matches.
        </p>

        <div class="grid" style="grid-template-columns: repeat(auto-fill,minmax(350px,1fr));">
            
            <?php
            $app_query = mysqli_query($conn, "SELECT a.*, p.name AS pet_name, p.image AS pet_image, u.$u_name_field AS applicant_name, u.email, u.profile_photo, u.phone, u.address, u.bio FROM adoption_applications a JOIN pets p ON a.pet_id = p.id JOIN users u ON a.user_id = u.id ORDER BY a.id DESC");
            
            if($app_query && mysqli_num_rows($app_query) > 0) {
                
                while($app = mysqli_fetch_assoc($app_query)) {
                    
                    $stat_c = "badge-pending";
                    
                    if($app['status'] == 'Approved') {
                        $stat_c = "badge-success";
                    }
                    if($app['status'] == 'Rejected') {
                        $stat_c = "badge-danger";
                    }
                    
                    $app_date = isset($app['application_date']) ? date('M j, Y', strtotime($app['application_date'])) : 'Recent';
            ?>
            
            <div class="form-container" style="padding:25px;">
                
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                    <span class="badge <?php echo $stat_c; ?>">
                        <?php echo htmlspecialchars($app['status']); ?>
                    </span>
                    <span style="font-size:0.8rem; font-weight:700; color:var(--text-light);">
                        <i class="far fa-calendar-alt"></i> 
                        <?php echo $app_date; ?>
                    </span>
                </div>

                <?php
                    $applicantPhoto = trim((string)($app['profile_photo'] ?? ''));
                    $petPhoto = trim((string)($app['pet_image'] ?? ''));
                    $petPhotoUrl = '';
                    if ($petPhoto !== '' && !in_array(strtolower($petPhoto), ['default.jpg', 'none', 'null'], true)) {
                        if (preg_match('~^https?://~i', $petPhoto)) {
                            $petPhotoUrl = $petPhoto;
                        } else {
                            $petPhotoUrl = (strpos($petPhoto, 'uploads/') === 0 ? $petPhoto : 'uploads/' . ltrim($petPhoto, '/'));
                        }
                    }
                    $applicantInitial = strtoupper(substr(trim((string)($app['applicant_name'] ?? 'C')), 0, 1));
                    if ($applicantInitial === '') $applicantInitial = 'C';
                ?>
                <div class="application-identity">
                    <div class="application-avatar" aria-label="Client profile picture">
                        <span class="application-avatar-fallback"><i class="fas fa-user"></i></span>
                        <?php if ($applicantPhoto !== ''): ?>
                            <img src="<?php echo htmlspecialchars($applicantPhoto, ENT_QUOTES); ?>" alt="Client profile photo" loading="lazy" onerror="this.remove()">
                        <?php else: ?>
                            <span class="application-initial"><?php echo htmlspecialchars($applicantInitial); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="application-identity-details">
                        <h4><?php echo htmlspecialchars($app['applicant_name'] ?? 'Unknown'); ?></h4>
                        <p>Applying for: <strong><?php echo htmlspecialchars($app['pet_name'] ?? ''); ?></strong></p>
                    </div>
                    <div class="application-pet-photo" title="Pet being applied for">
                        <span class="application-pet-fallback"><i class="fas fa-paw"></i></span>
                        <?php if ($petPhotoUrl !== ''): ?>
                            <img src="<?php echo htmlspecialchars($petPhotoUrl, ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($app['pet_name'] ?? 'Pet', ENT_QUOTES); ?>" loading="lazy" onerror="this.remove()">
                        <?php endif; ?>
                    </div>
                </div>

                <div style="background:var(--bg-main);border:1px solid var(--border-color);border-radius:16px;padding:16px;margin-bottom:20px;">
                    <div style="display:flex;gap:14px;align-items:center;margin-bottom:12px;">
                        <?php if (!empty($app['profile_photo'])): ?>
                            <div class="application-avatar" style="width:72px;height:72px;flex-shrink:0;">
                                <span class="application-initial"><?php echo htmlspecialchars(strtoupper(substr($app['applicant_name'] ?? 'C', 0, 1))); ?></span>
                                <img src="<?php echo htmlspecialchars($app['profile_photo'], ENT_QUOTES); ?>" alt="Applicant photo" loading="lazy" onerror="this.remove()">
                            </div>
                        <?php else: ?>
                            <div class="avatar-small" style="width:72px;height:72px;font-size:25px;flex-shrink:0;"><?php echo htmlspecialchars(strtoupper(substr($app['applicant_name'] ?? '?',0,1))); ?></div>
                        <?php endif; ?>
                        <div><strong style="font-size:1.05rem;">Client Profile</strong><div style="color:var(--text-muted);font-size:.85rem;">Account #<?php echo (int)$app['user_id']; ?></div></div>
                    </div>
                    <div style="overflow-wrap:anywhere;font-size:.9rem;line-height:1.8;">
                        <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($app['email'] ?? 'Not provided'); ?></div>
                        <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($app['phone'] ?: 'Phone not provided'); ?></div>
                        <div><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($app['address'] ?: 'Address not provided'); ?></div>
                        <div style="margin-top:8px;"><strong>About / Pet Care Experience:</strong><p style="margin:4px 0;white-space:pre-wrap;overflow-wrap:anywhere;"><?php echo htmlspecialchars($app['bio'] ?: 'Not provided'); ?></p></div>
                    </div>
                </div>

                <div style="font-size:0.95rem; line-height:1.8; color:var(--text-dark); margin-bottom:20px; font-weight: 600;">
                    <div>
                        <b style="color:var(--text-muted); font-size:0.85rem; text-transform:uppercase;">Living Space:</b> 
                        <?php echo htmlspecialchars($app['living_space'] ?? ''); ?>
                    </div>
                    <div>
                        <b style="color:var(--text-muted); font-size:0.85rem; text-transform:uppercase;">Experience:</b> 
                        <?php echo htmlspecialchars($app['experience_level'] ?? ''); ?>
                    </div>
                    <div>
                        <b style="color:var(--text-muted); font-size:0.85rem; text-transform:uppercase;">Activity:</b> 
                        <?php echo htmlspecialchars($app['activity_level'] ?? ''); ?>
                    </div>
                    <div>
                        <b style="color:var(--text-muted); font-size:0.85rem; text-transform:uppercase;">Children:</b> 
                        <?php echo htmlspecialchars($app['with_children'] ?? ''); ?>
                    </div>
                </div>

                <?php if($app['status'] == 'Pending') { ?>
                <form method="POST" style="display:flex; gap:10px; margin:0;">
                    
                    <input type="hidden" name="adoption_id" value="<?php echo $app['id']; ?>">
                    <input type="hidden" name="adopt_pet_id" value="<?php echo $app['pet_id']; ?>">
                    
                    <button 
                        type="submit" 
                        name="action_adoption" 
                        value="Approved" 
                        class="btn btn-success" 
                        style="flex:1; padding:10px; font-size:0.9rem;"
                    >
                        <i class="fas fa-check"></i> Approve
                    </button>
                    
                    <button 
                        type="submit" 
                        name="action_adoption" 
                        value="Rejected" 
                        class="btn btn-danger" 
                        style="flex:1; padding:10px; font-size:0.9rem;"
                    >
                        <i class="fas fa-times"></i> Reject
                    </button>

                </form>
                
                <?php } else { ?>
                    <div style="text-align:center; font-size:0.9rem; font-weight:800; color:var(--text-light); background: var(--bg-main); padding: 10px; border-radius: var(--radius-md); border: 1px dashed var(--border-color);">
                        <i class="fas fa-lock"></i> Application Processed
                    </div>
                <?php } ?>

            </div>
            
            <?php
                }
            } else {
                echo "<div style='grid-column:1/-1; padding:40px; text-align:center; color:var(--text-muted); border:1px dashed var(--border-color); border-radius:var(--radius-lg);'>No applications currently submitted.</div>";
            }
            ?>

        </div>

    </div>

</section>


<!-- =========================
     COMMUNITY FEED
========================= -->
<section class="main-container <?php echo $activeTab == 'community-feed' ? 'active' : ''; ?>">
    
    <div class="container">
        
        <h1 class="section-title">
            <i class="fas fa-comments" style="color:var(--primary);"></i> 
            Community Feed
        </h1>
        
        <p class="section-subtitle">
            Manage public posts and shared adoption stories.
        </p>

        <div class="grid" style="grid-template-columns: repeat(auto-fill,minmax(320px,1fr));">
            
            <?php
            $feed_query = mysqli_query($conn, "SELECT * FROM community_posts ORDER BY id DESC");
            
            if($feed_query && mysqli_num_rows($feed_query) > 0) {
                
                while($post = mysqli_fetch_assoc($feed_query)) {
                    
                    $p_img = !empty($post['image']) ? 'uploads/'.$post['image'] : null;
                    $p_date = isset($post['created_at']) ? date('M j, g:i A', strtotime($post['created_at'])) : 'Recent';
                    $post_initials = strtoupper(substr($post['user_display_name'], 0, 1));
            ?>
            
            <div class="form-container" style="padding:25px; transition:0.3s;">
                
                <div style="display:flex; align-items:center; gap:12px; margin-bottom:20px;">
                    
                    <div class="avatar-small" style="background:var(--text-muted); flex-shrink:0;">
                        <?php echo $post_initials; ?>
                    </div>
                    
                    <div style="flex:1;">
                        <p style="margin:0; font-weight:800; font-size:1.05rem; color:var(--text-dark);">
                            <?php echo htmlspecialchars($post['user_display_name'] ?? ''); ?>
                        </p>
                        <div style="font-size:0.8rem; font-weight:700; color:var(--text-light);">
                            <?php echo $p_date; ?>
                        </div>
                    </div>

                </div>

                <p style="font-size:0.95rem; line-height:1.6; color:var(--text-dark); margin-bottom:20px;">
                    <?php echo htmlspecialchars($post['content'] ?? ''); ?>
                </p>

                <?php if($p_img): ?>
                <div style="width:100%; border-radius:var(--radius-md); overflow:hidden; margin-bottom:20px; border:1px solid var(--border-color);">
                    <img src="<?php echo htmlspecialchars($p_img); ?>" style="width:100%; max-height:250px; object-fit:cover;">
                </div>
                <?php endif; ?>

                <form method="POST" onsubmit="return confirm('Delete this post?');" style="margin:0; border-top:1px solid var(--border-color); padding-top:15px;">
                    <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                    <button type="submit" name="delete_post" class="btn btn-outline" style="width:100%; padding:10px; font-size:0.9rem; color:var(--danger); border-color:var(--danger-light);">
                        <i class="fas fa-trash"></i> Delete Post
                    </button>
                </form>

            </div>
            
            <?php
                }
            } else {
                echo "<div style='grid-column:1/-1; padding:40px; text-align:center; color:var(--text-muted); border:1px dashed var(--border-color); border-radius:var(--radius-lg);'>No community posts yet.</div>";
            }
            ?>

        </div>

    </div>

</section>


<!-- =========================
     COMMUNITY USERS
========================= -->
<section class="main-container <?php echo $activeTab == 'manage-users' ? 'active' : ''; ?>">
    
    <div class="container">
        
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:20px; margin-bottom:30px;">
            
            <div>
                <h1 class="section-title" style="margin-bottom:0;">
                    <i class="fas fa-users" style="color:var(--primary);"></i> 
                    Community Members
                </h1>
                <p class="section-subtitle" style="margin-bottom:0;">
                    Manage registered users and system administrators.
                </p>
            </div>
            
            <button type="button" class="btn btn-primary" onclick="openUserModal(null);">
                <i class="fas fa-user-plus"></i> Add Member
            </button>

        </div>

        <div style="overflow-x:auto;">
            <table class="history-table">
                
                <thead>
                    <tr>
                        <th>Member Name</th>
                        <th>Email Address</th>
                        <th>Role</th>
                        <th>Date Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                
                <tbody>
                <?php
                $users_q = mysqli_query($conn, "SELECT * FROM users ORDER BY id DESC");
                
                if($users_q && mysqli_num_rows($users_q) > 0) {
                    while($u = mysqli_fetch_assoc($users_q)) {
                        
                        $uName = $u[$u_name_field] ?? 'Unknown';
                        $u_date2 = isset($u['created_at']) ? date('M j, Y', strtotime($u['created_at'])) : 'Recent';
                ?>
                    <tr>
                        <td>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div class="avatar-small" style="width:28px; height:28px; font-size:0.7rem;">
                                    <?php echo strtoupper(substr($uName,0,1)); ?>
                                </div>
                                <span><?php echo htmlspecialchars($uName); ?></span>
                            </div>
                        </td>
                        
                        <td>
                            <?php echo htmlspecialchars($u['email'] ?? ''); ?>
                        </td>
                        
                        <td>
                            <span 
                                class="badge <?php echo (($u['role'] ?? '') == 'Admin') ? 'badge-pending' : 'badge-gray'; ?>" 
                                style="<?php if(($u['role']??'')=='Admin') echo 'background:rgba(245, 158, 11, 0.2); color:#F59E0B;'; ?>"
                            >
                                <?php echo htmlspecialchars($u['role'] ?? 'Visitor'); ?>
                            </span>
                        </td>
                        
                        <td>
                            <?php echo $u_date2; ?>
                        </td>
                        
                        <td>
                            <div style="display:flex; gap:8px;">
                                
                                <button 
                                    type="button" 
                                    class="btn btn-secondary" 
                                    style="padding:6px 12px; font-size:0.8rem;"
                                    data-id="<?php echo $u['id']; ?>"
                                    data-name="<?php echo htmlspecialchars($uName, ENT_QUOTES); ?>"
                                    data-email="<?php echo htmlspecialchars($u['email'] ?? '', ENT_QUOTES); ?>"
                                    data-role="<?php echo htmlspecialchars($u['role'] ?? 'Visitor', ENT_QUOTES); ?>"
                                    onclick="openUserModal(this)"
                                >
                                    <i class="fas fa-edit"></i>
                                </button>
                                
                                <form method="POST" onsubmit="return confirm('WARNING: Are you sure you want to delete this user?');" style="margin:0;">
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    <button 
                                        type="submit" 
                                        name="delete_user" 
                                        class="btn btn-danger" 
                                        style="padding:6px 12px; font-size:0.8rem;"
                                    >
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>

                            </div>
                        </td>
                    </tr>
                <?php
                    }
                } else {
                    echo "<tr><td colspan='5' style='text-align:center; padding:30px; color:var(--text-muted);'>No users found.</td></tr>";
                }
                ?>
                </tbody>

            </table>
        </div>

    </div>

</section>


<!-- =========================
     PET PROFILE MODAL
========================= -->
<div id="petModal" class="sf-modal-overlay">
    
    <div class="sf-modal-container">
        
        <div class="modal-header">
            <h2 id="modal-title" style="font-size: 1.8rem; font-weight: 900; margin: 0; color: var(--text-dark);">
                <i class="fas fa-paw" style="color:var(--primary);"></i> 
                Pet Profile
            </h2>
            <span class="sf-modal-close" onclick="closePetModal()">&times;</span>
        </div>

        <div class="modal-body">
            
            <div class="profile-tabs" id="modal-tabs" style="display:none; margin-bottom: 30px;">
                <div class="ptab active" onclick="switchModalTab('edit')">
                    <i class="fas fa-edit"></i> Details & Edit
                </div>
                <div class="ptab" onclick="switchModalTab('history')">
                    <i class="fas fa-history"></i> History Log
                </div>
            </div>

            <div id="modal-tab-edit">
                
                <form method="POST" enctype="multipart/form-data">
                    
                    <input type="hidden" name="edit_pet_id" id="modal-id">

                    <div class="form-group" id="img-preview-group" style="display:none; text-align:center; margin-bottom:25px;">
                        <img 
                            id="modal-preview-img" 
                            src="" 
                            style="width:140px; height:140px; border-radius:50%; object-fit:cover; border:4px solid var(--badge-success-bg); box-shadow: var(--shadow);"
                        >
                    </div>

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label>Upload New Photo</label>
                        <input 
                            type="file" 
                            name="pet_image" 
                            accept="image/*" 
                            style="padding: 10px; background: transparent; border: 2px dashed var(--border-color); margin-top: 8px;"
                        >
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Pet Name</label>
                            <input type="text" name="pet_name" id="modal-name" required>
                        </div>
                        <div class="form-group">
                            <label>Age (Years)</label>
                            <input type="number" name="age" id="modal-age" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Species</label>
                            <select name="species" id="modal-species" required>
                                <option value="Dog">Dog</option>
                                <option value="Cat">Cat</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Specific Breed</label>
                            <input type="text" name="breed" id="modal-breed" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Gender</label>
                            <select name="gender" id="modal-gender">
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Size</label>
                            <select name="size" id="modal-size">
                                <option value="Small">Small</option>
                                <option value="Medium">Medium</option>
                                <option value="Large">Large</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Activity Level</label>
                            <select name="activity_level" id="modal-activity">
                                <option value="Low">Low</option>
                                <option value="Moderate">Moderate</option>
                                <option value="High">High</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Temperament</label>
                            <select name="temperament" id="modal-temperament">
                                <option value="Friendly">Friendly</option>
                                <option value="Needs Observation">Needs Observation</option>
                                <option value="Not for Kids">Not Recommended for Kids</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group" id="status-group" style="display:none;">
                            <label>Lifecycle Status</label>
                            <select name="status" id="modal-status">
                                <option value="Intake">Intake</option>
                                <option value="Available">Available</option>
                                <option value="Medical Hold">Medical Hold</option>
                                <option value="Adopted">Adopted</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Vaccinated?</label>
                            <select name="vaccinated" id="modal-vaccine">
                                <option value="Yes">Yes</option>
                                <option value="No">No / Unknown</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top:10px;">
                        <label>Detailed Description</label>
                        <textarea name="description" id="modal-description" rows="3" required></textarea>
                    </div>

                    <div style="display:flex; gap:15px; margin-top:25px; border-top: 1px dashed var(--border-color); padding-top: 25px;">
                        <button 
                            type="submit" 
                            name="add_pet" 
                            id="modal-submit-add" 
                            class="btn btn-primary" 
                            style="flex:1; padding:15px; font-size:1.05rem;"
                        >
                            <i class="fas fa-save"></i> Register Pet
                        </button>
                        
                        <button 
                            type="submit" 
                            name="edit_pet" 
                            id="modal-submit-edit" 
                            class="btn btn-primary" 
                            style="flex:1; display:none; padding:15px; font-size:1.05rem;"
                        >
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </div>
                </form>

                <form method="POST" id="form-delete" onsubmit="return confirm('WARNING: Permanently delete this pet?');" style="margin-top:15px; display:none;">
                    <input type="hidden" name="pet_id" id="modal-del-id">
                    <button 
                        type="submit" 
                        name="delete_pet" 
                        class="btn btn-outline" 
                        style="width:100%; border-color:var(--danger-light); color:var(--danger);"
                    >
                        <i class="fas fa-trash"></i> Delete Record
                    </button>
                </form>

            </div>

            <div id="modal-tab-history" style="display:none;">
                <div class="timeline" id="history-container"></div>
            </div>

        </div>

    </div>

</div>


<!-- =========================
     STAFF MODAL
========================= -->
<div id="staffModal" class="sf-modal-overlay">
    
    <div class="sf-modal-container" style="max-width:700px;">
        
        <div class="modal-header">
            <h2 style="font-size: 1.8rem; font-weight: 900; margin: 0; color: var(--text-dark);">
                <i class="fas fa-user-md" style="color:var(--secondary);"></i> 
                Staff & Vets
            </h2>
            <span class="sf-modal-close" onclick="closeStaffModal()">&times;</span>
        </div>
        
        <div class="modal-body" style="padding:0;">
            <table class="history-table" style="border:none; border-radius:0;">
                
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                
                <tbody>
                    <?php
                    $staff_q = mysqli_query($conn, "SELECT * FROM staff_attendance ORDER BY id DESC");
                    
                    if($staff_q) {
                        
                        while($stf = mysqli_fetch_assoc($staff_q)) {
                            
                            $s_badge = ($stf['status'] == 'Present') ? 'badge-success' : 'badge-gray';
                            $toggle_status = ($stf['status'] == 'Present') ? 'Offline' : 'Present';
                            $btn_class = ($stf['status'] == 'Present') ? 'btn-outline' : 'btn-success';
                    ?>
                    <tr>
                        <td style="font-weight:800;">
                            <?php echo htmlspecialchars($stf['name'] ?? ''); ?>
                        </td>
                        
                        <td style="font-size:0.9rem; color:var(--text-muted); font-weight:700;">
                            <?php echo htmlspecialchars($stf['role'] ?? ''); ?>
                        </td>
                        
                        <td>
                            <span class="badge <?php echo $s_badge; ?>">
                                <?php echo htmlspecialchars($stf['status'] ?? ''); ?>
                            </span>
                        </td>
                        
                        <td>
                            <form method="POST" style="margin:0;">
                                <input type="hidden" name="staff_id" value="<?php echo $stf['id']; ?>">
                                <input type="hidden" name="staff_status" value="<?php echo $toggle_status; ?>">
                                <button 
                                    type="submit" 
                                    name="update_staff" 
                                    class="btn <?php echo $btn_class; ?>" 
                                    style="padding:6px 12px; font-size:0.8rem; <?php if($btn_class==='btn-outline') echo 'border-color:var(--danger-light); color:var(--danger);'; ?>"
                                >
                                    Mark <?php echo $toggle_status; ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php 
                        } 
                    } 
                    ?>
                </tbody>

            </table>
        </div>

    </div>

</div>

<!-- =========================
     USER MODAL
========================= -->
<div id="userModal" class="sf-modal-overlay">
    
    <div class="sf-modal-container" style="max-width:550px;">
        
        <div class="modal-header">
            <h2 id="user-modal-title" style="font-size: 1.8rem; font-weight: 900; margin: 0; color: var(--text-dark);">
                <i class="fas fa-user-plus" style="color:var(--primary);"></i> 
                Register Member
            </h2>
            <span class="sf-modal-close" onclick="closeUserModal()">&times;</span>
        </div>
        
        <div class="modal-body">
            
            <form method="POST">
                
                <input type="hidden" name="edit_user_id" id="user-modal-id">
                
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="user_name" id="user-modal-name" required>
                </div>
                
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="user_email" id="user-modal-email" required>
                </div>
                
                <div class="form-group">
                    <label>Role</label>
                    <select name="user_role" id="user-modal-role" required>
                        <option value="Visitor">Visitor</option>
                        <option value="Adopter">Adopter</option>
                        <option value="Admin">Admin</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>
                        Password 
                        <span style="color:var(--text-light); font-weight:600; text-transform:none; font-size:0.75rem;">
                            (Leave blank to keep current)
                        </span>
                    </label>
                    <input type="password" name="user_password" id="user-modal-password">
                </div>
                
                <div style="display:flex; gap:15px; margin-top:30px; border-top: 1px dashed var(--border-color); padding-top: 25px;">
                    
                    <button 
                        type="submit" 
                        name="add_user" 
                        id="user-submit-add" 
                        class="btn btn-primary" 
                        style="flex:1; padding:15px; font-size:1.05rem;"
                    >
                        <i class="fas fa-save"></i> Register Member
                    </button>
                    
                    <button 
                        type="submit" 
                        name="edit_user" 
                        id="user-submit-edit" 
                        class="btn btn-primary" 
                        style="flex:1; display:none; padding:15px; font-size:1.05rem;"
                    >
                        <i class="fas fa-save"></i> Update Profile
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

<!-- =========================
     RESCUE EDIT MODAL
========================= -->
<div id="rescueModal" class="sf-modal-overlay">
    
    <div class="sf-modal-container" style="max-width:550px;">
        
        <div class="modal-header">
            <h2 style="font-size: 1.8rem; font-weight: 900; margin: 0; color: var(--text-dark);">
                <i class="fas fa-edit" style="color:var(--secondary);"></i> 
                Edit Rescue Report
            </h2>
            <span class="sf-modal-close" onclick="closeRescueModal()">&times;</span>
        </div>
        
        <div class="modal-body">
            
            <form method="POST">
                
                <input type="hidden" name="edit_report_id" id="rescue-modal-id">
                
                <div class="form-group">
                    <label>Location</label>
                    <input type="text" name="rep_location" id="rescue-modal-loc" required>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="rep_description" id="rescue-modal-desc" rows="3" required></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Reporter Name</label>
                        <input type="text" name="rep_reporter" id="rescue-modal-rep">
                    </div>
                    <div class="form-group">
                        <label>Contact Info</label>
                        <input type="text" name="rep_contact" id="rescue-modal-con">
                    </div>
                </div>
                
                <div style="margin-top:30px; border-top: 1px dashed var(--border-color); padding-top: 25px;">
                    <button 
                        type="submit" 
                        name="edit_rescue_report" 
                        class="btn btn-primary" 
                        style="width:100%; padding:15px; font-size:1.05rem;"
                    >
                        <i class="fas fa-save"></i> Update Report Info
                    </button>
                </div>

            </form>

        </div>

    </div>

</div>


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
    
    updateThemeUI(
        document.documentElement.getAttribute('data-theme')
    );

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
        nav.classList.toggle('active-menu');
    }

/* =========================
   MODAL HELPER FUNCTIONS 
========================= */
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.style.display = 'block';
    document.body.style.overflow = "hidden"; 
    
    setTimeout(() => {
        modal.classList.add("show");
    }, 10);
}

function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.classList.remove("show"); 
    document.body.style.overflow = "auto"; 
    
    setTimeout(() => {
        modal.style.display = "none";
    }, 300);
}

/* =========================
   RESCUE MODAL
========================= */
function openRescueModal(element) {
    document.getElementById('rescue-modal-id').value = 
        element.getAttribute('data-id');
        
    document.getElementById('rescue-modal-loc').value = 
        element.getAttribute('data-loc');
        
    document.getElementById('rescue-modal-desc').value = 
        element.getAttribute('data-desc');
        
    document.getElementById('rescue-modal-rep').value = 
        element.getAttribute('data-rep');
        
    document.getElementById('rescue-modal-con').value = 
        element.getAttribute('data-con');
        
    showModal('rescueModal');
}

function closeRescueModal() { 
    hideModal('rescueModal'); 
}

/* =========================
   USER MODAL
========================= */
function openUserModal(element) {
    
    if (element) {
        
        document.getElementById('user-modal-title').innerHTML = 
            '<i class="fas fa-user-edit" style="color:var(--primary);"></i> Edit Member';
            
        document.getElementById('user-modal-id').value = 
            element.getAttribute('data-id');
            
        document.getElementById('user-modal-name').value = 
            element.getAttribute('data-name');
            
        document.getElementById('user-modal-email').value = 
            element.getAttribute('data-email');
            
        document.getElementById('user-modal-role').value = 
            element.getAttribute('data-role');
            
        document.getElementById('user-modal-password').required = false;
        
        document.getElementById('user-submit-add').style.display = 'none';
        document.getElementById('user-submit-edit').style.display = 'block';
        
    } else {
        
        document.getElementById('user-modal-title').innerHTML = 
            '<i class="fas fa-user-plus" style="color:var(--primary);"></i> Register New Member';
            
        document.getElementById('user-modal-id').value = "";
        document.getElementById('user-modal-name').value = "";
        document.getElementById('user-modal-email').value = "";
        document.getElementById('user-modal-role').value = "Visitor";
        document.getElementById('user-modal-password').required = true;
        
        document.getElementById('user-submit-add').style.display = 'block';
        document.getElementById('user-submit-edit').style.display = 'none';
        
    }
    
    showModal('userModal');
}

function closeUserModal() { 
    hideModal('userModal'); 
}

/* =========================
   STAFF MODAL
========================= */
function openStaffModal() { 
    showModal('staffModal'); 
}

function closeStaffModal() { 
    hideModal('staffModal'); 
}

/* =========================
   PET MODAL
========================= */
function openPetModal(element) {
    
    if (element) {
        
        document.getElementById('modal-tabs').style.display = 'flex';
        switchModalTab('edit');

        document.getElementById('modal-title').innerHTML = 
            '<i class="fas fa-paw" style="color:var(--primary);"></i> ' 
            + element.getAttribute('data-name') 
            + "'s Profile";
            
        document.getElementById('modal-id').value = 
            element.getAttribute('data-id');
            
        document.getElementById('modal-del-id').value = 
            element.getAttribute('data-id');
            
        document.getElementById('modal-name').value = 
            element.getAttribute('data-name');
            
        document.getElementById('modal-species').value = 
            element.getAttribute('data-species');
            
        document.getElementById('modal-breed').value = 
            element.getAttribute('data-breed');
            
        document.getElementById('modal-age').value = 
            element.getAttribute('data-age');
            
        document.getElementById('modal-gender').value = 
            element.getAttribute('data-gender');
            
        document.getElementById('modal-size').value = 
            element.getAttribute('data-size');
            
        document.getElementById('modal-activity').value = 
            element.getAttribute('data-activity');
            
        document.getElementById('modal-temperament').value = 
            element.getAttribute('data-temperament');
            
        document.getElementById('modal-vaccine').value = 
            element.getAttribute('data-vaccine');
            
        document.getElementById('modal-status').value = 
            element.getAttribute('data-status');
            
        document.getElementById('modal-description').value = 
            element.getAttribute('data-desc');

        var imgSrc = element.getAttribute('data-image');
        document.getElementById('img-preview-group').style.display = 'block';
        document.getElementById('modal-preview-img').src = imgSrc;
        
        document.getElementById('modal-submit-add').style.display = 'none';
        document.getElementById('modal-submit-edit').style.display = 'block';
        document.getElementById('form-delete').style.display = 'block';
        document.getElementById('status-group').style.display = 'block';

        var hiddenHistory = element.querySelector('.hidden-history-data');
        document.getElementById('history-container').innerHTML = 
            hiddenHistory ? hiddenHistory.innerHTML : '';
            
    } else {
        
        document.getElementById('modal-title').innerHTML = 
            '<i class="fas fa-plus-circle" style="color:var(--primary);"></i> Register New Rescue';
            
        document.getElementById('modal-tabs').style.display = 'none';
        switchModalTab('edit');

        document.getElementById('modal-id').value = "";
        document.getElementById('modal-name').value = "";
        document.getElementById('modal-species').value = "Dog";
        document.getElementById('modal-breed').value = "";
        document.getElementById('modal-age').value = "";
        document.getElementById('modal-gender').value = "Male";
        document.getElementById('modal-size').value = "Small";
        document.getElementById('modal-activity').value = "Low";
        document.getElementById('modal-temperament').value = "Friendly";
        document.getElementById('modal-vaccine').value = "No";
        document.getElementById('modal-description').value = "";

        document.getElementById('status-group').style.display = 'none';
        document.getElementById('img-preview-group').style.display = 'none';
        document.getElementById('form-delete').style.display = 'none';
        
        document.getElementById('modal-submit-add').style.display = 'block';
        document.getElementById('modal-submit-edit').style.display = 'none';
        
    }
    
    showModal('petModal');
}

function closePetModal() { 
    hideModal('petModal'); 
}

/* =========================
   PET MODAL TABS
========================= */
function switchModalTab(tab) {
    
    var tabs = document.querySelectorAll('#modal-tabs .ptab');
    
    for(var i = 0; i < tabs.length; i++) { 
        tabs[i].classList.remove('active'); 
    }

    if(tab === 'edit') {
        
        tabs[0].classList.add('active');
        document.getElementById('modal-tab-edit').style.display = 'block';
        document.getElementById('modal-tab-history').style.display = 'none';
        
    } else {
        
        tabs[1].classList.add('active');
        document.getElementById('modal-tab-edit').style.display = 'none';
        document.getElementById('modal-tab-history').style.display = 'block';
        
    }
}

/* =========================
   RESCUE TABS
========================= */
function switchRescueTab(tab) {
    
    if(tab === 'active') {
        
        document.getElementById('tab-btn-active').classList.add('active');
        document.getElementById('tab-btn-history').classList.remove('active');
        document.getElementById('view-active-reports').style.display = 'block';
        document.getElementById('view-history-reports').style.display = 'none';
        
    } else {
        
        document.getElementById('tab-btn-history').classList.add('active');
        document.getElementById('tab-btn-active').classList.remove('active');
        document.getElementById('view-active-reports').style.display = 'none';
        document.getElementById('view-history-reports').style.display = 'block';
        
    }
}

/* =========================
   CLOSE MODAL WHEN CLICKING OUTSIDE
========================= */
window.addEventListener('click', function(event) {
    
    var petModal = document.getElementById('petModal');
    var staffModal = document.getElementById('staffModal');
    var userModal = document.getElementById('userModal');
    var rescueModal = document.getElementById('rescueModal');

    if (event.target === petModal) {
        closePetModal();
    }
    
    if (event.target === staffModal) {
        closeStaffModal();
    }
    
    if (event.target === userModal) {
        closeUserModal();
    }
    
    if (event.target === rescueModal) {
        closeRescueModal();
    }
    
});

(function(){
const sections=document.querySelectorAll('section.main-container');
sections.forEach(section=>{
if(section.querySelector('.admin-tools'))return;
const tables=Array.from(section.querySelectorAll('table'));
if(!tables.length)return;
const tool=document.createElement('div');tool.className='admin-tools';
const search=document.createElement('input');search.type='search';search.placeholder='Search records in this section...';search.setAttribute('aria-label','Search section records');
const reset=document.createElement('button');reset.type='button';reset.textContent='Clear Search';
const count=document.createElement('span');count.className='admin-search-count';
tool.append(search,reset,count);
const container=section.querySelector('.container');if(!container)return;
const heading=container.querySelector('h1, h2');if(heading&&heading.parentElement){heading.parentElement.insertAdjacentElement('afterend',tool)}else{container.prepend(tool)}
function filter(){const term=search.value.trim().toLocaleLowerCase();let visible=0,total=0;
tables.forEach(table=>{table.querySelectorAll('tbody tr').forEach(row=>{total++;const ok=row.textContent.toLocaleLowerCase().includes(term);row.hidden=!ok;if(ok)visible++})});count.textContent=visible+' of '+total+' records';}
search.addEventListener('input',filter);reset.addEventListener('click',()=>{search.value='';filter();search.focus()});filter();
});
document.addEventListener('keydown',e=>{if(e.key==='Escape'){document.querySelectorAll('.modal.show').forEach(m=>{m.classList.remove('show');m.style.display='none'});document.body.style.overflow='';}});
})();
</script>


<div class="admin-profile-overlay" id="adminProfileOverlay" role="dialog" aria-modal="true" aria-labelledby="adminProfileTitle" onclick="if(event.target===this)closeAdminProfile()">
  <div class="admin-profile-panel">
    <button type="button" class="admin-profile-close" aria-label="Close profile" onclick="closeAdminProfile()">&times;</button>
    <div class="admin-profile-header">
      <div class="admin-profile-photo"><?php if($adminPhoto): ?><img src="<?php echo $adminEscape($adminPhoto); ?>" alt="Admin profile photo" onerror="this.style.display='none'"/><?php else: ?><?php echo $adminEscape($adminInitials); ?><?php endif; ?></div>
      <div><h2 id="adminProfileTitle"><?php echo $adminEscape($adminDisplayName); ?></h2><p><i class="fas fa-shield-alt"></i> Administrator • Logged in</p><p><?php echo $adminEscape($adminProfile['email'] ?? ''); ?></p></div>
    </div>
    <?php if ($adminProfileError): ?><p class="admin-profile-notice"><?php echo $adminEscape($adminProfileError); ?></p><?php endif; ?>
    <form method="post" enctype="multipart/form-data" action="admin_dashboard.php?tab=<?php echo urlencode($activeTab); ?>">
      <input type="hidden" name="admin_profile_csrf" value="<?php echo $adminEscape($_SESSION['admin_profile_csrf']); ?>">
      <label for="admin_profile_name">Full name</label><input id="admin_profile_name" name="admin_profile_name" maxlength="100" value="<?php echo $adminEscape($adminDisplayName); ?>" required>
      <label for="admin_profile_email">Email address</label><input id="admin_profile_email" type="email" value="<?php echo $adminEscape($adminProfile['email'] ?? ''); ?>" readonly aria-readonly="true">
      <label for="admin_profile_phone">Contact number</label><input id="admin_profile_phone" name="admin_profile_phone" maxlength="30" value="<?php echo $adminEscape($adminProfile['phone'] ?? ''); ?>" placeholder="Optional contact number">
      <label for="admin_profile_bio">About me</label><textarea id="admin_profile_bio" name="admin_profile_bio" rows="3" maxlength="1000" placeholder="Tell your team about your role"><?php echo $adminEscape($adminProfile['bio'] ?? ''); ?></textarea>
      <label for="admin_profile_photo">Profile picture</label><input id="admin_profile_photo" name="admin_profile_photo" type="file" accept="image/jpeg,image/png,image/webp"><p class="admin-profile-hint">JPG, PNG or WebP • Maximum 2 MB</p>
      <div class="admin-profile-actions"><button class="btn btn-primary" type="submit" name="save_admin_profile" value="1"><i class="fas fa-save"></i> Save Profile</button><a class="btn btn-outline" href="?action=logout"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </form>
  </div>
</div>
<script>
function openAdminProfile(){const el=document.getElementById('adminProfileOverlay');el.classList.add('is-open');document.body.style.overflow='hidden';el.querySelector('.admin-profile-close').focus()}
function closeAdminProfile(){document.getElementById('adminProfileOverlay').classList.remove('is-open');document.body.style.overflow=''}
document.addEventListener('keydown',function(e){if(e.key==='Escape'&&document.getElementById('adminProfileOverlay').classList.contains('is-open'))closeAdminProfile()});
<?php if ($adminProfileError): ?>document.addEventListener('DOMContentLoaded',openAdminProfile);<?php endif; ?>
</script>
<script>
const adminShelterRecords=<?php echo json_encode($shelterAdminRows,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]'; ?>;
const shelterDays=['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
function shelterClock(hours){let schedule;try{schedule=typeof hours==='string'?JSON.parse(hours||'{}'):hours;}catch(e){return {open:false,label:'Hours unavailable',next:''};}if(!schedule||!Object.keys(schedule).length)return {open:false,label:'Hours not set',next:''};const parts=new Intl.DateTimeFormat('en-US',{timeZone:'Asia/Manila',weekday:'long',hour:'2-digit',minute:'2-digit',hourCycle:'h23'}).formatToParts(new Date());const get=t=>parts.find(p=>p.type===t)?.value||'';const day=get('weekday'),now=get('hour')+':'+get('minute'),d=schedule[day];const open=Boolean(d?.open&&d.from&&d.to&&now>=d.from&&now<d.to);return {open,label:open?'Open now':'Closed now',next:d?.open?(d.from+' – '+d.to):'Closed today'};}
function updateShelterHours(){document.querySelectorAll('[data-shelter-hours]').forEach(el=>{const s=shelterClock(el.dataset.shelterHours);el.textContent=s.label+' · '+s.next;el.classList.toggle('open',s.open);el.classList.toggle('closed',!s.open&&s.label==='Closed now');});}
function setShelterDayControls(f,schedule){for(const day of ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday']){const d=schedule?.[day];const check=f.querySelector('[name="day_open['+day+']"]'),from=f.querySelector('[name="open_time['+day+']"]'),to=f.querySelector('[name="close_time['+day+']"]');check.checked=Boolean(d?.open);from.value=d?.from||'08:00';to.value=d?.to||'17:00';}}
function resetShelterAdminForm(){const f=document.getElementById('shelter-admin-form');f.reset();f.elements.shelter_action.value='add';f.elements.shelter_id.value='0';document.getElementById('shelter-form-title').textContent='Add Animal Shelter';if(window.adminShelterSelection)window.adminShelterSelection.remove();window.adminShelterSelection=null;const place=document.getElementById('adminShelterPlaceName');if(place)place.textContent='Pumili ng lugar sa mapa';if(adminShelterGeocodeController)adminShelterGeocodeController.abort();clearTimeout(adminShelterGeocodeTimer);}
function editShelterAdmin(s){const f=document.getElementById('shelter-admin-form');for(const k of ['shelter_name','address','city','province','latitude','longitude','contact_number','contact_email','description','animal_types','capacity','available_slots','verification_status']){if(f.elements[k])f.elements[k].value=s[k]??'';}f.elements.rescue_available.checked=Number(s.rescue_available)===1;let schedule={};try{schedule=JSON.parse(s.opening_hours||'{}')}catch(e){}setShelterDayControls(f,schedule);f.elements.shelter_action.value='edit';f.elements.shelter_id.value=s.id;document.getElementById('shelter-form-title').textContent='Edit Shelter #'+s.id;if(s.latitude!==null&&s.longitude!==null&&window.adminShelterMap){selectAdminShelterPoint(Number(s.latitude),Number(s.longitude));window.adminShelterMap.setView([Number(s.latitude),Number(s.longitude)],13);}document.getElementById('admin-shelters').scrollIntoView({behavior:'smooth'});}
let adminShelterGeocodeController=null,adminShelterGeocodeTimer=null,adminShelterGeocodeSequence=0;
function shelterPlaceParts(a){const barangay=a.suburb||a.neighbourhood||a.quarter||a.village||a.hamlet||a.residential||a.locality||'';const city=a.city||a.town||a.municipality||a.city_district||'';const province=a.province||a.state||a.region||'';return {barangay,city,province};}
function resolveAdminShelterPlace(lat,lng){const label=document.getElementById('adminShelterPlaceName'),msg=document.getElementById('adminShelterMapMessage');if(!label||!msg)return;label.textContent='Hinahanap ang pangalan ng lugar…';clearTimeout(adminShelterGeocodeTimer);if(adminShelterGeocodeController)adminShelterGeocodeController.abort();const seq=++adminShelterGeocodeSequence;adminShelterGeocodeTimer=setTimeout(async()=>{const controller=new AbortController();adminShelterGeocodeController=controller;try{const url='https://nominatim.openstreetmap.org/reverse?format=jsonv2&addressdetails=1&zoom=18&lat='+encodeURIComponent(lat)+'&lon='+encodeURIComponent(lng);const response=await fetch(url,{signal:controller.signal,headers:{'Accept':'application/json','Accept-Language':'en'}});if(!response.ok)throw Error('Lookup unavailable');const data=await response.json();if(seq!==adminShelterGeocodeSequence)return;const a=data.address||{},parts=shelterPlaceParts(a);const exact=[parts.barangay,parts.city,parts.province].filter((x,i,arr)=>x&&arr.indexOf(x)===i).join(', ');label.textContent=exact||data.display_name||'Hindi matukoy ang pangalan ng lugar';msg.textContent='GPS: '+lat.toFixed(5)+', '+lng.toFixed(5);const f=document.getElementById('shelter-admin-form');if(f){if(!f.elements.city.value.trim()&&parts.city)f.elements.city.value=parts.city;if(!f.elements.province.value.trim()&&parts.province)f.elements.province.value=parts.province;if(!f.elements.address.value.trim()&&parts.barangay)f.elements.address.value=parts.barangay;}if(window.adminShelterSelection){const popup=document.createElement('div');const name=document.createElement('strong');name.textContent=exact||data.display_name||'Selected location';popup.append(name,document.createElement('br'),document.createTextNode('I-drag ang marker para baguhin'));window.adminShelterSelection.bindPopup(popup).openPopup();}}catch(err){if(err.name==='AbortError'||seq!==adminShelterGeocodeSequence)return;label.textContent='Hindi makuha ang pangalan ng lugar';msg.textContent='GPS: '+lat.toFixed(5)+', '+lng.toFixed(5)+' · I-check ang address manually.';}},450);}
function selectAdminShelterPoint(lat,lng){const f=document.getElementById('shelter-admin-form');f.elements.latitude.value=lat.toFixed(7);f.elements.longitude.value=lng.toFixed(7);if(window.adminShelterMap&&typeof L!=='undefined'){if(window.adminShelterSelection)window.adminShelterSelection.remove();window.adminShelterSelection=L.marker([lat,lng],{draggable:true}).addTo(window.adminShelterMap).bindPopup('Selected location · Drag to adjust').openPopup();window.adminShelterSelection.on('dragend',e=>selectAdminShelterPoint(e.target.getLatLng().lat,e.target.getLatLng().lng));}document.getElementById('adminShelterMapMessage').textContent='GPS: '+lat.toFixed(5)+', '+lng.toFixed(5);resolveAdminShelterPlace(lat,lng);}
function initAdminShelterMap(){updateShelterHours();setInterval(updateShelterHours,60000);const el=document.getElementById('adminShelterMap');if(!el)return;if(typeof L==='undefined'){el.textContent='Map unavailable. You can still enter coordinates manually.';return;}const map=L.map(el).setView([12.8797,121.774],6);window.adminShelterMap=map;L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'&copy; OpenStreetMap contributors',maxZoom:19}).addTo(map);map.on('click',e=>selectAdminShelterPoint(e.latlng.lat,e.latlng.lng));const bounds=[];adminShelterRecords.forEach(s=>{const lat=Number(s.latitude),lng=Number(s.longitude);if(s.latitude===null||s.longitude===null||!Number.isFinite(lat)||!Number.isFinite(lng)||Math.abs(lat)>90||Math.abs(lng)>180)return;const h=shelterClock(s.opening_hours);const marker=L.marker([lat,lng]).addTo(map);const popup=document.createElement('div');const title=document.createElement('strong');title.textContent=s.shelter_name;popup.append(title,document.createElement('br'),document.createTextNode(s.city+', '+s.province),document.createElement('br'),document.createTextNode('Verification: '+s.verification_status),document.createElement('br'),document.createTextNode(h.label+' · '+h.next));const btn=document.createElement('button');btn.type='button';btn.textContent='Edit shelter';btn.style.marginTop='8px';btn.onclick=()=>{map.closePopup();editShelterAdmin(s);};popup.append(document.createElement('br'),btn);marker.bindPopup(popup);bounds.push([lat,lng]);});if(bounds.length)map.fitBounds(bounds,{padding:[25,25],maxZoom:12});document.getElementById('adminShelterLocate').addEventListener('click',()=>{if(!navigator.geolocation){document.getElementById('adminShelterMapMessage').textContent='Location not supported.';return;}navigator.geolocation.getCurrentPosition(p=>{map.setView([p.coords.latitude,p.coords.longitude],14);selectAdminShelterPoint(p.coords.latitude,p.coords.longitude);},()=>document.getElementById('adminShelterMapMessage').textContent='Location unavailable. Click on the map instead.',{timeout:12000});});const f=document.getElementById('shelter-admin-form');for(const name of ['latitude','longitude'])f.elements[name].addEventListener('change',()=>{const lat=Number(f.elements.latitude.value),lng=Number(f.elements.longitude.value);if(f.elements.latitude.value&&f.elements.longitude.value&&Math.abs(lat)<=90&&Math.abs(lng)<=180){selectAdminShelterPoint(lat,lng);map.setView([lat,lng],13);}});document.querySelectorAll('.nav-link[href="?tab=shelters"]').forEach(n=>n.addEventListener('click',()=>setTimeout(()=>map.invalidateSize(),150)));if(new URLSearchParams(location.search).get('tab')==='shelters')setTimeout(()=>map.invalidateSize(),200);}
document.addEventListener('DOMContentLoaded',initAdminShelterMap);
</script>
</body>
</html>
