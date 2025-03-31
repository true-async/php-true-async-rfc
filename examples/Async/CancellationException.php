<?php

declare(strict_types=1);

namespace Async;

use Throwable;

class CancellationException implements Throwable
{
    public function getMessage(): string
    {
    }
    
    public function getCode()
    {
    }
    
    public function getFile(): string
    {
    }
    
    public function getLine(): int
    {
    }
    
    public function getTrace(): array
    {
    }
    
    public function getTraceAsString(): string
    {
    }
    
    public function getPrevious(): ?Throwable
    {
    }
    
    public function __toString(): string
    {
    }
}