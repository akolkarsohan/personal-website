<?php
$error = $_GET['google_oauth2_error'];
$error = str_replace('_', ' ', $error);

echo '<div class="error"><p>Google Analytics service reports "'.$error.'"</p></div>';