<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Message;
use App\Services\MessageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60; // Reintentar después de 60 segundos

    public function __construct(
        public Message $message
    ) {}

    public function handle(MessageService $service): void
    {
        $service->send($this->message);
    }

    public function failed(\Throwable $exception): void
    {
        $this->message->markAsFailed($exception->getMessage());
    }
}
