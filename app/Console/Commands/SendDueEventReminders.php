<?php

namespace App\Console\Commands;

use App\Services\EventNotificationService;
use Illuminate\Console\Command;

class SendDueEventReminders extends Command
{
    protected $signature = 'events:send-reminders';
    protected $description = 'Send due reminders to approved event registrants';

    public function handle(EventNotificationService $service): int
    {
        $service->sendDueReminders();
        $this->info('Due event reminders processed.');

        return self::SUCCESS;
    }
}
