<?php

use App\Console\Commands\SeedAgentRoster;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('agents:seed-roster', function () {
    $this->call(SeedAgentRoster::class);
})->purpose('Seed the agent roster with 6 core agents (idempotent)');
