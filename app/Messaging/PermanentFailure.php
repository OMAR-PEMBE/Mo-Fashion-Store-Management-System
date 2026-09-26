<?php

namespace App\Messaging;

use RuntimeException;

/** The provider refused the message for a reason a retry cannot fix. */
class PermanentFailure extends RuntimeException {}
