<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Start Learning | AI Study Planner</title>

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



/* SIDEBAR */


.sidebar{

position:fixed;

height:100vh;

width:250px;

background:#020617;

padding:30px 20px;

border-right:1px solid rgba(255,255,255,.1);

}



.logo{

font-size:24px;

font-weight:800;

margin-bottom:40px;

}


.logo span{

color:#38bdf8;

}



.menu a{

display:block;

padding:15px;

color:#cbd5e1;

text-decoration:none;

border-radius:15px;

margin-bottom:10px;

}



.menu a:hover{

background:#1e293b;

color:white;

}




/* MAIN */


.main{

margin-left:250px;

padding:35px;

}




.top-card{

background:linear-gradient(135deg,#2563eb,#06b6d4);

padding:35px;

border-radius:30px;

margin-bottom:30px;

}



.top-card h1{

font-size:40px;

font-weight:800;

}





.card-box{

background:rgba(255,255,255,.08);

border:1px solid rgba(255,255,255,.15);

border-radius:25px;

padding:25px;

height:100%;

backdrop-filter:blur(20px);

}




.card-box h3{

font-weight:700;

}




.icon{

font-size:35px;

color:#38bdf8;

margin-bottom:15px;

}




.progress{

height:12px;

background:#334155;

border-radius:20px;

}



.progress-bar{

background:linear-gradient(90deg,#06b6d4,#6366f1);

}





.task{

background:#111827;

padding:15px;

border-radius:15px;

margin-bottom:12px;

}





.subject{

padding:25px;

border-radius:20px;

background:#111827;

text-align:center;

transition:.3s;

}



.subject:hover{

transform:translateY(-8px);

box-shadow:0 15px 30px black;

}




.subject i{

font-size:35px;

color:#38bdf8;

margin-bottom:15px;

}




.ai-box{

background:#111827;

border-radius:25px;

padding:25px;

border:1px solid rgba(255,255,255,.1);

}





@media(max-width:900px){

.sidebar{

display:none;

}


.main{

margin:0;

}

}


</style>


</head>


<body>



<!-- SIDEBAR -->


<div class="sidebar">


<div class="logo">

🤖 AI <span>Planner</span>

</div>


<div class="menu">


<a href="index.php">

<i class="fa-solid fa-house"></i>
 Home

</a>


<a href="#subjects">

<i class="fa-solid fa-book"></i>
 Subjects

</a>


<a href="#tasks">

<i class="fa-solid fa-list-check"></i>
 Tasks

</a>


<a href="#progress">

<i class="fa-solid fa-chart-line"></i>
 Progress

</a>


<a href="auth/login.php">

<i class="fa-solid fa-right-from-bracket"></i>
 Logout

</a>


</div>


</div>







<!-- MAIN CONTENT -->


<div class="main">



<div class="top-card">


<h1>

Welcome Back Student 👋

</h1>


<p>

Your AI-powered study companion is ready to help you learn smarter.

</p>


<button class="btn btn-light rounded-pill px-4">

Generate AI Study Plan

</button>


</div>








<!-- DASHBOARD CARDS -->


<div class="row g-4 mb-4">



<div class="col-md-4">


<div class="card-box">


<div class="icon">

<i class="fa-solid fa-fire"></i>

</div>


<h3>

15 Days

</h3>


<p>

Learning Streak

</p>


</div>


</div>





<div class="col-md-4">


<div class="card-box">


<div class="icon">

<i class="fa-solid fa-clock"></i>

</div>


<h3>

24 Hours

</h3>


<p>

Total Study Time

</p>


</div>


</div>






<div class="col-md-4">


<div class="card-box">


<div class="icon">

<i class="fa-solid fa-trophy"></i>

</div>


<h3>

85%

</h3>


<p>

Goal Completion

</p>


</div>


</div>



</div>









<!-- SUBJECTS -->


<h2 id="subjects" class="mb-4">

Choose Subjects

</h2>


<div class="row g-4 mb-5">



<div class="col-md-3">

<div class="subject">

<i class="fa-solid fa-calculator"></i>

<h5>

Mathematics

</h5>

</div>

</div>



<div class="col-md-3">

<div class="subject">

<i class="fa-solid fa-code"></i>

<h5>

Programming

</h5>

</div>

</div>




<div class="col-md-3">

<div class="subject">

<i class="fa-solid fa-flask"></i>

<h5>

Science

</h5>

</div>

</div>




<div class="col-md-3">

<div class="subject">

<i class="fa-solid fa-language"></i>

<h5>

English

</h5>

</div>

</div>



</div>









<!-- TASKS -->


<div class="row g-4">



<div class="col-lg-7">


<div class="card-box" id="tasks">


<h2>

Today's Tasks

</h2>


<br>


<div class="task">

☐ Complete Mathematics Chapter 5

</div>


<div class="task">

☐ Practice Python Problems

</div>


<div class="task">

☐ Revise Physics Notes

</div>


<div class="task">

☐ Complete AI Quiz

</div>


</div>


</div>









<!-- AI ASSISTANT -->


<div class="col-lg-5">


<div class="ai-box">


<h3>

🤖 AI Recommendation

</h3>


<br>


<p>

Your performance in Mathematics is improving.
Spend 30 minutes today on algebra practice.

</p>


<hr>


<h5>

Today's Focus

</h5>


<p>

⭐ Mathematics Revision

<br>

⭐ Programming Practice

<br>

⭐ Reading Practice

</p>



</div>


</div>




</div>









<!-- PROGRESS -->


<div class="card-box mt-4" id="progress">


<h2>

Overall Progress

</h2>


<br>


<div class="progress">

<div class="progress-bar" style="width:85%">

</div>

</div>


<br>


<p>

85% of weekly learning goal completed

</p>


</div>





</div>






</body>

</html>