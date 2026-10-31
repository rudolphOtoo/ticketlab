<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Services\Sms\Contracts\SmsGatewayInterface;
use App\Services\Sms\Drivers\LogSmsGateway;
use App\Services\Sms\Drivers\MnotifySmsGateway;
use App\Services\Sms\Drivers\NullSmsGateway;
use Illuminate\Contracts\Foundation\Application;

/**
 * Resolves the configured SMS driver with plug-and-play switching.
 */
class SmsManager implements SmsGatewayInterface
{
    /** @var array<string, SmsGatewayInterface> */
    protected array $drivers = [];

    public function __construct(protected Application $app) {}

    public function driver(?string $name = null): SmsGatewayInterface
    {
        $name ??= (string) config('sms.default', 'null');

        if (! isset($this->drivers[$name])) {
            $this->drivers[$name] = $this->createDriver($name);
        }

        return $this->drivers[$name];
    }

    public function send(string $to, string $message, array $context = []): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        return $this->driver()->send($to, $message, $context);
    }

    public function isEnabled(): bool
    {
        return (bool) config('sms.enabled', false);
    }

    protected function createDriver(string $driver): SmsGatewayInterface
    {
        $enabled = $this->isEnabled();
        $senderId = (string) config('sms.sender_id', 'TicketLab');

        return match ($driver) {
            'log' => new LogSmsGateway(
                (string) config('sms.drivers.log.channel', 'single'),
                $enabled,
            ),
            'mnotify' => new MnotifySmsGateway(
                (string) config('sms.drivers.mnotify.api_key', ''),
                $senderId,
                (string) config('sms.drivers.mnotify.endpoint', 'https://api.mnotify.com/api/sms/quick'),
                $enabled,
            ),
            default => new NullSmsGateway($enabled),
        };
    }
}

