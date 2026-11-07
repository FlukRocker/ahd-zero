<?php

namespace App\Exceptions;

/**
 * The member tried to use an item they do not own. An authorisation failure
 * (403), distinct from the 422 validation-style refusals of StoreException.
 */
class NotOwnedException extends StoreException {}
