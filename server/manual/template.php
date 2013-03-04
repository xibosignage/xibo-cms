<?php
  if(! include('template_custom.php')) {
    # To override these options, create a file template_custom.php
    # and define ALL these variables as you wish.

    # Name of the product
    define('PRODUCT_NAME', 'Xibo');

    # Product Version
    define('PRODUCT_VERSION', '1.5.0');

    # Shoud the help include information on installing the server?
    define('HOSTED', FALSE);
  }
?>
