<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A refusal the member should see: not enough coins, already owned, inactive.
 * The controller turns it into a 422 with the message, never a 500.
 */
class StoreException extends RuntimeException {}
