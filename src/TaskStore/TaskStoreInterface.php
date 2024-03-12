<?php

namespace Webman\Midjourney\TaskStore;

use Webman\Midjourney\Task;
use Webman\Midjourney\TaskCondition;

interface TaskStoreInterface
{
    public function __construct(array $config = [], int $expiredDates = 30);

    public function get(string $taskId): ?Task;

    public function save(Task $task);

    public function delete(string $taskId);

    public function getList($listName): array;

    public function addToList($listName, $taskId): array;

    public function removeFromList($listName, $taskId): array;

}