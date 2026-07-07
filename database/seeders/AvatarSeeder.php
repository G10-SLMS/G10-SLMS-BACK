<?php

namespace Database\Seeders;

use App\Models\Avatar;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class AvatarSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultPath = public_path('avatars/defaults');
        
        if (!file_exists($defaultPath)) {
            $this->command->info('Default avatars directory not found. Skipping avatar seeding.');
            return;
        }

        $files = glob($defaultPath . '/avatar-*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
        
        if (empty($files)) {
            $this->command->info('No default avatars found. Skipping avatar seeding.');
            return;
        }

        $this->command->info('Seeding default avatars...');
        
        foreach ($files as $file) {
            $filename = basename($file);
            $relativePath = 'avatars/defaults/' . $filename;
            
            // Check if avatar already exists
            $existingAvatar = Avatar::where('path', $relativePath)->first();
            
            if (!$existingAvatar) {
                Avatar::create([
                    'filename' => $filename,
                    'path' => $relativePath,
                    'is_default' => true,
                ]);
                
                $this->command->info("✓ Seeded: {$filename}");
            } else {
                $this->command->info("⊘ Skipped: {$filename} (already exists)");
            }
        }
        
        $this->command->info('Default avatars seeded successfully!');
    }
}