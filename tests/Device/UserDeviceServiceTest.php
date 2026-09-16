<?php

namespace Azelya\SmsService\Tests\Device;

use Azelya\SmsService\Config;
use Azelya\SmsService\Enum\DeviceType;
use Azelya\SmsService\Exception\NotFoundException;
use Azelya\SmsService\Tests\TestCase;

final class UserDeviceServiceTest extends TestCase
{
    public function test_get_returns_the_registered_device(): void
    {
        $this->queue->append($this->jsonResponse(200, $this->deviceResource()));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $device = $client->devices()->get();

        $this->assertSame('device-1', $device->id);
        $this->assertSame('Galaxy S22', $device->name);
        $this->assertSame('android', $device->type);
        $this->assertSame('static-device-token-123', $device->token);
        $this->assertTrue($device->isActive);
        $this->assertSame('/api/v1/user/device', $this->lastRequest()->getUri()->getPath());
    }

    public function test_get_without_device_throws_not_found(): void
    {
        $this->queue->append($this->error(404, 'No device registered.'));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('No device registered.');

        $client->devices()->get();
    }

    public function test_create_unwraps_the_array_wrapped_resource(): void
    {
        // The gateway returns [{data: {...}}] for POST /user/device (201).
        $this->queue->append($this->jsonResponse(201, [$this->deviceResource()]));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $device = $client->devices()->create('Pixel 8', DeviceType::Android);

        $this->assertSame('device-1', $device->id);
        $this->assertSame('Galaxy S22', $device->name);
        $this->assertSame('android', $device->type);
        $this->assertSame('static-device-token-123', $device->token);

        $this->assertSame('POST', $this->lastRequest()->getMethod());
        $this->assertSame(
            ['name' => 'Pixel 8', 'type' => 'android'],
            $this->requestBody($this->lastRequest()),
        );
    }

    public function test_create_also_accepts_an_unwrapped_resource(): void
    {
        // Defensive: the gateway may fix the array wrap (200 update path).
        $this->queue->append($this->jsonResponse(200, $this->deviceResource(['type' => 'iot', 'name' => 'Sensor'])));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $device = $client->devices()->create('Sensor', 'iot');

        $this->assertSame('Sensor', $device->name);
        $this->assertSame('iot', $device->type);
    }

    public function test_delete_removes_the_device(): void
    {
        $this->queue->append($this->jsonResponse(200, ['message' => 'Device removed successfully.']));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $client->devices()->delete();

        $this->assertSame('DELETE', $this->lastRequest()->getMethod());
        $this->assertSame('/api/v1/user/device', $this->lastRequest()->getUri()->getPath());
    }
}
