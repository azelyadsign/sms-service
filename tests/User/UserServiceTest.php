<?php

namespace Azelya\SmsService\Tests\User;

use Azelya\SmsService\Config;
use Azelya\SmsService\Tests\TestCase;

final class UserServiceTest extends TestCase
{
    public function test_me_returns_the_authenticated_user(): void
    {
        $this->queue->append($this->jsonResponse(200, ['data' => $this->userResource()]));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $user = $client->user()->me();

        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $user->id);
        $this->assertSame('John Doe', $user->name);
        $this->assertSame('john@example.com', $user->email);
        $this->assertSame('2026-08-12T10:00:00.000000Z', $user->createdAt);
        $this->assertSame('/api/v1/user', $this->lastRequest()->getUri()->getPath());
    }

    public function test_me_passes_include_relations(): void
    {
        $this->queue->append($this->jsonResponse(200, ['data' => $this->userResource()]));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $client->user()->me(['roles', 'device']);

        parse_str($this->lastRequest()->getUri()->getQuery(), $query);

        $this->assertSame('roles,device', $query['include']);
    }

    public function test_me_parses_included_roles_and_permissions(): void
    {
        $this->queue->append($this->jsonResponse(200, [
            'data' => $this->userResource(),
            'included' => [
                ['id' => '1', 'type' => 'roles', 'attributes' => ['name' => 'Client']],
                ['id' => '2', 'type' => 'permissions', 'attributes' => ['name' => 'send-sms']],
            ],
        ]));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $user = $client->user()->me(['roles', 'permissions']);

        $this->assertSame(['Client'], $user->roles);
        $this->assertSame(['send-sms'], $user->permissions);
    }

    public function test_me_requires_authentication(): void
    {
        $this->queue->append($this->error(401, 'Unauthenticated.'));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $this->expectException(\Azelya\SmsService\Exception\AuthenticationException::class);

        $client->user()->me();
    }
}
