<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    public function test_preflight_blocks_development_configuration_without_contacting_real_database(): void
    {
        config()->set('app.env', 'testing');
        config()->set('app.debug', true);

        $this->artisan('grovemap:preflight', ['--skip-database' => true])
            ->assertExitCode(1);
    }
}
