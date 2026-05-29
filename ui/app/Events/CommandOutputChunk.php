<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommandOutputChunk implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public int $commandLogId,
        public string $chunk,
        public string $stream = 'stdout',
        public ?string $status = null,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('command-output.'.$this->commandLogId);
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'commandLogId' => $this->commandLogId,
            'chunk' => $this->chunk,
            'stream' => $this->stream,
            'status' => $this->status,
        ];
    }
}
