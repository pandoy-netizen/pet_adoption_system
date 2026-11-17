
<?php
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';

$pythonApiUrl = rtrim(
    getenv('PYTHON_AI_URL') ?: 'https://python-ai-production-0d406cb6.up.railway.app',
    '/'
);

function escapeHtml($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function showError($message, $status = 400)
{
    http_response_code($status);

    echo '<div style="
        padding:20px;
        margin:20px auto;
        max-width:600px;
        background:#fff1f2;
        color:#991b1b;
        border-radius:12px;
        font-family:Arial,sans-serif;
        text-align:center;
    ">';

    echo escapeHtml($message);

    echo '</div>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    showError('Invalid request method.', 405);
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    showError('Database connection unavailable.', 500);
}

$preferredSize = trim($_POST['size'] ?? '');

$maxAge = filter_var(
    $_POST['max_age'] ?? null,
    FILTER_VALIDATE_INT
);

$validSizes = ['Small', 'Medium', 'Large'];

$preferredSize = ucfirst(strtolower($preferredSize));

if (!in_array($preferredSize, $validSizes, true)) {
    showError('Please select a valid pet size.');
}

if (
    $maxAge === false ||
    $maxAge === null ||
    $maxAge < 0
) {
    showError('Please enter a valid maximum pet age.');
}

$adopter = [
    'living_space' => trim(
        $_POST['living_space'] ?? 'House'
    ),

    'children' => trim(
        $_POST['children'] ?? 'No'
    ),

    'experience' => trim(
        $_POST['experience'] ?? 'Beginner'
    ),

    'activity' => trim(
        $_POST['activity'] ?? 'Medium'
    ),

    'temperament' => trim(
        $_POST['temperament'] ?? 'Friendly'
    ),

    'target_species' => trim(
        $_POST['target_species'] ?? ''
    )
];

try {
    $stmt = $conn->prepare(
        "SELECT * FROM pets WHERE status = ?"
    );

    $status = 'Available';

    $stmt->bind_param('s', $status);
    $stmt->execute();

    $result = $stmt->get_result();

    $pets = [];

    while ($pet = $result->fetch_assoc()) {

        $petAge = isset($pet['age'])
            ? (float)$pet['age']
            : null;

        if ($petAge === null || $petAge > $maxAge) {
            continue;
        }

        $petSize = trim(
            (string)($pet['size'] ?? 'Medium')
        );

        if ($petSize === '') {
            $petSize = 'Medium';
        }

        if (
            strcasecmp($petSize, $preferredSize) !== 0
        ) {
            continue;
        }

        $petSpecies = trim(
            (string)($pet['species'] ?? '')
        );

        $petActivity = trim(
            (string)($pet['activity_level'] ?? 'Medium')
        );

        $petTemperament = trim(
            (string)($pet['temperament'] ?? 'Friendly')
        );

        $image = trim(
            (string)($pet['image'] ?? '')
        );

        if ($image === '') {
            $image = 'uploads/default-pet.jpg';
        } elseif (
            !preg_match('/^https?:\/\//i', $image)
            && strpos($image, 'uploads/') !== 0
        ) {
            $image = 'uploads/' . $image;
        }

        $pets[] = [
            'id' => (int)$pet['id'],
            'name' => (string)$pet['name'],
            'breed' => (string)($pet['breed'] ?? ''),
            'species' => $petSpecies,
            'age' => $petAge,
            'size' => $petSize,
            'activity_level' => $petActivity,
            'temperament' => $petTemperament,
            'display_image' => $image
        ];
    }

    $stmt->close();

} catch (Throwable $error) {

    error_log(
        'Pet recommendation DB error: ' .
        $error->getMessage()
    );

    showError(
        'Unable to retrieve available pets.',
        500
    );
}

if (empty($pets)) {

    echo '<div style="
        font-family:Arial,sans-serif;
        padding:25px;
        text-align:center;
    ">';

    echo '<h2>No matching pets found</h2>';

    echo '<p>No available pets match your selected age and size.</p>';

    echo '<a href="javascript:history.back()">Go Back</a>';

    echo '</div>';

    exit;
}

$payload = [
    'adopter' => $adopter,
    'pets' => $pets
];

$jsonPayload = json_encode(
    $payload,
    JSON_INVALID_UTF8_SUBSTITUTE
);

if ($jsonPayload === false) {
    showError('Unable to prepare AI request.', 500);
}

if (!function_exists('curl_init')) {
    showError('PHP cURL extension is not installed.', 500);
}

$ch = curl_init($pythonApiUrl . '/match');

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json'
    ],
    CURLOPT_POSTFIELDS => $jsonPayload,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 45,
    CURLOPT_FOLLOWLOCATION => false
]);

$response = curl_exec($ch);

$curlError = curl_error($ch);

