<?php

namespace App\Console\Commands;

use App\Models\Avatar;
use Illuminate\Console\Command;

class ImportGenderedAvatars extends Command
{
    protected $signature = 'avatars:import-gendered {--path=avatars/gendered : Public path (relative) that holds the gendered avatar images}';
    protected $description = 'Import the male/female avatar image set (avatar_{n}_{gender}.jpg, default_profile_{gender}.jpg) into the avatars table';

    private const SELECTABLE_PATTERN = '/^avatar_(\d+)_(male|female)\.\w+$/i';
    private const FALLBACK_PATTERN = '/^default_profile_(male|female)\.\w+$/i';

    public function handle(): int
    {
        $relativeDir = trim((string) $this->option('path'), '/');
        $directory = public_path($relativeDir);

        if (! is_dir($directory)) {
            $this->error("Directory not found: {$directory}");

            return self::FAILURE;
        }

        $files = collect(scandir($directory))
            ->reject(fn (string $file) => in_array($file, ['.', '..']))
            ->values();

        $imported = 0;
        $skipped = 0;

        foreach ($files as $file) {
            $relativePath = "{$relativeDir}/{$file}";

            if (preg_match(self::SELECTABLE_PATTERN, $file, $matches)) {
                Avatar::updateOrCreate(
                    ['filename' => $file],
                    [
                        'path' => $relativePath,
                        'gender' => strtolower($matches[2]),
                        'is_default' => true,
                    ]
                );
                $imported++;

                continue;
            }

            if (preg_match(self::FALLBACK_PATTERN, $file, $matches)) {
                Avatar::updateOrCreate(
                    ['filename' => $file],
                    [
                        'path' => $relativePath,
                        'gender' => strtolower($matches[1]),
                        // Not part of the selectable pool - it's the automatic
                        // fallback used when a user hasn't picked one yet.
                        'is_default' => false,
                    ]
                );
                $imported++;

                continue;
            }

            $skipped++;
        }

        $this->info("Imported {$imported} avatar(s).");

        if ($skipped > 0) {
            $this->comment("Skipped {$skipped} file(s) that didn't match the expected naming pattern.");
        }

        return self::SUCCESS;
    }
}
