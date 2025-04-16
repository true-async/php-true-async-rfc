<?php

declare(strict_types=1);

namespace Pdo;

final class PdoProxy
{
    public function __construct(private \PDO $pdo, private ConnectionPool $connectionPool)
    {
    }
    
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    public function prepare(string $sql): \PDOStatement
    {
        return $this->pdo->prepare($sql);
    }
    
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }
    
    public function commit(): bool
    {
        return $this->pdo->commit();
    }
    
    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }
    
    public function __destruct()
    {
        $this->connectionPool->release($this->pdo);
    }
}