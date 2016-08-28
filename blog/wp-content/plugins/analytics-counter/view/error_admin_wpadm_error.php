<?php
$error = $_GET['error'];
$error = str_replace('_', ' ', $error);

echo '<div class="error"><p>The site reports "'.$error.'"</p></div>';