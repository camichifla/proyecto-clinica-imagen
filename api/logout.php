<?php
session_start();
session_unset();
session_destroy();
header('Location: /clinica-imagen/public/login.html');
exit;
