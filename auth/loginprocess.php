<?php

session_start();


// Database connection

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "study_planner";


$conn = new mysqli($host, $user, $pass, $dbname);


if($conn->connect_error)
{
    die("Connection failed: " . $conn->connect_error);
}



if($_SERVER["REQUEST_METHOD"] == "POST")
{

    $email = trim($_POST['email']);
    $password = $_POST['password'];



    // Find user

    $stmt = $conn->prepare(
        "SELECT id, name, email, password FROM user WHERE email=?"
    );


    if(!$stmt)
    {
        die("SQL Error: ".$conn->error);
    }



    $stmt->bind_param("s",$email);

    $stmt->execute();


    $result = $stmt->get_result();



    // User found

    if($result->num_rows == 1)
    {

        $user = $result->fetch_assoc();



        // Verify password

        if(password_verify($password,$user['password']))
        {


            // Create session

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];



            // Go to dashboard

            // Check if profile already exists

// Check whether student profile exists

$check = $conn->prepare(
    "SELECT id FROM student_profilenew WHERE user_id=?"
);

$check->bind_param("i", $user['id']);

$check->execute();

$profile = $check->get_result();


if($profile->num_rows == 0)
{
    // First login - profile not created
    header("Location: ../student/profilesetup.php");
}
else
{
    // Returning user - profile already created
    header("Location: ../student/newdashboard.php");
}

exit();

        }
        else
        {

            echo "
            <script>
            alert('Incorrect password!');
            window.location='login.php';
            </script>";

        }



    }
    else
    {

        echo "
        <script>
        alert('Account not found. Please create your account first.');
        window.location='register.php';
        </script>";

    }


}


$conn->close();

?>