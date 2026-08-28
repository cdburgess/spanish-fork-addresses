<?php

namespace Cdburgess\SpanishForkAddresses\Contracts;

use Cdburgess\AddressingStandards\Address;
use Cdburgess\SpanishForkAddresses\ValidationResult;

interface AddressValidator
{
    /**
     * Validate an already-standardized Address against Spanish Fork GIS data.
     * This method must not normalize raw input.
     */
    public function validate(Address $address): ValidationResult;
}
