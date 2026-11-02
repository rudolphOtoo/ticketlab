<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;

test('welcome homepage renders cleanly with serene studio branding', function (): void {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('Where memorable gatherings begin.');
    $response->assertSee('Explore Upcoming Events');
    $response->assertSee('Card Friendly');
    $response->assertDontSee("Let's get started");
});

test('admin root renders the tenant admin overview', function (): void {
    $org = Organization::create([
        'name' => 'Accra Central Hub',
        'slug' => 'accra-central-hub',
    ]);

    $user = User::factory()->create([
        'organization_id' => $org->id,
        'role' => 'org_admin',
    ]);

    $response = $this->actingAs($user)->get('/admin');

    $response->assertOk();
    $response->assertSee('Welcome back');
    $response->assertSee('Admin Command');
});

test('unauthenticated visitor to admin is redirected to login', function (): void {
    $response = $this->get('/admin');

    $response->assertRedirect('/login');
});

test('non-existent route renders branded 404 page', function (): void {
    $response = $this->get('/non-existent-page-route-999');

    $response->assertNotFound();
    $response->assertSee('404');
    $response->assertSee('Page or Event Not Found');
    $response->assertSee('The event, ticket pass, or administrative link you are looking for has been moved');
});
