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
         * Error messages grouped by code.
         *
         * @var array<string, array<int, string>>
         */
        private $errors = array();

        /**
         * Additional error data grouped by code.
         *
         * @var array<string, mixed>
         */
        private $errorData = array();

        /**
         * Creates a lightweight WordPress error.
         *
         * @param string $code    Error code.
         * @param string $message Error message.
         */
        public function __construct($code = '', $message = '')
        {
            if ('' !== $code) {
                $this->add($code, $message);
            }
        }

        /**
         * Adds an error message and optional associated data.
         *
         * @param string $code    Error code.
         * @param string $message Error message.
         * @param mixed  $data    Optional error data.
         * @return void
         */
        public function add($code, $message, $data = '')
        {
            $this->errors[$code][] = $message;

            if ('' !== $data) {
                $this->errorData[$code] = $data;
            }
        }

        /**
         * Returns the first error code.
         *
         * @return string
         */
        public function get_error_code()
        {
            $codes = $this->get_error_codes();

            return $codes[0] ?? '';
        }

        /**
         * Returns the first error message.
         *
         * @return string
         */
        public function get_error_message()
        {
            $messages = $this->get_error_messages();

            return $messages[0] ?? '';
        }

        /**
         * Returns all recorded error codes.
         *
         * @return array<int, string>
         */
        public function get_error_codes()
        {
            return array_keys($this->errors);
        }

        /**
         * Returns messages for one code, or every recorded message.
         *
         * @param string $code Optional error code.
         * @return array<int, string>
         */
        public function get_error_messages($code = '')
        {
            if ('' !== $code) {
                return $this->errors[$code] ?? array();
            }

            $messages = array();
            foreach ($this->errors as $errorMessages) {
                $messages = array_merge($messages, $errorMessages);
            }

            return $messages;
        }

        /**
         * Reports whether one or more errors have been recorded.
         *
         * @return bool
         */
        public function has_errors()
        {
            return !empty($this->errors);
        }

        /**
         * Returns data associated with an error code.
         *
         * @param string $code Error code.
         * @return mixed
         */
        public function get_error_data($code = '')
        {
            $code = $code ?: $this->get_error_code();

            return $this->errorData[$code] ?? null;
        }
    }
}
