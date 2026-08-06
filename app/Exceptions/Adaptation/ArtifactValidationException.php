<?php

declare(strict_types=1);

namespace App\Exceptions\Adaptation;

use RuntimeException;

/**
 * Thrown when an adaptation phase exhausts its generation and repair attempts
 * without producing a valid artifact.
 *
 * This is a semantic failure, not a transport failure. Replaying the whole job
 * would repeat the same generations against the same inputs with no new
 * information, so jobs treat it as terminal rather than letting the queue retry.
 */
final class ArtifactValidationException extends RuntimeException {}
