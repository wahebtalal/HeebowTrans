<?php

namespace Wahebtalal\HeebowTrans;

use Filament\Support\Assets\Asset;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Wahebtalal\HeebowTrans\Commands\HeebowTransExtract;

class HeebowTransServiceProvider extends PackageServiceProvider
{
    public static string $name = 'heebowtrans';


    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package->name(static::$name)
            ->hasCommands($this->getCommands())
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->askToStarRepoOnGitHub('wahebtalal/heebowtrans');
            });

        $configFileName = $package->shortName();

        if (file_exists($package->basePath("/../config/{$configFileName}.php"))) {
            $package->hasConfigFile();
        }


    }

    public function packageRegistered(): void {}

    public function packageBooted(): void
    {



        foreach (config('heebowtrans.include') as $class) {
            $class::configureUsing(function ($component) {
                $component->translateLabel();
            });
        }
    }

    protected function getAssetPackageName(): ?string
    {
        return 'wahebtalal/heebowtrans';
    }

    /**
     * @return array<Asset>
     */
    protected function getAssets(): array
    {
        return [
        ];
    }

    /**
     * @return array<class-string>
     */
    protected function getCommands(): array
    {
        return [
            HeebowTransExtract::class,
        ];
    }

}
