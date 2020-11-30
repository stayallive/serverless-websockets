<?php

namespace Stayallive\ServerlessWebSockets\Entities;

class User extends Entity
{
    protected string $id;
    protected array  $info;

    public function __construct(string $id, array $info)
    {
        $this->id   = $id;
        $this->info = $info;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getInfo(): array
    {
        return $this->info;
    }
}
