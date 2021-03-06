<?php

namespace RestApiBundle\Model\OpenApi\Request;

use RestApiBundle;

class RequestModel implements RestApiBundle\Model\OpenApi\Request\RequestInterface
{
    private string $class;
    private bool $nullable;

    public function __construct(string $class, bool $nullable)
    {
        $this->class = $class;
        $this->nullable = $nullable;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function getNullable(): bool
    {
        return $this->nullable;
    }
}
