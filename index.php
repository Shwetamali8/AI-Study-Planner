<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>AI Study Planner | Smart Learning</title>


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


/* NAVBAR */

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

font-size:26px;

font-weight:800;

color:white;

text-decoration:none;

display:flex;

align-items:center;

gap:8px;

}


.logo span{

color:#38bdf8;

}


.logo-icon{

display:inline-flex;

width:35px;

height:35px;

align-items:center;

justify-content:center;

background:linear-gradient(135deg,#06b6d4,#6366f1);

border-radius:12px;

color:white!important;

font-size:20px;

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

padding-top:170px;

min-height:100vh;

display:flex;

align-items:center;

}


.hero h1{

font-size:60px;

font-weight:800;

}


.hero h1 span{

background:linear-gradient(90deg,#22d3ee,#8b5cf6);

-webkit-background-clip:text;

color:transparent;

}


.hero p{

font-size:19px;

color:#cbd5e1;

line-height:1.8;

margin-top:25px;

}



.hero-btn{

background:linear-gradient(90deg,#06b6d4,#6366f1);

border:none;

padding:14px 35px;

border-radius:30px;

color:white;

font-weight:600;

}



/* AI IMAGE BOX */


.ai-circle{

height:350px;

width:350px;

border-radius:50%;

background:linear-gradient(135deg,#2563eb,#06b6d4);

display:flex;

align-items:center;

justify-content:center;

font-size:150px;

box-shadow:0 0 80px #2563eb;

animation:float 3s infinite;

}



@keyframes float{

50%{

transform:translateY(-20px);

}

}



/* FEATURES */


.section{

padding:90px 0;

}


.title{

text-align:center;

font-size:40px;

font-weight:800;

margin-bottom:50px;

}


.title span{

color:#38bdf8;

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

transform:translateY(-10px);

box-shadow:0 20px 50px black;

}



.icon{

font-size:45px;

color:#38bdf8;

margin-bottom:20px;

}



.card-box p{

color:#cbd5e1;

line-height:1.7;

}



/* HOW IT WORKS */


.steps{

background:#020617;

}



.step{

background:rgba(255,255,255,.08);

border-radius:20px;

padding:30px;

text-align:center;

}



.step i{

font-size:45px;

color:#38bdf8;

}



/* CTA */


.cta{

background:linear-gradient(135deg,#2563eb,#06b6d4);

padding:50px;

border-radius:30px;

text-align:center;

}


.cta h2{

font-weight:800;

font-size:40px;

}



/* FOOTER */


footer{

background:#020617;

padding:30px;

text-align:center;

color:#94a3b8;

}



@media(max-width:768px){


.hero h1{

font-size:40px;

}


.ai-circle{

height:250px;

width:250px;

font-size:100px;

}


}

</style>


</head>


<body>


<!-- NAVBAR -->


<nav class="navbar navbar-expand-lg">

<div class="container">


<a class="logo" href="index.php">

<span class="logo-icon">◈</span>

<span>Mind</span>AI

</a>



<button class="navbar-toggler"
data-bs-toggle="collapse"
data-bs-target="#menu">

<span class="navbar-toggler-icon"></span>

</button>



<div class="collapse navbar-collapse" id="menu">


<ul class="navbar-nav ms-auto align-items-center">


<li class="nav-item">
<a class="nav-link" href="index.php">Home</a>
</li>


<li class="nav-item">
<a class="nav-link" href="features.php">Features</a>
</li>


<li class="nav-item">
<a class="nav-link" href="aboutus.php">About</a>
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

<div class="row align-items-center">


<div class="col-lg-7">


<h1>

Study Smarter With

<span>AI Powered</span>

Learning

</h1>


<p>

AI Study Planner helps students create personalized
study schedules, track progress and achieve academic
goals with intelligent technology.

</p>


<a href="features.php"
class="btn hero-btn mt-3">

Explore features

</a>





</div>




<div class="col-lg-5 text-center mt-5 mt-lg-0">


<div class="ai-circle">

🤖

</div>


</div>


</div>


</div>


</section>







<!-- BENEFITS -->


<section class="section">


<div class="container">


<h2 class="title">

Why Choose <span>AI Study Planner?</span>

</h2>



<div class="row g-4">


<div class="col-md-4">

<div class="card-box">


<div class="icon">

<i class="fa-solid fa-brain"></i>

</div>


<h3>

Smart Planning

</h3>


<p>

AI creates study plans according to your subjects,
deadlines and learning goals.

</p>


</div>

</div>





<div class="col-md-4">

<div class="card-box">


<div class="icon">

<i class="fa-solid fa-chart-simple"></i>

</div>


<h3>

Progress Tracking

</h3>


<p>

Analyze your performance and understand your learning
improvements.

</p>


</div>

</div>





<div class="col-md-4">

<div class="card-box">


<div class="icon">

<i class="fa-solid fa-graduation-cap"></i>

</div>


<h3>

Better Results

</h3>


<p>

Develop consistent study habits and achieve academic
success faster.

</p>


</div>

</div>


</div>


</div>


</section>






<!-- WORKFLOW -->


<section class="section steps">


<div class="container">


<h2 class="title">

How It <span>Works</span>

</h2>



<div class="row g-4">


<div class="col-md-4">

<div class="step">

<i class="fa-solid fa-user-plus"></i>

<h4 class="mt-3">

Create Account

</h4>

<p>

Register and set your learning goals.

</p>


</div>

</div>




<div class="col-md-4">

<div class="step">

<i class="fa-solid fa-calendar-check"></i>

<h4 class="mt-3">

Generate Plan

</h4>

<p>

AI creates your personalized timetable.

</p>


</div>

</div>





<div class="col-md-4">

<div class="step">

<i class="fa-solid fa-trophy"></i>

<h4 class="mt-3">

Achieve Goals

</h4>

<p>

Follow your plan and improve daily.

</p>


</div>

</div>



</div>


</div>


</section>







<!-- CTA -->


<section class="section">


<div class="container">


<div class="cta">


<h2>

Your AI Learning Partner Is Ready

</h2>


<p>

Start planning your studies smarter today.

</p>


<a href="auth/register.php"
class="btn btn-light rounded-pill px-5 mt-3">

Get Started

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