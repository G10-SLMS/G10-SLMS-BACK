<?php

namespace App\Console\Commands;

use App\Models\Avatar;
use Illuminate\Console\Command;

class BackfillAvatarGender extends Command
{
    protected $signature = 'avatars:backfill-gender';

    protected $description = 'Assign male/female to existing avatars that have no gender set (splits evenly by id)';

    public function handle(): int
    {
        $avatars = Avatar::whereNull('gender')->orderBy('id')->get();

        foreach ($avatars as $index => $avatar) {
            $avatar->gender = $index % 2 === 0 ? 'male' : 'female';
            $avatar->save();
        }

        $this->info("Updated {$avatars->count()} avatars.");

        return self::SUCCESS;
    }
}
