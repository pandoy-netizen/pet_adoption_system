<?php
include("config/db.php");

$preferred_size = $_POST['size'];
$max_age = $_POST['max_age'];

$sql = "SELECT * FROM pets WHERE status='Available'";
$result = $conn->query($sql);

$recommendations = [];

while($pet = $result->fetch_assoc()){

    $score = 0;

    if($pet['size'] == $preferred_size){
        $score += 40;
    }

    if($pet['age'] <= $max_age){
        $score += 30;
    }

    if($pet['gender'] == 'Female'){
        $score += 10;
    }

    $pet['score'] = $score;
    $recommendations[] = $pet;
}

usort($recommendations,function($a,$b){
    return $b['score'] - $a['score'];
});

foreach($recommendations as $pet){
    echo $pet['name']." - Match Score: ".$pet['score']."<br>";
}
?>