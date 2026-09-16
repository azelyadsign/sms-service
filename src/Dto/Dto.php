<?php

namespace Azelya\SmsService\Dto;

/**
 * Base for the read-only data transfer objects returned by the SDK.
 */
abstract class Dto
{
    /**
     * Build the DTO from a decoded gateway response.
     *
     * @param  array<string, mixed>  $data
     */
    abstract public static function fromArray(array $data): static;

    /**
     * Serialize the DTO back to an array. The default implementation relies on
     * public readonly properties; DTOs with nested objects override it.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
