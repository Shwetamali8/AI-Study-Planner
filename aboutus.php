<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>About Us | AI Study Planner</title>


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




/* HERO */


.hero{

padding-top:150px;

padding-bottom:80px;

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

max-width:750px;

margin:20px auto;

}





/* ABOUT SECTION */


.about-section{

padding:70px 0;

}



.about-card{

background:rgba(255,255,255,.08);

border:1px solid rgba(255,255,255,.15);

border-radius:30px;

padding:40px;

height:100%;

backdrop-filter:blur(20px);

}



.about-card h2{

font-weight:800;

margin-bottom:20px;

}



.about-card p{

color:#cbd5e1;

line-height:1.8;

}




.icon-box{

font-size:45px;

color:#38bdf8;

margin-bottom:20px;

}





/* MISSION */


.mission{

background:#020617;

padding:80px 0;

}



.mission-box{

background:linear-gradient(135deg,#2563eb,#06b6d4);

padding:45px;

border-radius:30px;

text-align:center;

}



.mission-box h2{

font-weight:800;

}





/* TEAM STYLE */


.team-card{

background:#111827;

padding:30px;

border-radius:25px;

text-align:center;

transition:.3s;

}



.team-card:hover{

transform:translateY(-10px);

box-shadow:0 20px 40px black;

}



.team-card i{

font-size:45px;

color:#38bdf8;

margin-bottom:15px;

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

About <span>AI Study Planner</span>

</h1>


<p>

An intelligent learning platform designed to help students
organize their studies, improve productivity and achieve
academic goals using Artificial Intelligence.

</p>


</div>


</section>







<!-- ABOUT CONTENT -->


<section class="about-section">


<div class="container">


<div class="row g-4">



<div class="col-lg-6">


<div class="about-card">


<div class="icon-box">

<i class="fa-solid fa-brain"></i>

</div>


<h2>

Who We Are

</h2>


<p>

AI Study Planner is a smart education platform that combines
Artificial Intelligence with modern learning techniques.
Our goal is to make studying easier, organized and more
effective for students.

</p>


<p>

The system helps learners create personalized schedules,
track progress and build consistent study habits.

</p>


</div>


</div>






<div class="col-lg-6">


<div class="about-card">


<div class="icon-box">

<i class="fa-solid fa-bullseye"></i>

</div>


<h2>

Our Vision

</h2>


<p>

We believe every student has unique learning needs.
Our vision is to provide an AI-powered assistant that adapts
to individual goals and helps students learn smarter.

</p>


<p>

By using technology, we aim to create a better and more
efficient learning experience.

</p>


</div>


</div>




</div>


</div>


</section>







<!-- MISSION -->


<section class="mission">


<div class="container">


<div class="mission-box">


<h2>

🚀 Our Mission

</h2>


<p>

To empower students with intelligent tools that improve
planning, productivity and learning outcomes.

</p>


<a href="auth/register.php"
class="btn btn-light rounded-pill px-4 mt-3">

Join AI Learning

</a>


</div>


</div>


</section>








<!-- VALUES -->


<section class="about-section">


<div class="container">


<h2 class="text-center fw-bold mb-5">

Why Choose Us?

</h2>



<div class="row g-4">



<div class="col-md-4">


<div class="team-card">


<i class="fa-solid fa-robot"></i>


<h4>

AI Powered

</h4>


<p>

Smart recommendations and personalized study planning.

</p>


</div>


</div>






<div class="col-md-4">


<div class="team-card">


<i class="fa-solid fa-graduation-cap"></i>


<h4>

Student Focused

</h4>


<p>

Designed to support students in achieving their goals.

</p>


</div>


</div>






<div class="col-md-4">


<div class="team-card">


<i class="fa-solid fa-chart-simple"></i>


<h4>

Progress Driven

</h4>


<p>

Track improvement and maintain learning consistency.

</p>


</div>


</div>




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