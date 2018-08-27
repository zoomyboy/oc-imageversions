<?php namespace Zoomyboy\Imageversions\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use System\Models\File;

class FilesVersions extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'files:versions';

    /**
     * @var string The console command description.
     */
    protected $description = 'Generates image thumbnails for System files with nice slugged names';

    /**
     * Execute the console command.
     * @return void
     */
    public function handle()
    {
        $versions = app('filesversions')->getVersions();

        if ($this->option('model')) {
            $versions = $versions->filter(function($version) {
                return $version->model == $this->option('model');
            });
        }

        $i = 0;

        foreach($versions as $version) {
            $images = File::where('attachment_type', $version->model)
                ->where('content_type', 'image/jpeg')
                ->get();

            foreach($images as $image) {
                $i += $image->generateSizes(
                    $version->sizes,
                    $version->dir.'/'.str_slug($image->attachment->title)
                );
            }
        }

        $this->info($i.' Bilder wurden erstellt.');
    }

    /**
     * Get the console command arguments.
     * @return array
     */
    protected function getArguments()
    {
        return [];
    }

    /**
     * Get the console command options.
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['model', null, InputOption::VALUE_OPTIONAL, 'Generate Images only for this model', null]
        ];
    }
}
