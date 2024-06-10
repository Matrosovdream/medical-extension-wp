<?php
class KC_init
{

    public function __construct()
    {

        // Include classes
        $this->include_classes();

    }

    private function include_classes()
    {

        // Main classes
        require_once KC_ABS . '/classes/core.class.php';
        require_once KC_ABS . '/classes/booking.class.php';

        // Helpers
        require_once KC_ABS . '/classes/helper.class.php';

        // Settings
        require_once (KC_ABS . '/classes/metaboxes.class.php');

    }

}

new KC_init();