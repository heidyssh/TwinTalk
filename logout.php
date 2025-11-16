<?php
session_start();
session_unset();
session_destroy();
header("Location: /twintalk/index.php");
exit();
