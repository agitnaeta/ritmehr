<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The root path is a shortcut to the public QR scan page.
     */
    public function test_the_root_path_redirects_to_the_public_scan_page(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('presence.scan.public'));
    }
}
