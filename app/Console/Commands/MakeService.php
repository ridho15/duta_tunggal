<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeService extends Command
{
    protected $signature = 'make:service {name}';
    protected $description = 'Generate a new Service class in app/Services';

    public function handle()
    {
        $name = Str::studly($this->argument('name'));
        $directory = app_path('Services');
        $path = "{$directory}/{$name}.php";

        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        if (File::exists($path)) {
            $this->error("Service '{$name}' already exists!");
            return Command::FAILURE;
        }

        $content = <<<PHP
<?php

namespace App\Services;

class {$name}
{
    // TODO: Implement service logic here
}
PHP;

        File::put($path, $content);

        $this->info("Service {$name} created successfully at app/Services/{$name}.php");
        return Command::SUCCESS;
    }
}
