<?php

$conn = mysqli_connect("localhost","root","","pet_adoption_rescue");

if(isset($_POST['submit'])){

$name=$_POST['name'];
$breed=$_POST['breed'];
$age=$_POST['age'];
$traits=$_POST['traits'];

$image=$_FILES['image']['name'];
$tmp=$_FILES['image']['tmp_name'];

move_uploaded_file($tmp,"uploads/".$image);

$sql="INSERT INTO pets
(name,breed,age,characteristics,image,status)
VALUES
('$name','$breed','$age','$traits','$image','pending')";

mysqli_query($conn,$sql);

$success="Rescue pet uploaded! Waiting for admin approval.";

}

?>

<!DOCTYPE html>
<html>
<head>

<title>Upload Rescue Pet</title>

<style>

body{
font-family:Segoe UI;
background:#f4f6f9;
}

.container{
width:500px;
margin:80px auto;
background:white;
padding:30px;
border-radius:10px;
}

input,textarea{
width:100%;
padding:10px;
margin-bottom:10px;
}

button{
background:#d4a017;
color:white;
border:none;
padding:10px 20px;
}

.success{
color:green;
}

</style>

</head>

<body>

<div class="container">

<h2>Rescue Pet Upload</h2>

<?php if(isset($success)) echo "<p class='success'>$success</p>"; ?>

<form method="POST" enctype="multipart/form-data">

<input type="text" name="name" placeholder="Pet Name" required>

<input type="text" name="breed" placeholder="Breed" required>

<input type="number" name="age" placeholder="Age" required>

<textarea name="traits" placeholder="Characteristics"></textarea>

<input type="file" name="image" required>

<button name="submit">Upload Rescue Pet</button>

</form>

</div>

</body>
</html>