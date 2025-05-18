<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class MakeView extends Command
{
    protected $signature = 'make:view {name}';
    protected $description = 'Create a new blade view file';

    public function handle()
    {
        $name = $this->argument('name');
        $filesystem = new \Illuminate\Filesystem\Filesystem();

        // Path'i oluştur (blade uzantılı)
        $path = resource_path("views/{$name}.blade.php");

        // Klasör yolunu al (örneğin: resources/views/devices)
        $directory = dirname($path);

        // Klasör yoksa oluştur
        if (! $filesystem->isDirectory($directory)) {
            $filesystem->makeDirectory($directory, 0755, true);
        }

        if ($filesystem->exists($path)) {
            $this->error("View {$name} already exists!");
            return 1;
        }

        $stub = "<!-- New Blade View: {$name} -->\n<h1>Hello from {$name}</h1>";

        $filesystem->put($path, $stub);

        $this->info("View {$name} created successfully at {$path}.");
        return 0;
    }

}
