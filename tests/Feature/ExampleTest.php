<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_homepage_renders_the_public_catalog(): void
    {
        $this->get('/')
            ->assertInertia(fn (Assert $page) => $page->component('Catalog/Index'));
    }
}
