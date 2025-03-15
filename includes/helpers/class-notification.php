<?php
// includes/helpers/class-notification.php
class WCL_Notification {
    public static function success($message) {
        return array(
            'status' => 'success',
            'message' => $message
        );
    }

    public static function error($message) {
        return array(
            'status' => 'error',
            'message' => $message
        );
    }
}
?>