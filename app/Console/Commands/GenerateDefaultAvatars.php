<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class GenerateDefaultAvatars extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'avatars:generate-defaults {--count=20 : Number of avatars to generate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate 20 default avatar images using DiceBear API';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $count = $this->option('count');
        $defaultPath = public_path('avatars/defaults');
        
        // Create directory if it doesn't exist
        if (!file_exists($defaultPath)) {
            mkdir($defaultPath, 0755, true);
            $this->info("Created directory: {$defaultPath}");
        }

        $this->info("Generating {$count} default avatars...");

        for ($i = 1; $i <= $count; $i++) {
            $filename = "avatar-{$i}.png";
            $filepath = $defaultPath . '/' . $filename;
            
            // Skip if file already exists
            if (file_exists($filepath)) {
                $this->info("Skipping {$filename} (already exists)");
                continue;
            }

            // Generate avatar URL using DiceBear API
            $avatarUrl = "https://api.dicebear.com/7.x/avataaars/svg?seed={$i}";
            
            try {
                // Download the avatar
                $response = Http::timeout(30)->get($avatarUrl);
                
                if ($response->successful()) {
                    // Save the file
                    file_put_contents($filepath, $response->body());
                    $this->info("✓ Generated: {$filename}");
                } else {
                    $this->error("✗ Failed to generate: {$filename}");
                }
            } catch (\Exception $e) {
                $this->error("✗ Error generating {$filename}: " . $e->getMessage());
            }
        }

        $this->info("\nDefault avatars generated successfully!");
        $this->info("Location: {$defaultPath}");
        
        return Command::SUCCESS;
    }
}