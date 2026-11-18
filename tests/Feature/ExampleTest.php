<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Guests can access the public landing page without signing in.
     */
    public function test_the_root_route_displays_the_public_homepage(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Kelola alat sekolah dengan cara yang')
            ->assertSee(route('login'), false);
    }
}
