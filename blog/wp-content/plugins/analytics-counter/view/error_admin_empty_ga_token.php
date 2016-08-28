<style type="text/css">
    .wpadm_ga_code_tab_btn, .wpadm_ga_code_tab_btn_active {
        padding: 10px;
        text-align: center;  
        cursor: pointer; 
        font-weight: bold;
        background-color: #f9f9f9;
        color: gray;
        border: 1px solid #b4b4b4;
    }

    .wpadm_ga_code_tab_btn_active {
        background-color: #EDEDED;
        color: black;
    }

    .wpadm_ga_code_tab {
        padding: 20px;
        background-color: #EDEDED;
        border: 1px solid #b4b4b4;
        border-top: none;
    }

    #wpadm_ga_manual_tracking_code {
        border: 1px solid #b4b4b4;
        color: black;
    }

    #wpadm_ga_submit_code_btn {
        background-color: #9acfea;
    }

    #wpadm_ga_submit_code_btn:hover {
        background-color: #0088cc;
        color: white;
    }

    
    
</style>

<?php
$current_uri = home_url( add_query_arg( NULL, NULL ) );
?>

<div class="row">
    <div class="col-md-3" style="text-align: center">
        <?php echo '<a href="'.WPAdm_GA::URL_GA_AUTH.'?fix&redirect='. urlencode(WPAdm_GA::getCurUrl()).'" class="btn btn-success" style="margin-top: 20px;"><b>Connect Google Analytics services</b></a>'; ?>
    </div>
    <div class="col-md-5">
        <div style="margin-top: 20px;"><a href='https://analytics.google.com/analytics/web/#management/Settings//%3Fm.page%3DNewAccount/' class='btn btn-xs btn-success'>Create Google Analytics account</a></div>
        <p>Since Google Analytics account was successfully created, please, connect the Google Analytics created account to this Google Analytics plugin, using the same access credentials data.</p>

    </div>
</div>

<div class="row" style="margin-top: 100px; ">
    <div class="col-md-8">
        <div class="wpadm_ga_code_tab_btn_active" style=" float: left; width: 50%;" id="wpadm_ga_tab_auto_btn" onclick="wpadm_ga_clickToTab('auto')">
            Automatically generate Google Analytics Code
        </div>
        <div class="wpadm_ga_code_tab_btn" id="wpadm_ga_tab_manual_btn"  onclick="wpadm_ga_clickToTab('manual')">
            Manually past Google Analytics Code
        </div>
    </div>
</div>
<div class="row" id="wpadm_ga_manual_code_container">
    <div class="col-md-8">
        <div  class="wpadm_ga_code_tab" id="wpadm_ga_tab_auto">
            <p style="text-align: center">
                Click here to <?php echo '<a href="'.WPAdm_GA::URL_GA_AUTH.'?fix&redirect='. urlencode(WPAdm_GA::getCurUrl()).'">connect your Google Analytics account</a>'; ?>,
                automatically generate Google Analytics code <br>and automatically past Google Analytics code in your website.
            </p>
        </div>
        <div class="wpadm_ga_code_tab" style="display: none;" id="wpadm_ga_tab_manual">
            <form method="post">
                <p>Manually past Google Analytics сode in HTML of your website, without to connect to Google Analytics services. More information about this you can read on <a href="https://support.google.com/analytics/answer/1008080">Google Analytics support</a> pages.
                </p><p>Please, past your Google Analytics code here:</p>
                <?php
                    $code = get_option('wpadm_ga_manual_tracking_code', '');
                ?>

                <textarea class="form-control" rows="5" name="wpadm_ga_manual_tracking_code" id="wpadm_ga_manual_tracking_code"><?php echo stripslashes($code); ?></textarea>

                <div style="text-align: right; margin-top: 10px;">
                    <input type="submit" id="wpadm_ga_submit_code_btn" value="Save and integrate Google Analytics code" class="btn btn-default">
                </div>
            </form>
        </div>
    </div>
</div>


<script type="text/javascript">
    function wpadm_ga_clickToTab(tab) {
        var auto_btn = document.getElementById('wpadm_ga_tab_auto_btn');
        var manual_btn = document.getElementById('wpadm_ga_tab_manual_btn');

        var auto_cont = document.getElementById('wpadm_ga_tab_auto');
        var manual_cont = document.getElementById('wpadm_ga_tab_manual');

        if (tab == 'manual') {
            manual_btn.className = 'wpadm_ga_code_tab_btn_active';
            auto_btn.className = 'wpadm_ga_code_tab_btn';
            auto_cont.style.display = 'none'; 
            manual_cont.style.display = ''; 
        } else {
            manual_btn.className = 'wpadm_ga_code_tab_btn';
            auto_btn.className = 'wpadm_ga_code_tab_btn_active';
            auto_cont.style.display = '';
            manual_cont.style.display = 'none';
        }        
    }
    <?php 
        if($code)  {
            echo 'wpadm_ga_clickToTab("manual")'; 
        }
    ?>

</script>