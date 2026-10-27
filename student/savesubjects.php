<?php


session_start();


$conn = new mysqli(
"localhost",
"root",
"",
"study_planner"
);



$user_id=$_SESSION['user_id'];



if(isset($_POST['subjects'])){


foreach($_POST['subjects'] as $subject){



$stmt=$conn->prepare(

"INSERT INTO subjects
(user_id,subject_name)
VALUES(?,?)"

);



$stmt->bind_param(
"is",
$user_id,
$subject
);



$stmt->execute();


}


}



header("Location: subject_topics.php");

exit();


?>