<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TriggerNotification extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:trigger-notification';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle() {
        broadcast(new \App\Events\UserNotificationEvent(10, [
                    'type' => 'chat',
                    'title' => 'Test',
                    'body' => 'Hello World',
                    'message_id' => 999,
        ]));

        $this->info('Notification event broadcasted!');
    }
}
