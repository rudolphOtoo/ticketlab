<?php

declare(strict_types=1);

test('error views exist and extend branded layout', function (): void {
    expect(resource_path('views/errors/401.blade.php'))->toBeFile()
        ->and(resource_path('views/errors/403.blade.php'))->toBeFile()
        ->and(resource_path('views/errors/404.blade.php'))->toBeFile()
        ->and(resource_path('views/errors/419.blade.php'))->toBeFile()
        ->and(resource_path('views/errors/429.blade.php'))->toBeFile()
        ->and(resource_path('views/errors/500.blade.php'))->toBeFile()
        ->and(resource_path('views/errors/503.blade.php'))->toBeFile()
        ->and(resource_path('views/errors/layout.blade.php'))->toBeFile();
});

test('non-existent route returns 404 and renders serene studio branded error page', function (): void {
    $response = $this->get('/non-existent-ticket-url-' . uniqid());

    $response->assertNotFound();
    $response->assertSee('Page or Event Not Found');
    $response->assertSee('404');
    $response->assertSee('TicketLab');
});

test('branded error views render appropriate titles and status codes', function (
    string $code,
    string $expectedTitle,
): void {
    $view = view("errors.{$code}")->render();

    expect($view)->toContain($code)->toContain($expectedTitle)->toContain('TicketLab');
})->with([
    ['401', 'Authentication Required'],
    ['403', 'Access Restricted'],
    ['404', 'Page or Event Not Found'],
    ['419', 'Session Expired'],
    ['429', 'Rate Limit Exceeded'],
    ['500', 'Server Encountered an Error'],
    ['503', 'System Under Maintenance'],
]);
