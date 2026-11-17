<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicCatalogTest extends TestCase
{
    public function test_guest_can_open_public_catalog_without_admin_login_redirect(): void
    {
        $this->get('/')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Catalog/Index'));
    }
}
