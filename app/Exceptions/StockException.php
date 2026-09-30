<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A business-rule violation that must be shown to the user (e.g. insufficient stock).
 * Rendered as a validation-style error by bootstrap/app.php.
 */
class StockException extends RuntimeException {}
