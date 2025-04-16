<?php

declare(strict_types=1);

namespace Pdo;

use function Async\coroutineContext;

final class DataBase
{
    private ConnectionPool $pool;
    private \stdClass      $connectionKey;
    
    public function __construct(ConnectionPool $pool)
    {
        $this->pool          = $pool;
        $this->connectionKey = new \stdClass();
    }
    
    public function getConnectionFromContext(): PdoProxy
    {
        $context = coroutineContext();
        
        if ($context->has($this->connectionKey)) {
            return $context->get($this->connectionKey);
        } else {
            $context->set($this->connectionKey, new PdoProxy($this->pool->borrow(), $this->pool));
            return $context->get($this->connectionKey);
        }
    }
    
    public function getConnection(): PdoProxy
    {
        return new PdoProxy($this->pool->borrow(), $this->pool);
    }
}