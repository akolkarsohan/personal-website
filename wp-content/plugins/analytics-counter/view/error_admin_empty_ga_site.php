<?php
$url = admin_url('options-general.php?page=wpadm-ga-menu-settings');
echo '<div class="error">
    <p>Google Analytics service was unable to determine the site</p>
    <div style="text-align: center">
        <a href="'.$url.'" class="btn btn-success" >Select a site</a>
    </div>
</div>';
