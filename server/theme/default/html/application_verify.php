<div class="login_box">
    <div class="login_header">
        <div class="login_header_left">
        </div>
        <div class="login_header_right">
        </div>
    </div>

    <div class="login_body">
        
        <h2>Xibo API - Authorization Requested</h2>
        <div style="text-align:left;">
            <p>Are you sure you want to authorize this application to have access to your CMS account?</p>
            <p>
                <strong>Application Name</strong>: <?php echo $consumer['application_title']; ?><br />
                <strong>Application Description</strong>: <?php echo $consumer['application_descr']; ?><br />
                <strong>Application Site</strong>: <?php echo $consumer['application_uri']; ?>
            </p>
        </div>
        <form method="post">
            <input type="submit" name="Allow" value="Allow">
        </form>
        <p><a href="http://www.xibo.org.uk"><img src='img/login/complogo.png'></a></p>
    </div>

    <div class="login_foot">
        <div class="login_foot_left">
        </div>
        <div class="login_foot_right">
        </div>
    </div>
</div>