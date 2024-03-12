<?php

namespace Webman\Midjourney\TaskStore;

use Throwable;
use Webman\Midjourney\BusinessException;
use Webman\Midjourney\Log;
use Webman\Midjourney\Task;
use Workerman\Timer;

class File implements TaskStoreInterface
{

    protected $dataPath;

    protected $taskPath;

    protected $listPath;

    protected $taskCaches = [];

    protected $listCaches = [];

    protected $cacheSize = 100;

    protected $expiredDates = 30;

    /**
     * @param array $config
     * @param int $expiredDates
     * @throws BusinessException
     */
    public function __construct(array $config = [], int $expiredDates = 30)
    {
        $path = $config['dataPath'] ?? '';
        if (!$path) {
            throw new BusinessException('data_path empty');
        }
        $this->taskPath = $path . DIRECTORY_SEPARATOR . 'tasks';
        $this->listPath = $path . DIRECTORY_SEPARATOR . 'lists';
        $this->cacheSize = $config['cacheSize'] ?? $this->cacheSize;
        $this->mkdir();
        $this->expiredDates = $expiredDates ?: $this->expiredDates;
        $this->createClearTaskTimer();
    }

    protected function mkdir()
    {
        $paths = [$this->taskPath, $this->listPath];
        foreach ($paths as $path) {
            if (!is_dir($path) && !mkdir($path, 0777, true)) {
                throw new BusinessException("Make dir $path fail");
            }
        }
    }

    protected function createClearTaskTimer()
    {
        // 找到第二天凌晨1点的时间戳
        $seconds = strtotime(date('Y-m-d', time() + 86400)) - time() + 3600;
        Timer::add($seconds, function () {
            $this->clearExpiredTasks($this->expiredDates);
        }, [], false);
    }

    protected function clearExpiredTasks($expiredDates) {
        $this->createClearTaskTimer();
        $dirs = scandir($this->taskPath);
        try {
            foreach ($dirs as $dir) {
                if ($dir === '.' || $dir === '..') {
                    continue;
                }
                $path = $this->taskPath . DIRECTORY_SEPARATOR . $dir;
                if (is_dir($path)) {
                    $date = strtotime($dir);
                    if ($date < time() - $expiredDates * 86400) {
                        // 删除这个目录的所有文件
                        $files = scandir($path);
                        foreach ($files as $file) {
                            if ($file === '.' || $file === '..') {
                                continue;
                            }
                            $file = $path . DIRECTORY_SEPARATOR . $file;
                            if (is_file($file)) {
                                unlink($file);
                            }
                        }
                        remove_dir($path);
                    }
                }
            }
        } catch (Throwable $e) {
            Log::error($e);
        }
    }


    public function get(string $taskId): ?Task
    {
        if (isset($this->taskCaches[$taskId])) {
            return $this->taskCaches[$taskId];
        }
        $file = $this->getTaskFilePath($taskId);
        if (!$file || !is_file($file)) {
            return null;
        }
        $data = file_get_contents($file);
        $task = $data ? Task::unserialize($data) : null;
        if ($task) {
            $this->cacheTask($task);
        }
        return $task;
    }

    public function save(Task $task)
    {
        $file = $this->getTaskFilePath($task->id());
        if (!$file) {
            return;
        }
        file_put_contents($file, $task);
        $this->cacheTask($task);
    }


    public function delete(string $taskId)
    {
        unset($this->taskCaches[$taskId]);
        $file = $this->getTaskFilePath($taskId);
        if (!$file) {
            return;
        }
        if (is_file($file)) {
            unlink($file);
        }
    }

    public function getList($listName): array
    {
        if (isset($this->listCaches[$listName])) {
            return $this->listCaches[$listName];
        }
        $file = $this->getListFilePath($listName);
        if (is_file($file) && $content = file_get_contents($file)) {
            return json_decode($content, true) ?: [];
        }
        return [];
    }

    public function addToList($listName, $taskId): array
    {
        $list = $this->getList($listName);
        if (in_array($taskId, $list)) {
            return $list;
        }
        $list[] = $taskId;
        $this->saveList($listName, $list);
        return $list;
    }

    public function removeFromList($listName, $taskId): array
    {
        $list = $this->getList($listName);
        foreach ($list as $key => $item) {
            if ($taskId === $item) {
                unset($list[$key]);
            }
        }
        $list = array_values($list);
        $this->saveList($listName, $list);
        return $list;
    }

    protected function saveList($listName, array $list)
    {
        $this->listCaches[$listName] = $list;
        $this->cacheList($listName, $list);
        $file = $this->getListFilePath($listName);
        file_put_contents($file, json_encode($list));
    }

    /**
     * @param $taskId
     * @return string
     */
    protected function getTaskFilePath($taskId): string
    {
        if (!$this->checkTaskId($taskId)) {
            return '';
        }
        $time = substr($taskId, 0, 10);
        $date = date('Y-m-d', $time);
        $path = $this->taskPath . DIRECTORY_SEPARATOR . $date;
        if (!is_dir($path) && !mkdir($path, 0777, true)) {
            Log::error(new \Exception("Make dir $path fail"));
            return '';
        }
        return $path . DIRECTORY_SEPARATOR . "task-$taskId";
    }

    /**
     * @param $listName
     * @return string
     * @throws BusinessException
     */
    protected function getListFilePath($listName): string
    {
        $this->checkListId($listName);
        return $this->listPath . DIRECTORY_SEPARATOR . $listName;
    }

    /**
     * @param Task $task
     * @return void
     */
    protected function cacheTask(Task $task)
    {
        $this->taskCaches[$task->id()] = $task;
        if (count($this->taskCaches) > $this->cacheSize) {
            reset($this->taskCaches);
            unset($this->taskCaches[key($this->taskCaches)]);
        }
    }

    /**
     * @param $listName
     * @param $list
     * @return void
     */
    protected function cacheList($listName, $list)
    {
        $this->listCaches[$listName] = $list;
        if (count($this->listCaches) > $this->cacheSize) {
            reset($this->listCaches);
            unset($this->listCaches[key($this->listCaches)]);
        }
    }

    /**
     * 判断taskId是否合法
     * @param $taskId
     * @return bool
     */
    protected function checkTaskId($taskId): bool
    {
        if (!preg_match('/^\d+$/', $taskId)) {
            Log::error(new \Exception('Bad taskId ' . var_export($taskId, true)));
            return false;
        }
        return true;
    }

    /**
     * 判断taskId是否合法
     * @param $listId
     * @return void
     * @throws BusinessException
     */
    protected function checkListId($listId)
    {
        if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $listId)) {
            throw new BusinessException('Bad listId ' . var_export($listId, true));
        }
    }
}