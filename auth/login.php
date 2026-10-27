<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Login | AI Study Planner</title>


<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">


<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">


<style>


*{
margin:0;
padding:0;
box-sizing:border-box;
}


body{

height:100vh;

display:flex;

align-items:center;

justify-content:center;

font-family:'Inter',sans-serif;

background:

radial-gradient(circle at top left,#2563eb55,transparent 40%),
radial-gradient(circle at bottom right,#06b6d455,transparent 40%),
#050816;

color:white;

}



/* LOGIN CARD */


.login-box{

width:420px;

padding:40px;

background:

rgba(255,255,255,.08);

backdrop-filter:blur(20px);

border-radius:25px;

border:1px solid rgba(255,255,255,.15);

box-shadow:

0 20px 50px rgba(0,0,0,.5);

}



.logo{

text-align:center;

font-size:45px;

margin-bottom:15px;

}



h2{

text-align:center;

font-weight:700;

}



.subtitle{

text-align:center;

color:#cbd5e1;

margin-bottom:30px;

}




.form-control{

height:50px;

border-radius:15px;

background:#111827;

border:1px solid #334155;

color:white;

}


.form-control:focus{

background:#111827;

color:white;

border-color:#38bdf8;

box-shadow:none;

}



label{

margin-bottom:8px;

}



.login-btn{

width:100%;

height:50px;

border-radius:30px;

border:none;

background:

linear-gradient(90deg,#06b6d4,#6366f1);

color:white;

font-weight:700;

font-size:16px;

}



.login-btn:hover{

opacity:.9;

}



.bottom-text{

text-align:center;

margin-top:25px;

color:#cbd5e1;

}



a{

color:#38bdf8;

text-decoration:none;

}



</style>


</head>


<body>



<div class="login-box">


<div class="logo">
🤖
</div>


<h2>
Welcome Back
</h2>


<p class="subtitle">
Login to continue your smart learning journey
</p>




<form action="loginprocess.php" method="POST">


<div class="mb-3">

<label>Email</label>

<input 
type="email"
name="email"
class="form-control"
placeholder="Enter email"
required>

</div>



<div class="mb-4">

<label>Password</label>

<input
type="password"
name="password"
class="form-control"
placeholder="Enter password"
required>

</div>




<button class="login-btn">

Login

</button>



</form>



<div class="bottom-text">

Don't have an account?

<a href="register.php">
Create Account
</a>


<br><br>

<a href="../index.php">
← Back to Home
</a>


</div>


</div>




</body>

</html>