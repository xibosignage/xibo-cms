<?php
  $username = $_REQUEST['username'];
  $key = $_GET['key'];

  //TODO: Validate username

  $sql_user = "displaym2_reset";
  $sql_pass = "f9rqDkSR3LcsjNtBdShgYqcX";
  $sql_db = "displaymanager2";
  $sql_host = "localhost";
  //TODO: Validate key and username
  $DEBUG = false;

  $headers = 'From: support@digitalhda.com' . "\r\n" .
    'Reply-To: support@digitalhda.com' . "\r\n" .
    'X-Mailer: PHP/' . phpversion();

  function print_debug($msg) {
   global $DEBUG;
   if ($DEBUG) {
    print_r ($msg);
   }
  }

  if ($username) {
    $mysqli = new mysqli($sql_host, $sql_user, $sql_pass, $sql_db);
    if ($mysqli->connect_errno) {
      die("Failed to connect to Database:"); # (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
    }
    if (!$key) {
      print_debug ($username);
      $stmt = $mysqli->prepare("SELECT `UserName`, `lastaccessed`, `email` FROM user WHERE `UserName` = ?");
      $stmt->bind_param("s", $username);
      if ($stmt->execute()) {
	 $row = array();
	 $stmt->bind_result($row['UserName'], $row['lastaccessed'], $row['email']);
 	 while ($stmt->fetch()) {
  	   print_debug ($row);
	   $to = $row['email'];
	   $e = "/^[-+\\.0-9=a-z_]+@([-0-9a-z]+\\.)+([0-9a-z]){2,4}$/i";
    	   // from address
    	   if(preg_match($e, $to)) {
	     $gen_key = hash('ripemd160', $row['email'].$row['lastaccessed']);
	     $subject = "Password Reset from DisplayManager 2";
             $message = "Hi,\n\nSomeone requested a password reset from DisplayManager2. If this was NOT you, contact support@digitalhda.com right away.\n\nTo continue resetting your password copy and paste this link in your browser: https://displaymanager2.digitalhda.com".$_SERVER['PHP_SELF']."?username=".$row['UserName']."&key=".$gen_key;
	     mail ($to, $subject, $message, $headers);
	   }
         }
      }
    } else {
      $stmt = $mysqli->prepare("SELECT `UserName`, `lastaccessed`, `email` FROM user WHERE `UserName` = ?");
      $stmt->bind_param("s", $username);
      if ($stmt->execute()) {
         $row = array();
         $stmt->bind_result($row['UserName'], $row['lastaccessed'], $row['email']);
         while ($stmt->fetch()) {
	   $gen_key = hash('ripemd160', $row['email'].$row['lastaccessed']);
         }
	 $stmt->close();
	 if ($gen_key === $key) {
	     $stmt = $mysqli->prepare("UPDATE user SET `UserPassword` = ? WHERE `UserName` = ?");
             $new_pwd = substr(str_shuffle(abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890),0, 8);
             $new_pwd_hash = password_hash($new_pwd, PASSWORD_DEFAULT);
             $stmt->bind_param("ss", $new_pwd_hash, $username);
             if ($stmt->execute()) {
               $to = $row['email'];
               $subject = "Password Reset from DisplayManager 2";
               $message = "Hi,\n\nSomeone requested a password reset from DisplayManager2. If this was NOT you, contact support@digitalhda.com right away.\n\nYour new password: ".$new_pwd;
               mail ($to, $subject, $message, $headers);
             }
	  }
      }
    }
  }
  

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>DigitalHDA - Digital Signage</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="token" content="1f1e0771bea9c505c425c0dfbb57aba53586e666"/>
    <link rel="shortcut icon" href="/theme/digitalhda/img/favicon.ico" />

    <link href="/theme/digitalhda/libraries/bootstrap/css/bootstrap.min.css" rel="stylesheet" media="screen">
    <link href="/theme/digitalhda/libraries/bootstrap/css/bootstrap-theme.min.css" rel="stylesheet" media="screen">

    <!-- Copyright 2006-2015 Daniel Garner. Part of the Xibo Open Source Digital Signage Solution. Released under the AGPLv3 or later. -->
    <style type="text/css">
        body {
            padding-top: 40px;
            padding-bottom: 40px;
            background-color: #f5f5f5;
        }

        .form-signin {
            max-width: 300px;
            padding: 19px 29px 29px;
            margin: 0 auto 20px;
            background-color: #fff;
            border: 1px solid #e5e5e5;
            -webkit-border-radius: 5px;
            -moz-border-radius: 5px;
            border-radius: 5px;
            -webkit-box-shadow: 0 1px 2px rgba(0,0,0,.05);
            -moz-box-shadow: 0 1px 2px rgba(0,0,0,.05);
            box-shadow: 0 1px 2px rgba(0,0,0,.05);
        }
        .form-signin .form-signin-heading,
        .form-signin .checkbox {
            margin-bottom: 10px;
        }
        .form-signin input[type="text"],
        .form-signin input[type="password"] {
            font-size: 16px;
            height: auto;
            margin-bottom: 15px;
            padding: 7px 9px;
        }

    </style>
</head>
<body>
    <div class="container">
  <?php if ($username == "") { ?>  
      <form id="user-form" class="form-signin text-center" action="<?php echo $_SERVER['PHP_SELF'];?>" method="post">
            <p><a href="https://www.digitalhda.com"><img src="/theme/digitalhda/img/xibologo.png"></a></p>

            <p>Please enter your login</p>

            <input id="username" class="form-control input-block-level" name="username" type="text" placeholder="User" autofocus>
            
            <button class="btn btn-large btn-primary" type="submit">Send verification e-mail</button>
	</form>
  <?php } elseif ($username != "" && $key == "") { ?>
            <p><a href="https://www.digitalhda.com"><img src="/theme/digitalhda/img/xibologo.png"></a></p>

            <p>An e-mail has been sent to the address we have on file. Please check your e-mail for further instructions. If you do not receive an e-mail within 15 minutes, check your SPAM box or contact us by e-mailing us at support@digitalhda.com</p>

  <?php } elseif ($username != "" && $key != "") { ?>
	     <p><a href="https://www.digitalhda.com"><img src="/theme/digitalhda/img/xibologo.png"></a></p>

            <p>A new password has been sent to you by e-mail. Once you have received it, you can login with this new password and change your password in the top-right corner of the system.</p>
  <?php } ?>
    </div> <!-- /container -->
    <script src="/theme/digitalhda/libraries/jquery/jquery-1.11.1.min.js"></script>
    <script src="/theme/digitalhda/libraries/bootstrap/js/bootstrap.min.js"></script>
</body>
</html>
