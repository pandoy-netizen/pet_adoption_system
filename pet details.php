<?php
// Note: Remove session_start() and DB connection here ONLY IF your 
// client_dashboard.php already has them at the very top of the file!

if(!isset($_GET['id'])){
    echo "<div class='container'><h2>Pet not found.</h2></div>";
    return; // Stops this file, but keeps dashboard loaded
}

$id = intval($_GET['id']);

// Assuming $conn is already established in client_dashboard.php
$sql = "SELECT * FROM pets WHERE id=$id";
$result = mysqli_query($conn,$sql);

if(mysqli_num_rows($result)==0){
    echo "<div class='container'><h2>Pet not found.</h2></div>";
    return;
}

$pet = mysqli_fetch_assoc($result);

$imagePath = "uploads/".$pet['image'];
if(empty($pet['image']) || !file_exists($imagePath)){
    $imagePath = "uploads/default.jpg";
}

/* HANDLE ADOPTION FORM */
if(isset($_POST['apply'])){
    $full_name = mysqli_real_escape_string($conn,$_POST['full_name']);
    $email = mysqli_real_escape_string($conn,$_POST['email']);
    $phone = mysqli_real_escape_string($conn,$_POST['phone']);
    $address = mysqli_real_escape_string($conn,$_POST['address']);
    $message = mysqli_real_escape_string($conn,$_POST['message']);

    $insert = "INSERT INTO adoption_applications
    (pet_id, full_name, email, phone, address, message)
    VALUES ($id,'$full_name','$email','$phone','$address','$message')";

    mysqli_query($conn,$insert);
    $success = "Application submitted successfully!";
}
?>

<style>
/* I kept ALL your exact styles for the pet details, just removed the body/header/footer styles */

/* ================= MAIN CONTAINER ================= */
.pet-details-container {
    width:90%;
    max-width:1200px;
    margin:60px auto;
}

/* ================= PET PROFILE ================= */
.profile-card {
    background:white;
    padding:40px;
    border-radius:20px;
    box-shadow:0 15px 35px rgba(0,0,0,0.08);
    margin-bottom:40px;
}
.profile-flex {
    display:flex;
    gap:40px;
    flex-wrap:wrap;
}
.pet-image {
    flex:1;
    min-width:320px;
}
.pet-image img {
    width:100%;
    border-radius:20px;
}
.pet-info {
    flex:1;
}
.status-badge {
    display:inline-block;
    padding:6px 15px;
    background:#28a745;
    color:white;
    border-radius:20px;
    font-size:14px;
    margin-bottom:15px;
}
.label {
    font-weight:bold;
    color:#d4a017;
}
.star {
    color:gold;
    font-size:20px;
}
.adopt-btn {
    display:inline-block;
    margin-top:20px;
    padding:12px 30px;
    border-radius:30px;
    background:#d4a017;
    color:white;
    text-decoration:none;
}

/* ================= SIMILAR PETS ================= */
.section-card {
    background:white;
    padding:40px;
    border-radius:20px;
    box-shadow:0 10px 25px rgba(0,0,0,0.08);
    margin-bottom:40px;
}
.similar-grid {
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:20px;
    margin-top:20px;
}
.similar-card {
    background:#fff;
    border-radius:15px;
    overflow:hidden;
    box-shadow:0 6px 18px rgba(0,0,0,0.08);
    text-align:center;
    padding-bottom: 15px;
}
.similar-card img {
    width:100%;
    height:160px;
    object-fit:cover;
}
.similar-card h4 {
    margin:10px 0;
}

/* ================= FORM ================= */
.adoption-form input, .adoption-form textarea {
    width:100%;
    padding:12px;
    margin-bottom:15px;
    border-radius:8px;
    border:1px solid #ccc;
    box-sizing: border-box;
}
.adoption-form button {
    background:#d4a017;
    color:white;
    border:none;
    padding:12px 25px;
    border-radius:25px;
    cursor:pointer;
}
.success {
    color:green;
    font-weight:bold;
}
</style>

<!-- Everything below is EXACTLY your code, just without the header/footer! -->

<div class="pet-details-container">

    <!-- PROFILE SECTION -->
    <div class="profile-card">
        <div class="profile-flex">

            <div class="pet-image">
                <img src="<?php echo htmlspecialchars($imagePath); ?>">
            </div>

            <div class="pet-info">
                <div class="status-badge">Available for Adoption</div>
                <h1><?php echo htmlspecialchars($pet['name']); ?></h1>

                <p><span class="label">Breed:</span> <?php echo htmlspecialchars($pet['breed']); ?></p>
                <p><span class="label">Age:</span> <?php echo $pet['age']; ?> years old</p>
                <p><span class="label">Characteristics:</span><br>
                <?php echo htmlspecialchars($pet['characteristics']); ?></p>

                <p><span class="label">Playfulness:</span>
                <?php
                for($i=1;$i<=5;$i++){
                    echo $i <= $pet['playfulness']
                    ? "<span class='star'>★</span>"
                    : "<span class='star' style='color:#ccc;'>★</span>";
                }
                ?>
                </p>

                <a href="#apply" class="adopt-btn">Apply for Adoption</a>
            </div>
        </div>
    </div>

    <!-- SIMILAR PETS -->
    <div class="section-card">
        <h2>Similar Pets</h2>

        <div class="similar-grid">
        <?php
        $breed = mysqli_real_escape_string($conn,$pet['breed']);
        $similar = mysqli_query($conn,"SELECT * FROM pets WHERE breed='$breed' AND id!=$id LIMIT 3");

        while($row=mysqli_fetch_assoc($similar)){
            $simImage="uploads/".$row['image'];
            if(empty($row['image']) || !file_exists($simImage)){
                $simImage="uploads/default.jpg";
            }
        ?>
            <div class="similar-card">
                <img src="<?php echo $simImage; ?>">
                <h4><?php echo htmlspecialchars($row['name']); ?></h4>
                <!-- IMPORTANT: Notice how this link now points to your dashboard! -->
                <a href="client_dashboard.php?page=pet_details&id=<?php echo $row['id']; ?>" class="adopt-btn" style="margin-top:0; padding: 8px 15px;">View</a>
            </div>
        <?php } ?>
        </div>
    </div>

    <!-- ADOPTION FORM -->
    <div class="section-card adoption-form" id="apply">
        <h2>Adoption Application</h2>
        <br>
        <?php if(isset($success)) echo "<p class='success'>$success</p>"; ?>

        <form method="POST">
            <input type="text" name="full_name" placeholder="Full Name" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="text" name="phone" placeholder="Phone Number" required>
            <textarea name="address" placeholder="Complete Address" required></textarea>
            <textarea name="message" placeholder="Why do you want to adopt this pet?" required></textarea>
            <button type="submit" name="apply">Submit Application</button>
        </form>
    </div>

</div>