<?php namespace Zoomyboy\Imageversions;

use Backend;
use System\Classes\PluginBase;
use Zoomyboy\Imageversions\Console\FilesVersions;
use Intervention\Image\ImageManagerStatic as Image;

/**
 * imageversions Plugin Information File
 */
class Plugin extends PluginBase
{
    /**
     * Returns information about this plugin.
     *
     * @return array
     */
    public function pluginDetails()
    {
        return [
            'name'        => 'imageversions',
            'description' => 'No description provided yet...',
            'author'      => 'zoomyboy',
            'icon'        => 'icon-leaf'
        ];
    }

    /**
     * Register method, called when the plugin is first registered.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('filesversions', function() {
            return new Class {
                private $versions = [];

                public function getVersions() {
                    return collect($this->versions);
                }

                public function byModelName($model) {
                    return $this->getVersions()->first(function($version) use ($model) {
                        return $version->model == $model;
                    });
                }

                public function register($sizes, $model, $dir) {
                    $this->versions[] = (object) compact('sizes', 'model', 'dir');
                }
            };
        });
    }

    /**
     * Boot method, called right before the request route.
     *
     * @return array
     */
    public function boot()
    {
        $this->registerConsoleCommand('files:versions', FilesVersions::class);

        \System\Models\File::extend(function($file) {
            $file->addDynamicMethod('generateSizes', function($sizes, $dir) use ($file) {
                $originalSize = getimagesize(\Storage::disk('local')->path($file->getDiskPath()));

                $sizes = collect($sizes)->filter(function($size) use ($originalSize) {
                    return $size <= $originalSize[0];
                })->each(function($size) use ($file, $dir) {
                    $originalImageLocation = \Storage::disk('local')->path($file->getDiskPath());

                    $image = Image::make($originalImageLocation)
                        ->resize($size, null, function($c) {
                            $c->aspectRatio();
                        })
                    ;

                    $filename = str_slug(
                        $file->title ?: pathinfo($file->getFilename(), PATHINFO_FILENAME)
                    );

                    if (!\Storage::disk('local')->exists('uploads/public/'.$dir)) {
                        \Storage::disk('local')->makeDirectory('uploads/public/'.$dir);
                    }

                    $location = 'uploads/public/'.$dir.'/'.$filename.'-'.$image->width().'x'.$image->height().'.'.$file->getExtension();

                    $rootPath = \Storage::disk('local')->path($location);
                    $image->save($rootPath);
                });

                return count($sizes);
            });

            $file->addDynamicMethod('responsive', function() use ($file) {
                return \Cache::rememberForever($file->path, function() use ($file) {
                    $dir = $file->attachment->title;
                    $versions = app('filesversions')->byModelName($file->attachment_type);
                    $filename = str_slug(
                        $file->title ?: pathinfo($file->getFilename(), PATHINFO_FILENAME)
                    );

                    $location = 'uploads/public/'.$versions->dir.'/'.str_slug($file->attachment->title);

                    $sizes = collect(\Storage::files($location))
                        ->filter(function($filename) use ($file) {
                            $originalFilename = str_slug(pathinfo($file->getFilename(), PATHINFO_FILENAME));
                            return str_contains($filename, $file->title)
                                || str_contains($filename, $originalFilename);
                        })
                        ->map(function($fileVersion) use ($location) {
                            $sizes = getimagesize(\Storage::path($fileVersion));

                            return [\Storage::url('app/'.$fileVersion), $sizes[0]];
                        })
                    ;

                    if ($sizes->isEmpty()) {
                        return '<img src="'.$file->path.'">';
                    }

                    $srcset = 'srcset="';
                    $s = '';

                    foreach($sizes as $size) {
                        $srcset .= $size[0].' '.$size[1].'w, ';
                        $s .= '(max-width: '.$size[1].'px) 100vw, ';
                    }

                    $srcset = substr($srcset, 0, -2).'"';
                    $s = 'sizes="'.substr($s, 0, -2).', 1920px"';


                    return '<img '.$srcset.' '.$s.' src="'.$file->path.'" alt="'.$file->title.'">';
                });
            });
        });
    }

    /**
     * Registers any front-end components implemented in this plugin.
     *
     * @return array
     */
    public function registerComponents()
    {
        return []; // Remove this line to activate

        return [
            'Zoomyboy\Imageversions\Components\MyComponent' => 'myComponent',
        ];
    }

    /**
     * Registers any back-end permissions used by this plugin.
     *
     * @return array
     */
    public function registerPermissions()
    {
        return []; // Remove this line to activate

        return [
            'zoomyboy.imageversions.some_permission' => [
                'tab' => 'imageversions',
                'label' => 'Some permission'
            ],
        ];
    }

    /**
     * Registers back-end navigation items for this plugin.
     *
     * @return array
     */
    public function registerNavigation()
    {
        return []; // Remove this line to activate

        return [
            'imageversions' => [
                'label'       => 'imageversions',
                'url'         => Backend::url('zoomyboy/imageversions/mycontroller'),
                'icon'        => 'icon-leaf',
                'permissions' => ['zoomyboy.imageversions.*'],
                'order'       => 500,
            ],
        ];
    }
}