$httpCode = curl_getinfo(
    $ch,
    CURLINFO_HTTP_CODE
);

curl_close($ch);

if ($response === false) {

    error_log(
        'Python AI connection error: ' .
        $curlError
    );

    showError(
        'Unable to connect to Python AI. Please try again.',
        502
    );
}

$data = json_decode($response, true);

if (
    $httpCode !== 200 ||
    !is_array($data) ||
    ($data['success'] ?? false) !== true
) {

    error_log(
        'Python AI API error: HTTP ' .
        $httpCode . ' ' .
        substr($response, 0, 1000)
    );

    showError(
        'Python AI could not process the recommendations.',
        502
    );
}

$recommendations = $data['matches'] ?? [];

if (!is_array($recommendations)) {
    showError('Invalid AI response format.', 502);
}

usort(
    $recommendations,
    function ($a, $b) {
        return (float)($b['match_score'] ?? 0)
            <=> (float)($a['match_score'] ?? 0);
    }
);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>AI Pet Recommendations</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 30px 15px;
    background: #fff7f1;
    font-family: Arial, sans-serif;
    color: #333;
}

.container {
    max-width: 1100px;
    margin: auto;
}

h1 {
    text-align: center;
    color: #ef7626;
    margin-bottom: 10px;
}

.subtitle {
    text-align: center;
    color: #777;
    margin-bottom: 35px;
}

.pet-grid {
    display: grid;
    grid-template-columns: repeat(
        auto-fit,
        minmax(260px, 1fr)
    );
    gap: 22px;
}

.pet-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
}

.pet-image {
    width: 100%;
    height: 210px;
    object-fit: cover;
}

.pet-content {
    padding: 20px;
}

.pet-name {
    margin: 0 0 8px;
    color: #333;
}

.pet-info {
    color: #777;
    font-size: 14px;
    margin: 6px 0;
}

.score {
    color: #ef7626;
    font-size: 25px;
    font-weight: bold;
    margin-top: 15px;
}

.match-label {
    display: inline-block;
    padding: 7px 12px;
    border-radius: 20px;
    background: #fff0df;
    color: #d96915;
    font-size: 13px;
    font-weight: bold;
    margin-top: 8px;
}

.reasons {
    margin-top: 15px;
    padding-left: 18px;
    font-size: 13px;
    color: #555;
    line-height: 1.6;
}

.back-btn {
    display: inline-block;
    margin-bottom: 25px;
    background: #ef7626;
    color: white;
    padding: 11px 20px;
    border-radius: 9px;
    text-decoration: none;
}

.empty {
    text-align: center;
    padding: 35px;
    background: white;
    border-radius: 12px;
}
</style>
</head>

<body>

<div class="container">

<a href="javascript:history.back()" class="back-btn">
    Back
</a>

<h1>AI Pet Recommendations</h1>

<p class="subtitle">
    Recommended pets based on your preferences
    and our trained AI compatibility model.
</p>

<?php if (empty($recommendations)): ?>

<div class="empty">
    <h2>No recommendations found</h2>
    <p>Try changing your preferences.</p>
</div>

<?php else: ?>

<div class="pet-grid">

<?php foreach ($recommendations as $pet): ?>

<?php
    $score = (float)($pet['match_score'] ?? 0);

    $label = $pet['match_label'] ?? 'Possible Match';

    $reasons = $pet['match_reasons'] ?? [];

    $petId = (int)($pet['id'] ?? 0);

    $petName = $pet['name'] ?? 'Pet';

    $petBreed = $pet['breed'] ?? 'Mixed Breed';

    $petSpecies = $pet['species'] ?? '';

    $petImage = $pet['image'] ?? 'uploads/default-pet.jpg';
?>

<div class="pet-card">

<img
    class="pet-image"
    src="<?php echo escapeHtml($petImage); ?>"
    alt="<?php echo escapeHtml($petName); ?>"
    loading="lazy"
>

<div class="pet-content">

<h2 class="pet-name">
    <?php echo escapeHtml($petName); ?>
</h2>

<p class="pet-info">
    Breed:
    <?php echo escapeHtml($petBreed); ?>
</p>

<p class="pet-info">
    Species:
    <?php echo escapeHtml($petSpecies); ?>
</p>

<div class="score">
    <?php echo number_format($score, 2); ?>%
</div>

<div class="match-label">
    <?php echo escapeHtml($label); ?>
</div>

<?php if (!empty($reasons) && is_array($reasons)): ?>

<ul class="reasons">

<?php foreach ($reasons as $reason): ?>

<li>
    <?php echo escapeHtml($reason); ?>
</li>

<?php endforeach; ?>

</ul>

<?php endif; ?>

</div>
</div>

<?php endforeach; ?>

</div>

<?php endif; ?>

</div>

</body>
</html>
