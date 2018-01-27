<?php

# If you need to add custom configuration settings to the CMS settings.php file,
# this is the place to do it.

# For example, if you want to configure SAML authentication, you can add the
# required configuration here

#$authentication = new \Xibo\Middleware\SAMLAuthentication();
#$samlSettings = array (
#   'workflow' => array(
#        // Enable/Disable Just-In-Time provisioning
#        'jit' => true,
#        // Attribute to identify the user 
#        'field_to_identify' => 'UserName',   // Alternatives: UserID, UserName or email
#        // Default libraryQuota assigned to the created user by JIT
#        'libraryQuota' => 1000,
#        // Initial User Group
#        'group' => 'Users',
#        // Home Page
#        'homePage' => 'dashboard',
#        // Enable/Disable Single Logout
#        'slo' => true,
#        // Attribute mapping between XIBO-CMS and the IdP
#        'mapping' => array (
#            'UserID' => '',
#            'usertypeid' => '',
#            'UserName' => 'uid',
#            'email' => 'mail',
#        )
#    ),
#   // Settings for the PHP-SAML toolkit. 
#   // See documentation: https://github.com/onelogin/php-saml#settings 
#   'strict' => false,
#   'debug' => true,
#   'idp' => array (
#            'entityId' => 'https://idp.example.com/simplesaml/saml2/idp/metadata.php',
#            'singleSignOnService' => array (
#                'url' => 'http://idp.example.com/simplesaml/saml2/idp/SSOService.php',
#            ),
#            'singleLogoutService' => array (
#                'url' => 'http://idp.example.com/simplesaml/saml2/idp/SingleLogoutService.php',
#            ),
#            'x509cert' => 'MIICbDCCAdWgAwIBAgIBADANBgkqhkiG9w0BAQ0FADBTMQswCQYDVQQGEwJ1czETMBEGA1UECAwKQ2FsaWZvcm5pYTEVMBMGA1UECgwMT25lbG9naW4gSW5jMRgwFgYDVQQDDA9pZHAuZXhhbXBsZS5jb20wHhcNMTQwOTIzMTIyNDA4WhcNNDIwMjA4MTIyNDA4WjBTMQswCQYDVQQGEwJ1czETMBEGA1UECAwKQ2FsaWZvcm5pYTEVMBMGA1UECgwMT25lbG9naW4gSW5jMRgwFgYDVQQDDA9pZHAuZXhhbXBsZS5jb20wgZ8wDQYJKoZIhvcNAQEBBQADgY0AMIGJAoGBAOWA+YHU7cvPOrBOfxCscsYTJB+kH3MaA9BFrSHFS+KcR6cw7oPSktIJxUgvDpQbtfNcOkE/tuOPBDoech7AXfvH6d7Bw7xtW8PPJ2mB5Hn/HGW2roYhxmfh3tR5SdwN6i4ERVF8eLkvwCHsNQyK2Ref0DAJvpBNZMHCpS24916/AgMBAAGjUDBOMB0GA1UdDgQWBBQ77/qVeiigfhYDITplCNtJKZTM8DAfBgNVHSMEGDAWgBQ77/qVeiigfhYDITplCNtJKZTM8DAMBgNVHRMEBTADAQH/MA0GCSqGSIb3DQEBDQUAA4GBAJO2j/1uO80E5C2PM6Fk9mzerrbkxl7AZ/mvlbOn+sNZE+VZ1AntYuG8ekbJpJtG1YfRfc7EA9mEtqvv4dhv7zBy4nK49OR+KpIBjItWB5kYvrqMLKBa32sMbgqqUqeF1ENXKjpvLSuPdfGJZA3dNa/+Dyb8GGqWe707zLyc5F8m',
#        ),
#   'sp' => array (
#        'entityId' => 'http://xibo-cms.example.com/saml/metadata',
#        'assertionConsumerService' => array (
#            'url' => 'http://xibo-cms.example.com/saml/acs',
#        ),
#        'singleLogoutService' => array (
#            'url' => 'http://xibo-cms.example.com/saml/sls',
#        ),
#        'NameIDFormat' => 'urn:oasis:names:tc:SAML:2.0:nameid-format:emailAddress',
#        'x509cert' => '',
#        'privateKey' > '',
#    ),
#    'security' => array (
#        'nameIdEncrypted' => false,
#        'authnRequestsSigned' => false,
#        'logoutRequestSigned' => false,
#        'logoutResponseSigned' => false,
#        'signMetadata' => false,
#        'wantMessagesSigned' => false,
#        'wantAssertionsSigned' => false,
#        'wantAssertionsEncrypted' => false,
#        'wantNameIdEncrypted' => false,
#    )
#);

?>
