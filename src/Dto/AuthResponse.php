<?php

namespace Azelya\SmsService\Dto;

use Azelya\SmsService\Exception\InvalidResponseException;

final class AuthResponse extends Dto
{
    public function __construct(
        public readonly User $user,
        public readonly string $accessToken,
        public readonly string $tokenType = 'Bearer',
    ) {
    }

    public static function fromArray(array $data): static
    {
        $userResource = $data['user']['data'] ?? null;

        if (! is_array($userResource)) {
            throw new InvalidResponseException('The SMS gateway returned an auth response without a user resource.');
        }

        return new self(
            user: User::fromResource($userResource),
            accessToken: (string) ($data['access_token'] ?? ''),
            tokenType: (string) ($data['token_type'] ?? 'Bearer'),
        );
    }

    public function toArray(): array
    {
        return [
            'user' => $this->user->toArray(),
            'access_token' => $this->accessToken,
            'token_type' => $this->tokenType,
        ];
    }
}
