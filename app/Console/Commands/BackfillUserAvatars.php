<?php

namespace App\Console\Commands;

use App\Models\Avatar;
use App\Models\User;
use Illuminate\Console\Command;

class BackfillUserAvatars extends Command
{
    protected $signature = 'users:backfill-avatars';

    protected $description = 'Assign a default avatar to existing users that have no avatar_id set';

    public function handle(): int
    {
        $users = User::whereNull('avatar_id')->get();
        $updated = 0;

        foreach ($users as $user) {
            $avatar = Avatar::fallbackFor($user->gender);

            if ($avatar) {
                $user->avatar_id = $avatar->id;
                $user->save();
                $updated++;
            }
        }

        $this->info("Updated {$updated} of {$users->count()} user(s) missing an avatar.");

        return self::SUCCESS;
    }
}
