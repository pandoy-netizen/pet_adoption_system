<?php
session_start();
include("../config/db.php");

if($_SESSION['role'] != 'admin'){
    header("Location: ../index.php");
}

if(isset($_GET['approve'])){
    $id = $_GET['approve'];

    $conn->query("UPDATE adoption_requests SET status='Approved' WHERE id=$id");

    $conn->query("
        UPDATE pets 
        SET status='Adopted'
        WHERE id=(SELECT pet_id FROM adoption_requests WHERE id=$id)
    ");
}

if(isset($_GET['reject'])){
    $id = $_GET['reject'];
    $conn->query("UPDATE adoption_requests SET status='Rejected' WHERE id=$id");
}

$result = $conn->query("
    SELECT adoption_requests.*, pets.name AS pet_name, users.name AS user_name
    FROM adoption_requests
    JOIN pets ON pets.id = adoption_requests.pet_id
    JOIN users ON users.id = adoption_requests.user_id
");
?>

<h2>Adoption Requests</h2>

<?php while($row = $result->fetch_assoc()){ ?>

<div>
<b><?php echo $row['user_name']; ?></b> wants <b><?php echo $row['pet_name']; ?></b>
Status: <?php echo $row['status']; ?>

<a href="?approve=<?php echo $row['id']; ?>">Approve</a>
<a href="?reject=<?php echo $row['id']; ?>">Reject</a>
</div>

<?php } ?>