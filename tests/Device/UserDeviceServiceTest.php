<?php

namespace Azelya\SmsService\Tests\Device;

use Azelya\SmsService\Config;
use Azelya\SmsService\Enum\DeviceType;
use Azelya\SmsService\Exception\InvalidResponseException;
use Azelya\SmsService\Exception\NotFoundException;
use Azelya\SmsService\Exception\PermissionDeniedException;
use Azelya\SmsService\Exception\ValidationException;
use Azelya\SmsService\Tests\TestCase;

final class UserDeviceServiceTest extends TestCase
{
    public function test_list_returns_all_devices_of_the_user(): void
    {
        $this->queue->append($this->jsonResponse(200, $this->deviceCollection([
            [],
            ['id' => 'device-2', 'name' => 'iPhone 15', 'type' => 'ios'],
        ])));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $devices = $client->devices()->list();

        $this->assertCount(2, $devices);
        $this->assertSame('Galaxy S22', $devices[0]->name);
        $this->assertSame('static-device-token-123', $devices[0]->token);
        $this->assertSame('iPhone 15', $devices[1]->name);
        $this->assertSame('ios', $devices[1]->type);
        $this->assertTrue($devices[1]->isActive);
        $this->assertSame('GET', $this->lastRequest()->getMethod());
        $this->assertSame('/api/v1/user/devices', $this->lastRequest()->getUri()->getPath());
    }

    public function test_list_returns_an_empty_array_when_the_user_has_no_devices(): void
    {
        $this->queue->append($this->jsonResponse(200, $this->deviceCollection()));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $this->assertSame([], $client->devices()->list());
    }

    public function test_list_without_a_data_key_throws_invalid_response(): void
    {
        $this->queue->append($this->jsonResponse(200, ['message' => 'ok']));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $this->expectException(InvalidResponseException::class);

        $client->devices()->list();
    }

    public function test_get_returns_the_device_by_id(): void
    {
        $this->queue->append($this->jsonResponse(200, $this->deviceResource()));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $device = $client->devices()->get('device-1');

        $this->assertSame('device-1', $device->id);
        $this->assertSame('Galaxy S22', $device->name);
        $this->assertSame('android', $device->type);
        $this->assertSame('static-device-token-123', $device->token);
        $this->assertSame('/api/v1/user/devices/device-1', $this->lastRequest()->getUri()->getPath());
    }

    public function test_get_of_an_unknown_device_throws_not_found(): void
    {
        $this->queue->append($this->error(404, 'No device found.'));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $this->expectException(NotFoundException::class);

        $client->devices()->get('device-404');
    }

    public function test_get_of_another_users_device_throws_permission_denied(): void
    {
        $this->queue->append($this->error(403, 'This action is unauthorized.'));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $this->expectException(PermissionDeniedException::class);

        $client->devices()->get('device-1');
    }

    public function test_create_posts_to_the_plural_endpoint(): void
    {
        $this->queue->append($this->jsonResponse(201, $this->deviceResource(['name' => 'Pixel 8'])));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $device = $client->devices()->create('Pixel 8', DeviceType::Android);

        $this->assertSame('device-1', $device->id);
        $this->assertSame('Pixel 8', $device->name);
        $this->assertSame('static-device-token-123', $device->token);

        $this->assertSame('POST', $this->lastRequest()->getMethod());
        $this->assertSame('/api/v1/user/devices', $this->lastRequest()->getUri()->getPath());
        $this->assertSame(
            ['name' => 'Pixel 8', 'type' => 'android'],
            $this->requestBody($this->lastRequest()),
        );
    }

    public function test_create_accepts_a_free_form_device_type_string(): void
    {
        $this->queue->append($this->jsonResponse(201, $this->deviceResource(['name' => 'Hub', 'type' => 'galaxy-s22'])));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $device = $client->devices()->create('Hub', 'galaxy-s22');

        $this->assertSame('galaxy-s22', $device->type);
        $this->assertSame(
            ['name' => 'Hub', 'type' => 'galaxy-s22'],
            $this->requestBody($this->lastRequest()),
        );
    }

    public function test_create_validation_error_exposes_field_errors(): void
    {
        $this->queue->append($this->error(422, 'The given data was invalid.', [
            'type' => ['The type field must be at least 3 characters.'],
        ]));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        try {
            $client->devices()->create('Hub', 'ab');
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertSame(['The type field must be at least 3 characters.'], $e->errorsFor('type'));
        }
    }

    public function test_delete_removes_the_device_by_id(): void
    {
        $this->queue->append($this->jsonResponse(200, ['message' => 'Device removed successfully.']));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $client->devices()->delete('device-1');

        $this->assertSame('DELETE', $this->lastRequest()->getMethod());
        $this->assertSame('/api/v1/user/devices/device-1', $this->lastRequest()->getUri()->getPath());
    }

    public function test_delete_of_another_users_device_throws_permission_denied(): void
    {
        $this->queue->append($this->error(403, 'This action is unauthorized.'));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $this->expectException(PermissionDeniedException::class);

        $client->devices()->delete('device-1');
    }
}
