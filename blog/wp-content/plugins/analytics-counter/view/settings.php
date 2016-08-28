<?php
if (isset($_GET['modal'])): ?>
<style>
    #wpadminbar {
        display: none;
    }
    html.wp-toolbar {
        padding-top: 0px;
    }
    body.settings_page_wpadm-ga-menu-settings {
        overflow: hidden;
    }
    
</style>    
    
<?php
endif;

require dirname(__FILE__) . DIRECTORY_SEPARATOR . 'settings_ga_account.php';
