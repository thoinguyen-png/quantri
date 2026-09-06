<?php

namespace App\Exceptions;

use RuntimeException;

class RatingSubmissionException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message = ''
    ) {
        parent::__construct($message !== '' ? $message : $reason);
    }
}
