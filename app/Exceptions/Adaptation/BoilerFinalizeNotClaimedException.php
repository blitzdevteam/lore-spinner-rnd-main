<?php

declare(strict_types=1);

namespace App\Exceptions\Adaptation;

use RuntimeException;

/**
 * Thrown when a BoilerFinalizeJob cannot take ownership of a session because
 * another worker holds it or the session is not in a finalizable state.
 *
 * It stops the job chain so downstream verification never runs against a
 * missing final cut, and it must never overwrite the boiler status owned by
 * whichever worker actually holds the session.
 */
final class BoilerFinalizeNotClaimedException extends RuntimeException {}
