
<?php
if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
    echo htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8');
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    echo '</div>';

    unset($_SESSION['error']);
}

if (isset($_SESSION['success'])) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">';
    echo htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8');
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    echo '</div>';

    unset($_SESSION['success']);
}
?>


<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Create Account | MindAI</title>


<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">


<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">


<style>


*{

margin:0;
padding:0;
box-sizing:border-box;

}


body{

font-family:'Inter',sans-serif;

background:#050816;

min-height:100vh;

display:flex;

align-items:center;

justify-content:center;

color:white;

}



/* BACKGROUND GLOW */


body::before{

content:"";

position:absolute;

width:400px;

height:400px;

background:#2563eb;

filter:blur(150px);

opacity:.4;

top:10%;

left:10%;

}


body::after{

content:"";

position:absolute;

width:400px;

height:400px;

background:#06b6d4;

filter:blur(150px);

opacity:.3;

bottom:10%;

right:10%;

}



/* REGISTER BOX */


.register-box{

width:430px;

background:rgba(255,255,255,.08);

border:1px solid rgba(255,255,255,.15);

backdrop-filter:blur(20px);

padding:40px;

border-radius:30px;

position:relative;

z-index:2;

box-shadow:0 20px 60px black;

}



.logo{

text-align:center;

font-size:30px;

font-weight:800;

margin-bottom:10px;

}



.logo span{

color:#38bdf8;

}



.subtitle{

text-align:center;

color:#cbd5e1;

margin-bottom:30px;

}



/* INPUTS */


.form-control{

background:rgba(255,255,255,.1);

border:none;

color:white;

padding:14px;

border-radius:15px;

}



.form-control:focus{

background:rgba(255,255,255,.15);

color:white;

box-shadow:0 0 0 2px #38bdf8;

}



.form-control::placeholder{

color:#94a3b8;

}



/* BUTTON */


.btn-create{

width:100%;

padding:14px;

border-radius:30px;

border:none;

background:linear-gradient(90deg,#06b6d4,#6366f1);

color:white;

font-weight:700;

font-size:16px;

transition:.3s;

}


.btn-create:hover{

transform:translateY(-3px);

box-shadow:0 10px 30px #2563eb;

}



.login-link{

text-align:center;

margin-top:25px;

color:#cbd5e1;

}


.login-link a{

color:#38bdf8;

text-decoration:none;

font-weight:600;

}


</style>


</head>


<body>



<div class="register-box">


<div class="logo">

<span>◈ Mind</span>AI

</div>


<p class="subtitle">

Create your AI learning account

</p>




<form action="registerprocess.php" method="POST">


<div class="mb-3">

<input type="text"
name="name"
class="form-control"
placeholder="Full Name"
required>

</div>

<div class="mb-3">

<input type="email"
name="email"
class="form-control"
placeholder="Email Address"
required>

</div>





<div class="mb-3">

<input type="password"
name="password"
class="form-control"
placeholder="Password"
required>

</div>





<div class="mb-3">

<input type="password"
name="confirm_password"
class="form-control"
placeholder="Confirm Password"
required>

</div>





<button type="submit" class="btn-create">
    Create Account 🚀
</button>

</form>


</form>





<div class="login-link">

Already have an account?

<a href="login.php">

Login

</a>


</div>



</div>




</body>

</html>