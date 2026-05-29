<?php

namespace App\Console\Commands;

use App\Services\DiskSyncService;
use Illuminate\Console\Command;

class SyncDiskState extends Command
{
    protected $signature = 'disk:sync {--system= : signal7 or hyper7} {--task= : specific task ID}';

    protected $description = 'Sync .signal/ and .hyper/ disk state into the database cache';

    public function handle(DiskSyncService $sync): int
    {
        $system = $this->option('system');
        $taskId = $this->option('task');

        if ($taskId) {
            if (! $system) {
                $this->error('--task requires --system');

                return 1;
            }
            $sync->syncTask($system, $taskId);
            $this->info("Synced single task: {$system}/{$taskId}");

            return 0;
        }

        if ($system) {
            $counts = $sync->syncSystem($system);
            $this->info("Synced {$system}: {$counts['tasks']} tasks, {$counts['assets']} assets, {$counts['backlog']} backlog, {$counts['recipes']} recipes");

            return 0;
        }

        $results = $sync->syncAll();
        foreach (['signal7', 'hyper7'] as $sys) {
            $c = $results[$sys];
            $this->info("Synced {$sys}: {$c['tasks']} tasks, {$c['assets']} assets, {$c['backlog']} backlog, {$c['recipes']} recipes");
        }

        return 0;
    }
}
