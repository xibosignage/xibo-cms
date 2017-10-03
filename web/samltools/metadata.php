<?php

/**
 *  SAML Metadata view
 */
require_once dirname(__DIR__).'/_toolkit_loader.php';
require_once dirname(__DIR__).'/samlSettings.php';
try {
    #$auth = new OneLogin_Saml2_Auth($settingsInfo);
    #$settings = $auth->getSettings();
    // Now we only validate SP settings
    $settings = new OneLogin_Saml2_Settings($samlSettings, true);
    $metadata = $settings->getSPMetadata();
    $errors = $settings->validateMetadata($metadata);
    if (empty($errors)) {
        header('Content-Type: text/xml');
        echo $metadata;
    } else {
        throw new OneLogin_Saml2_Error(
            'Invalid SP metadata: '.implode(', ', $errors),
            OneLogin_Saml2_Error::METADATA_SP_INVALID
        );
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
