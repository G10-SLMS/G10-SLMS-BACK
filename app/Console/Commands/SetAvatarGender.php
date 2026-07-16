<?php

namespace App\Console\Commands;

use App\Models\Avatar;
use Illuminate\Console\Command;

class SetAvatarGender extends Command
{
    protected $signature = 'avatars:set-gender {--male=} {--female=}';

    protected $description = 'Set gender on avatars by comma-separated ID list, e.g. --male=1,2,3 --female=4,5,6';

    public function handle(): int
    {
        $male = $this->parseIds($this->option('male'));
        $female = $this->parseIds($this->option('female'));

        $updated = 0;
        $updated += Avatar::whereIn('id', $male)->update(['gender' => 'male']);
        $updated += Avatar::whereIn('id', $female)->update(['gender' => 'female']);

        $this->info("Updated {$updated} avatars.");

        return self::SUCCESS;
    }

    private function parseIds(?string $ids): array
    {
        if (! $ids) {
            return [];
        }

        return array_map('intval', explode(',', $ids));
    }
}
