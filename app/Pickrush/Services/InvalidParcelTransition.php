<?php

namespace App\Pickrush\Services;

use DomainException;

/** A scan that the parcel lifecycle does not allow from the parcel's current status. */
class InvalidParcelTransition extends DomainException {}
