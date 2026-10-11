<?php

namespace Tests\Feature;

use Tests\TestCase;

class RenderHttpsProxyTest extends TestCase
{
    public function test_login_form_uses_https_when_render_forwards_https_request(): void
    {
        $this->withServerVariables([
            'REMOTE_ADDR' => '10.0.0.9',
            'SERVER_PORT' => '80',
        ])->withHeaders([
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Port' => '443',
        ])->get('http://grovemap.onrender.com/login')
            ->assertOk()
            ->assertSee('action="https://grovemap.onrender.com/login"', false);
    }
}
