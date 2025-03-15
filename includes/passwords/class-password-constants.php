<?php
// includes/passwords/class-password-constants.php
namespace WP_Content_Locker\Includes\Passwords;

class Password_Constants {
    // Database
    const TABLE_NAME = 'wcl_passwords';
    
    // Password Generation
    const MIN_COUNT = 100;
    const MAX_COUNT = 2000;
    const MIN_LENGTH = 8;
    const MAX_LENGTH = 32;
    
    // Status
    const STATUS_UNUSED = 'unused';
    const STATUS_USING = 'using';
    const STATUS_USED = 'used';
    
    // Expiration
    const DEFAULT_EXPIRY_TIME = 24;
    const DEFAULT_EXPIRY_UNIT = 'hours';
}