<?php

declare(strict_types=1);

namespace Daikazu\AssetCleaner\Tests;

use Daikazu\AssetCleaner\AssetCleanerServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            AssetCleanerServiceProvider::class,
        ];
    }
}
