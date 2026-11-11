<?php
$conn = mysqli_connect("localhost","root","","pet_adoption_rescue");

$sql = "SELECT * FROM pets WHERE status='Available'";
$result = mysqli_query($conn,$sql);
?>

<!DOCTYPE html>
<html>
<head>
<title>Adopt a Pet</title>
</head>
<body>

<h2>Available Pets</h2>

<?php while($row = mysqli_fetch_assoc($result)) { ?>
    <div style="border:1px solid #ccc; padding:15px; margin:15px;">
        <img src="uploads/<?php echo $row['image']; ?>" width="200"><br>
        <h3><?php echo $row['name']; ?></h3>
        <p><?php echo $row['breed']; ?> | <?php echo $row['age']; ?> years old</p>
        <a href="pet_details.php?id=<?php echo $row['id']; ?>">View Details</a>
    </div>
<?php } ?>

</body>
</html>