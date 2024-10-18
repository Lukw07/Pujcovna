<?php 
session_start();
session_destroy(); //a
header("Location: login.php");
