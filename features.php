<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Features | AI Study Planner</title>


<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">


<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">


<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">


<style>


*{
margin:0;
padding:0;
box-sizing:border-box;
}


body{

font-family:'Inter',sans-serif;

background:#050816;

color:white;

}



.navbar{

position:fixed;

top:20px;

left:50%;

transform:translateX(-50%);

width:90%;

background:rgba(255,255,255,.08);

backdrop-filter:blur(20px);

border:1px solid rgba(255,255,255,.15);

border-radius:20px;

padding:12px 25px;

z-index:1000;

}



.logo{

font-size:24px;

font-weight:800;

color:white;

text-decoration:none;

}


.logo span{

color:#38bdf8;

}



.nav-link{

color:#ddd!important;

margin:0 10px;

}


.nav-link:hover{

color:#38bdf8!important;

}


.register{

background:linear-gradient(90deg,#06b6d4,#6366f1);

padding:10px 22px;

border-radius:30px;

color:white;

text-decoration:none;

}




.hero{

padding-top:150px;

text-align:center;

}



.hero h1{

font-size:55px;

font-weight:800;

}



.hero h1 span{

background:linear-gradient(90deg,#22d3ee,#8b5cf6);

-webkit-background-clip:text;

color:transparent;

}



.hero p{

font-size:18px;

color:#b8c1d9;

margin-top:20px;

}





.features{

padding:80px 0;

}



.card-box{

background:rgba(255,255,255,.08);

border:1px solid rgba(255,255,255,.15);

border-radius:25px;

padding:35px;

height:100%;

transition:.3s;

backdrop-filter:blur(20px);

}



.card-box:hover{

transform:translateY(-12px);

box-shadow:0 20px 50px black;

}



.icon{

font-size:45px;

color:#38bdf8;

margin-bottom:20px;

}



.card-box h3{

font-weight:700;

}



.card-box p{

color:#cbd5e1;

line-height:1.7;

}





.ai-section{

background:#020617;

padding:80px 0;

}



.ai-box{

background:linear-gradient(135deg,#2563eb,#06b6d4);

padding:40px;

border-radius:30px;

text-align:center;

}



.ai-box h2{

font-weight:800;

}





footer{

background:#020617;

padding:30px;

text-align:center;

color:#94a3b8;

}




@media(max-width:768px){

.hero h1{

font-size:38px;

}


.navbar{

width:95%;

}

}


</style>


</head>


<body>



<!-- NAVBAR -->


<nav class="navbar navbar-expand-lg">


<div class="container">


<a class="logo" href="index.php">

🤖 AI <span>Study Planner</span>

</a>



<button class="navbar-toggler"
data-bs-toggle="collapse"
data-bs-target="#menu">


<span class="navbar-toggler-icon"></span>


</button>



<div class="collapse navbar-collapse" id="menu">


<ul class="navbar-nav ms-auto align-items-center">


<li class="nav-item">

<a class="nav-link" href="index.php">

Home

</a>

</li>



<li class="nav-item">

<a class="nav-link" href="features.php">

Features

</a>

</li>



<li class="nav-item">

<a class="nav-link" href="aboutus.php">

About

</a>

</li>



<li class="nav-item">

<a class="register" href="auth/register.php">

Create Account

</a>

</li>


</ul>


</div>


</div>


</nav>






<!-- HERO -->


<section class="hero">


<div class="container">


<h1>

Powerful <span>AI Features</span>

for Better Learning

</h1>


<p>

Everything you need to plan, track and improve your study journey.

</p>


</div>


</section>






<!-- FEATURES -->


<section class="features">


<div class="container">


<div class="row g-4">



<div class="col-md-4">


<div class="card-box">


<div class="icon">

<i class="fa-solid fa-robot"></i>

</div>


<h3>

AI Study Planner

</h3>


<p>

Generate personalized study schedules based on your subjects,
goals and available time.

</p>


</div>


</div>





<div class="col-md-4">


<div class="card-box">


<div class="icon">

<i class="fa-solid fa-chart-line"></i>

</div>


<h3>

Learning Analytics

</h3>


<p>

Monitor your progress, completion rate and identify areas
where you need improvement.

</p>


</div>


</div>






<div class="col-md-4">


<div class="card-box">


<div class="icon">

<i class="fa-solid fa-bell"></i>

</div>


<h3>

Smart Reminders

</h3>


<p>

Receive intelligent reminders to maintain your daily
study routine and streak.

</p>


</div>


</div>






<div class="col-md-4">


<div class="card-box">


<div class="icon">

<i class="fa-solid fa-clock"></i>

</div>


<h3>

Time Management

</h3>


<p>

Optimize your study hours using AI-powered time planning
and priority management.

</p>


</div>


</div>







<div class="col-md-4">


<div class="card-box">


<div class="icon">

<i class="fa-solid fa-book-open"></i>

</div>


<h3>

Subject Tracking

</h3>


<p>

Organize subjects, chapters and assignments in one smart
learning dashboard.

</p>


</div>


</div>








<div class="col-md-4">


<div class="card-box">


<div class="icon">

<i class="fa-solid fa-fire"></i>

</div>


<h3>

Study Streaks

</h3>


<p>

Stay motivated by tracking daily learning activities and
achievements.

</p>


</div>


</div>




</div>


</div>


</section>







<section class="ai-section">


<div class="container">


<div class="ai-box">


<h2>

🤖 Your Personal AI Learning Partner

</h2>


<p>

Study smarter, save time and achieve your academic goals
with intelligent assistance.

</p>



<a href="auth/register.php"
class="btn btn-light rounded-pill px-4 mt-3">

Start Learning

</a>


</div>


</div>


</section>






<footer>


<h4>

🤖 AI Study Planner

</h4>


<p>

Learn smarter. Achieve faster.

</p>


© 2026 AI Study Planner


</footer>





<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>


</body>

</html>