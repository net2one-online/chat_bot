<?php

namespace App\Services\Agent\Tools;

interface ToolInterface
{
    public function getName(): string;

    public function getDescription(): string;

    public function getParameters(): array;

    public function execute(array $parameters): mixed;
}
