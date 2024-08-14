<?php
namespace WelfordMedia\CraftTikTok\jobs;

use craft\queue\BaseJob;
use WelfordMedia\CraftTikTok\TikTok;
use yii\queue\RetryableJobInterface;

class SyncProduct extends BaseJob implements RetryableJobInterface
{
    public int $id;

    protected function defaultDescription(): string
    {
        return "Syncing Product";
    }

    public function getTtr(): int
    {
        return 15;
    }

    public function canRetry($attempt, $error): int
    {
        return 3;
    }

    public function execute($queue): void
    {
        $tiktok = TikTok::getInstance()->tiktok;
        $tiktok->syncProduct($this->id);
    }
}
