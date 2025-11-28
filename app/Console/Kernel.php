<?php
namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Artisan;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\RegisterMpesaUrls::class,
    ];
}

// Add this to your routes/console.php file
Artisan::command('mpesa:register', function () {
    $this->call('mpesa:register-urls');
});


