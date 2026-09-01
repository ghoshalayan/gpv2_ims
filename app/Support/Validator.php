<?php
declare(strict_types=1);

namespace App\Support;

class Validator
{
    /** @var array<string, array<int, string>> */
    private array $errors = [];

    public function __construct(private array $data) {}

    public static function make(array $data): self
    {
        return new self($data);
    }

    public function required(string $field, ?string $message = null): self
    {
        $value = \trim((string) ($this->data[$field] ?? ''));
        if ($value === '') {
            $this->errors[$field][] = $message ?? "{$this->label($field)} is required.";
        }
        return $this;
    }

    public function max(string $field, int $length, ?string $message = null): self
    {
        $value = (string) ($this->data[$field] ?? '');
        if (\mb_strlen($value) > $length) {
            $this->errors[$field][] = $message ?? "{$this->label($field)} must not exceed {$length} characters.";
        }
        return $this;
    }

    public function min(string $field, int $length, ?string $message = null): self
    {
        $value = (string) ($this->data[$field] ?? '');
        if (\mb_strlen($value) < $length) {
            $this->errors[$field][] = $message ?? "{$this->label($field)} must be at least {$length} characters.";
        }
        return $this;
    }

    /** @param array<int, string> $allowed */
    public function in(string $field, array $allowed, ?string $message = null): self
    {
        $value = (string) ($this->data[$field] ?? '');
        if (!\in_array($value, $allowed, true)) {
            $this->errors[$field][] = $message ?? "{$this->label($field)} is invalid.";
        }
        return $this;
    }

    /** Validates an Indian GSTIN (15 chars: 2 state digits + 5 PAN letters + 4 digits + entity + Z + checksum). Skipped if empty. */
    public function gstin(string $field, ?string $message = null): self
    {
        $value = \strtoupper(\trim((string) ($this->data[$field] ?? '')));
        if ($value === '') {
            return $this;
        }
        if (!\preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $value)) {
            $this->errors[$field][] = $message ?? 'GSTIN must be a valid 15-character format.';
        }
        return $this;
    }

    /** Validates an Indian phone (10 digits, optional +91 / leading 0). Skipped if empty. */
    public function phone(string $field, ?string $message = null): self
    {
        $value = \preg_replace('/\s+/', '', (string) ($this->data[$field] ?? ''));
        if ($value === null || $value === '') {
            return $this;
        }
        if (!\preg_match('/^(\+91|0)?[6-9][0-9]{9}$/', $value)) {
            $this->errors[$field][] = $message ?? "{$this->label($field)} must be a valid 10-digit Indian phone number.";
        }
        return $this;
    }

    /** Validates an Indian PAN (5 letters + 4 digits + 1 letter). Skipped if empty. */
    public function pan(string $field, ?string $message = null): self
    {
        $value = \strtoupper(\trim((string) ($this->data[$field] ?? '')));
        if ($value === '') {
            return $this;
        }
        if (!\preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $value)) {
            $this->errors[$field][] = $message ?? 'PAN must be 10 characters: 5 letters, 4 digits, 1 letter.';
        }
        return $this;
    }

    /** Validates an Indian Aadhaar (12 digits). Skipped if empty. */
    public function aadhaar(string $field, ?string $message = null): self
    {
        $value = \preg_replace('/\s+/', '', (string) ($this->data[$field] ?? ''));
        if ($value === null || $value === '') {
            return $this;
        }
        if (!\preg_match('/^\d{12}$/', $value)) {
            $this->errors[$field][] = $message ?? 'Aadhaar must be exactly 12 digits.';
        }
        return $this;
    }

    /** Validates an Indian IFSC (4 letters, 0, then 6 alphanumeric). Skipped if empty. */
    public function ifsc(string $field, ?string $message = null): self
    {
        $value = \strtoupper(\trim((string) ($this->data[$field] ?? '')));
        if ($value === '') {
            return $this;
        }
        if (!\preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $value)) {
            $this->errors[$field][] = $message ?? 'IFSC must be 11 characters: 4 letters + 0 + 6 alphanumeric.';
        }
        return $this;
    }

    /** Ensures $field matches $confirmationField. Used for password + password_confirmation. */
    public function confirmed(string $field, string $confirmationField, ?string $message = null): self
    {
        $value   = (string) ($this->data[$field] ?? '');
        $confirm = (string) ($this->data[$confirmationField] ?? '');
        if ($value !== $confirm) {
            $this->errors[$field][] = $message ?? "{$this->label($field)} confirmation does not match.";
        }
        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return !$this->fails();
    }

    /** @return array<string, array<int, string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        foreach ($this->errors as $messages) {
            return $messages[0] ?? null;
        }
        return null;
    }

    private function label(string $field): string
    {
        return \ucfirst(\str_replace('_', ' ', $field));
    }
}
