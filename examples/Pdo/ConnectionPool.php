<?php

declare(strict_types=1);

namespace Pdo;

final class ConnectionPool
{
    public function borrow(): \PDO
    {
    }
    
    public function release(\PDO $connection): void
    {
    
    }
}