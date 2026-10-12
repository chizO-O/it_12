<?php

namespace Tests\Feature;

use Tests\TestCase;

class PwaInstallabilityTest extends TestCase
{
    public function test_public_pages_advertise_the_installable_app(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('rel="manifest"', false)
            ->assertSee('manifest.json', false)
            ->assertSee('apple-touch-icon', false)
            ->assertSee('data-grovemap-install', false);

        $this->get('/login')
            ->assertOk()
            ->assertSee('rel="manifest"', false);
    }

    public function test_manifest_is_valid_and_icons_are_real_png_files(): void
    {
        $manifestPath = public_path('manifest.json');
        $this->assertFileExists($manifestPath);

        $manifest = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('GroveMap', $manifest['short_name']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/', $manifest['start_url']);
        $this->assertSame('/', $manifest['scope']);

        foreach ([
            'pwa/icon-192.png' => 192,
            'pwa/icon-512.png' => 512,
            'pwa/icon-maskable-512.png' => 512,
            'pwa/apple-touch-icon.png' => 180,
        ] as $path => $size) {
            $fullPath = public_path($path);
            $this->assertFileExists($fullPath);
            $imageSize = getimagesize($fullPath);
            $this->assertIsArray($imageSize);
            $this->assertSame($size, $imageSize[0]);
            $this->assertSame($size, $imageSize[1]);
            $this->assertSame('image/png', $imageSize['mime']);
        }

        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
        }
    }

    public function test_service_worker_never_persistently_caches_private_pages(): void
    {
        $workerPath = public_path('sw.js');
        $offlinePath = public_path('offline.html');
        $this->assertFileExists($workerPath);
        $this->assertFileExists($offlinePath);

        $worker = file_get_contents($workerPath);
        $this->assertStringContainsString("request.mode === 'navigate'", $worker);
        $this->assertStringContainsString('fetch(request).catch', $worker);
        $this->assertStringContainsString('if (!isPublicStaticAsset(url)) return;', $worker);
        // JavaScript regexes escape the path separators as \/; normalize them for this assertion.
        $this->assertStringContainsString('/build/assets/', str_replace('\\/', '/', $worker));
        $this->assertStringNotContainsString("cache.put('/dashboard'", $worker);
        $this->assertStringNotContainsString("cache.addAll(['/dashboard'", $worker);

        $offline = file_get_contents($offlinePath);
        $this->assertStringContainsString("You're offline", $offline);
        $this->assertStringContainsString('Your private information is not stored', $offline);
    }
}
