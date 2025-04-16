<?php

declare(strict_types=1);

namespace Pdo;

use function Async\coroutineContext;
use function Async\currentContext;

final class DataBase
{
    private ConnectionPool $pool;
    private \stdClass      $connectionKey;
    
    public function __construct(ConnectionPool $pool)
    {
        $this->pool          = $pool;
        $this->connectionKey = new \stdClass();
    }
    
    private function connection(): PdoProxy
    {
        $context = coroutineContext();
        
        if (!$context->has($this->connectionKey)) {
            $context->set($this->connectionKey, new PdoProxy($this->pool->borrow(), $this->pool));
        }
        
        return $context->get($this->connectionKey);
    }
    
    public function executeAndFetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getConnection(): PdoProxy
    {
        return $this->connection();
    }
}