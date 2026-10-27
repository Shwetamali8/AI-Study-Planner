<?php

session_start();

if(!isset($_SESSION['user_id'])){
    header("Location: ../auth/login.php");
    exit();
}


$conn = new mysqli(
"localhost",
"root",
"",
"study_planner"
);


if($conn->connect_error){
    die("Database Error");
}



$result = $conn->query(
"SELECT * FROM master_subjects"
);


?>


<!DOCTYPE html>
<html>

<head>

<title>Select Subjects</title>


<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">


<style>

body{

background:#050816;
color:white;

}


.card{

background:#111827;
border-radius:20px;
padding:25px;

}


.form-check{

margin-bottom:15px;

}


</style>


</head>



<body>


<div class="container mt-5">


<h1 class="mb-4">
Choose Your Subjects
</h1>



<form action="save_subjects.php" method="POST">



<div class="card">


<div class="row">


<?php while($row=$result->fetch_assoc()){ ?>


<div class="col-md-4">


<div class="form-check">


<input

class="form-check-input"

type="checkbox"

name="subjects[]"

value="<?php echo $row['subject_name']; ?>"

>


<label class="form-check-label">

<?php echo $row['subject_name']; ?>

</label>


</div>


</div>



<?php } ?>


</div>


</div>


<br>


<button class="btn btn-success btn-lg">

Continue To Topics

</button>



</form>



</div>


</body>

</html>