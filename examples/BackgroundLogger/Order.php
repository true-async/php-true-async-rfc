<?php

declare(strict_types=1);

namespace BackgroundLogger;

class Order
{
    public function __construct(
        public string $id,
        public string $userEmail
    ) {}
    
}