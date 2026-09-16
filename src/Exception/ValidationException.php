<?php

namespace Azelya\SmsService\Exception;

/**
 * Thrown on 422 responses with access to the per-field validation errors.
 */
class ValidationException extends SmsGatewayException
{
    /**
     * The validation errors keyed by field name.
     *
     * @return array<string, string[]>
     */
    public function errors(): array
    {
        $errors = $this->context['errors'] ?? [];

        return is_array($errors) ? $errors : [];
    }

    /**
     * The validation messages for a single field.
     *
     * @return string[]
     */
    public function errorsFor(string $field): array
    {
        $errors = $this->errors()[$field] ?? [];

        return is_array($errors) ? $errors : [];
    }
}
