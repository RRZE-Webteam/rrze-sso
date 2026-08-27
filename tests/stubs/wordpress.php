<?php

/**
 * Minimal WordPress runtime classes required by the isolated unit tests.
 */

if (!class_exists('WP_User')) {
    class WP_User
    {
        /**
         * WordPress user ID.
         *
         * @var int
         */
        public $ID;

        /**
         * Public display name.
         *
         * @var string
         */
        public $display_name = '';

        /**
         * Email address.
         *
         * @var string
         */
        public $user_email = '';

        /**
         * Creates a lightweight user object.
         *
         * @param int $id User ID.
         */
        public function __construct($id = 0)
        {
            $this->ID = (int) $id;
        }
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        /**
         * Error code.
         *
         * @var string
         */
        private $code;

        /**
         * Error message.
         *
         * @var string
         */
        private $message;

        /**
         * Creates a lightweight WordPress error.
         *
         * @param string $code    Error code.
         * @param string $message Error message.
         */
        public function __construct($code = '', $message = '')
        {
            $this->code = $code;
            $this->message = $message;
        }

        /**
         * Returns the first error code.
         *
         * @return string
         */
        public function get_error_code()
        {
            return $this->code;
        }

        /**
         * Returns the first error message.
         *
         * @return string
         */
        public function get_error_message()
        {
            return $this->message;
        }
    }
}
