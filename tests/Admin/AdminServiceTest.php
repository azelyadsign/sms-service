<?php

namespace Azelya\SmsService\Tests\Admin;

use Azelya\SmsService\Config;
use Azelya\SmsService\Exception\ConflictException;
use Azelya\SmsService\Exception\PermissionDeniedException;
use Azelya\SmsService\Tests\TestCase;

final class AdminServiceTest extends TestCase
{
    public function test_list_users_parses_the_paginated_collection(): void
    {
        $this->queue->append($this->jsonResponse(200, [
            'data' => [
                $this->userResource('user-1', 'Alice', 'alice@example.com'),
                $this->userResource('user-2', 'Bob', 'bob@example.com'),
            ],
            'links' => [
                'first' => 'https://smsgate.test/api/v1/admin/users?page=1',
                'last' => 'https://smsgate.test/api/v1/admin/users?page=1',
                'prev' => null,
                'next' => null,
            ],
            'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 2],
        ]));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $page = $client->admin()->listUsers();

        $this->assertCount(2, $page->users);
        $this->assertSame('Bob', $page->users[1]->name);
        $this->assertSame(2, $page->total());
        $this->assertSame(1, $page->currentPage());
        $this->assertSame(1, $page->lastPage());
        $this->assertSame(15, $page->perPage());
        $this->assertNull($page->nextPage());
        $this->assertNull($page->prevPage());
        $this->assertSame('/api/v1/admin/users', $this->lastRequest()->getUri()->getPath());
    }

    public function test_list_users_passes_sort_and_per_page(): void
    {
        $this->queue->append($this->jsonResponse(200, ['data' => [], 'meta' => []]));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $client->admin()->listUsers(['sort' => '-created_at', 'per_page' => 50]);

        parse_str($this->lastRequest()->getUri()->getQuery(), $query);

        $this->assertSame('-created_at', $query['sort']);
        $this->assertSame('50', $query['per_page']);
    }

    public function test_approve_returns_the_updated_user(): void
    {
        $this->queue->append($this->jsonResponse(200, [
            'message' => 'User approved successfully.',
            'user' => ['data' => $this->userResource()],
        ]));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $user = $client->admin()->approve('550e8400-e29b-41d4-a716-446655440000');

        $this->assertSame('john@example.com', $user->email);
        $this->assertSame('PATCH', $this->lastRequest()->getMethod());
        $this->assertSame(
            '/api/v1/admin/users/550e8400-e29b-41d4-a716-446655440000/approve',
            $this->lastRequest()->getUri()->getPath(),
        );
    }

    public function test_approve_of_already_approved_user_throws_conflict(): void
    {
        $this->queue->append($this->error(409, 'User is already approved.'));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('User is already approved.');

        $client->admin()->approve('user-1');
    }

    public function test_revoke_returns_the_updated_user(): void
    {
        $this->queue->append($this->jsonResponse(200, [
            'message' => 'User roles revoked successfully.',
            'user' => ['data' => $this->userResource()],
        ]));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $user = $client->admin()->revoke('user-1');

        $this->assertSame('john@example.com', $user->email);
        $this->assertSame('/api/v1/admin/users/user-1/revoke', $this->lastRequest()->getUri()->getPath());
    }

    public function test_revoke_without_roles_throws_conflict(): void
    {
        $this->queue->append($this->error(409, 'User has no roles to revoke.'));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $this->expectException(ConflictException::class);

        $client->admin()->revoke('user-1');
    }

    public function test_admin_endpoint_without_admin_role_throws_permission_denied(): void
    {
        $this->queue->append($this->error(403, 'This action is unauthorized.'));

        $client = $this->client(new Config('https://smsgate.test/api/v1', token: 't'));

        $this->expectException(PermissionDeniedException::class);

        $client->admin()->listUsers();
    }
}
