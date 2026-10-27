<?php

session_start();

$conn = new mysqli("localhost","root","","study_planner");

$user_id = $_SESSION['user_id'];

foreach($_POST['topics'] as $subject=>$topicList){

    $topics = explode(",",$topicList);

    foreach($topics as $topic){

        $topic = trim($topic);

        if($topic=="")
            continue;

        $stmt = $conn->prepare("
        INSERT INTO subject_topics
        (user_id,subject_name,topic_name)
        VALUES
        (?,?,?)
        ");

        $stmt->bind_param(
        "iss",
        $user_id,
        $subject,
        $topic
        );

        $stmt->execute();
    }

}

header("Location: dashboard.php");
exit();