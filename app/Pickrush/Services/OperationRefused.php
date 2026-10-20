<?php

namespace App\Pickrush\Services;

use DomainException;

/** An operation a business rule does not allow; the message is shown to staff as-is. */
class OperationRefused extends DomainException {}
