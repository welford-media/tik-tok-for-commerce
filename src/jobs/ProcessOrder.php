<?php
namespace WelfordMedia\CraftTikTok\jobs;

use craft\queue\BaseJob;
use WelfordMedia\CraftTikTok\TikTok;
use yii\queue\RetryableJobInterface;

class ProcessOrder extends BaseJob implements RetryableJobInterface
{
    public int $id;

    protected function defaultDescription(): string
    {
        return "Processing TikTok Order";
    }

    public function getTtr(): int
    {
        return 30;
    }

    public function canRetry($attempt, $error): int
    {
        return 3;
    }

    public function execute($queue): void
    {
        $commerce = TikTok::getInstance()->commerce;
        $commerce->processOrder($this->id);
    }
}
