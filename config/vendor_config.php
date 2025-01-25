<?php
/**
 * File for vendor customization. You can change here paths or some behaviors,
 * which vendors such as Linux distributions might want to change.
 *
 * For changing this file you should know what you are doing. For this reason
 * the options given here are not part of the normal configuration.
 */

return [
    /**
     * Path to a vendor autoload file. Useful when you want to have vendor dependencies somewhere else.
     */
    'autoload_file' => ROOT_PATH . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php',

    /**
     * Path to the configuration file.
     */
    'config_file' => ROOT_PATH . 'config' . DIRECTORY_SEPARATOR . 'conf.php',

    /**
     * Suffix to add to the DmarcSrg version
     */
    'version_suffix' => ''
];
