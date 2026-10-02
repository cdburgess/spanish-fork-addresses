<?php

namespace Cdburgess\SpanishForkAddresses\Tests;

use Cdburgess\SpanishForkAddresses\SpanishForkAddressesServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [SpanishForkAddressesServiceProvider::class];
    }
}
