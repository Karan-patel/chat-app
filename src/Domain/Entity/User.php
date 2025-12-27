<?php
declare(strict_types=1);

namespace App\Domain\Entity;

final class User
{
    public function __construct(
        private readonly int    $id,
        private readonly string $identifier
    )
    {}

    public function id(): int
    {
        return $this->id;
    }

    public function identifier(): string
    {
        return $this->identifier;
    }
}