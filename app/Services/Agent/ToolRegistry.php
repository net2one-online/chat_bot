<?php

namespace App\Services\Agent;

use App\Services\Agent\Tools\CreateActivityTool;
use App\Services\Agent\Tools\SearchCompanyTool;
use App\Services\Agent\Tools\SearchContactTool;
use App\Services\Agent\Tools\SearchDealTool;
use App\Services\Agent\Tools\SendFileTool;
use App\Services\Agent\Tools\TransferHumanTool;

class ToolRegistry
{
    protected array $tools = [];

    public function __construct()
    {
        $this->register(new SearchContactTool);
        $this->register(new SearchDealTool);
        $this->register(new SearchCompanyTool);
        $this->register(new CreateActivityTool);
        $this->register(new SendFileTool);
        $this->register(new TransferHumanTool);
    }

    public function register($tool): void
    {
        $this->tools[$tool->getName()] = $tool;
    }

    public function get(string $name)
    {
        return $this->tools[$name] ?? null;
    }

    public function getAll(): array
    {
        return $this->tools;
    }

    public function execute(string $name, array $parameters): mixed
    {
        $tool = $this->get($name);

        if (! $tool) {
            return ['error' => "Herramienta no encontrada: {$name}"];
        }

        return $tool->execute($parameters);
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }
}
