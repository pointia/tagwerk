<?php

declare(strict_types=1);

namespace App\Exceptions;

use InvalidArgumentException;

/** A file that cannot be re-encoded as a photo. The message is meant for people. */
final class InvalidImage extends InvalidArgumentException {}
