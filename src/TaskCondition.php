<?php

namespace Webman\Midjourney;

use RuntimeException;

class TaskCondition
{
    protected $nonce;

    protected $action;

    protected $prompt;

    protected $messageId;

    protected $messageHash;

    protected $params = [];

    /**
     * @param $nonce
     * @return $this
     */
    public function nonce($nonce): TaskCondition
    {
        $this->nonce = $nonce;
        return $this;
    }

    /**
     * @param $action
     * @return $this
     */
    public function action($action): TaskCondition
    {
        $this->action = $action;
        return $this;
    }

    /**
     * @param $prompt
     * @return $this
     */
    public function prompt($prompt): TaskCondition
    {
        $this->prompt = $prompt;
        return $this;
    }

    /**
     * @param $messageId
     * @return $this
     */
    public function messageId($messageId): TaskCondition
    {
        $this->messageId = $messageId;
        return $this;
    }

    /**
     * @param $messageHash
     * @return $this
     */
    public function messageHash($messageHash): TaskCondition
    {
        $this->messageHash = $messageHash;
        return $this;
    }

    /**
     * @param $data
     * @return $this
     */
    public function params($data): TaskCondition
    {
        $this->params = $data;
        return $this;
    }

    /**
     * @param Task $task
     * @return bool
     */
    public function match(Task $task): bool
    {
        if ($this->nonce === null && $this->action === null && $this->prompt === null && $this->messageId === null && $this->messageHash === null && empty($this->params)) {
            Log::error(new RuntimeException('TaskCondition is empty'));
            return false;
        }
        if ($this->nonce !== null && $this->nonce !== $task->nonce()) {
            return false;
        }
        if ($this->action !== null && $this->action !== $task->action()) {
            return false;
        }
        if ($this->prompt !== null && $this->prompt !== $task->prompt()) {
            return false;
        }
        if ($this->messageId !== null && $this->messageId !== $task->messageId()) {
            return false;
        }
        if ($this->messageHash !== null && $this->messageHash !== $task->messageHash()) {
            return false;
        }
        $params = $task->params();
        foreach ($this->params as $key => $value) {
            if (!isset($params[$key]) || $params[$key] != $value) {
                return false;
            }
        }
        return true;
    }
}