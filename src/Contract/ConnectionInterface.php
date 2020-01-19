<?php

namespace Src\RPCServer\Contract;

interface ConnectionInterface
{
    public function getHost(): string;

    public function getPort(): int;

    public function services($service_name): array;

    public function register();
}