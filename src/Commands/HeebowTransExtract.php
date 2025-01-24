<?php

namespace Wahebtalal\HeebowTrans\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

class HeebowTransExtract extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trans:extract
                            {path : The path to the directory or file to scan}
                            {--locale= : The locale for the translation file}
                            {--dry-run : Perform a dry run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Extract all values between make(\'here\') and save them into a JSON translation file.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $path = $this->argument('path');
        $locale = $this->option('locale') ?? config('heebowtrans.default_locale');
        $dryRun = $this->option('dry-run');

        if (! file_exists($path)) {
            $this->error("Path not found: {$path}");

            return Command::FAILURE;
        }

        // Get all PHP files in the path
        $files = is_dir($path) ? $this->getPhpFiles($path) : [$path];

        if (empty($files)) {
            $this->info("No PHP files found in the directory: {$path}");

            return Command::SUCCESS;
        }

        $extractedValues = [];

        foreach ($files as $file) {
            // Skip excluded files or directories
            if ($this->isExcluded($file)) {
                $this->info("Skipping excluded file: {$file}");

                continue;
            }

            $this->info("Processing file: {$file}");
            $content = File::get($file);

            // Extract values between make('...')
            $values = $this->extractMakeValues($content);

            if (! empty($values)) {
                $extractedValues = array_merge($extractedValues, $values);
                $this->info('Extracted values: ' . implode(', ', $values));
            }
        }

        // Save extracted values to JSON translation file
        if (! empty($extractedValues)) {
            $this->saveToJsonFile($extractedValues, $locale, $dryRun);
        } else {
            $this->info('No values extracted.');
        }

        $this->info('Extraction complete!');

        return Command::SUCCESS;
    }

    /**
     * Get all PHP files in a directory recursively.
     */
    private function getPhpFiles(string $directory): array
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        $regex = new RegexIterator($iterator, '/^.+\.php$/i', RegexIterator::GET_MATCH);

        $files = [];
        foreach ($regex as $file) {
            $files[] = $file[0];
        }

        return $files;
    }

    /**
     * Check if a file or directory is excluded.
     */
    private function isExcluded(string $path): bool
    {
        $excludedPaths = config('heebowtrans.exclude', []);

        foreach ($excludedPaths as $excludedPath) {
            if (strpos($path, $excludedPath) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract values between make('...').
     */
    private function extractMakeValues(string $content): array
    {
        $values = [];

        // Use regex patterns from the configuration
        $patterns = ["/(.+?)::make\\(['\"](.+?)['\"]\\)/"];
        //        $patterns = ['/(?!\s*\/)(?!\s*#)(?!\s*\*).*?(.+?)::make\([\'\"](.+?)[\'\"]\)/'];
        $include = config('heebow-trans-extract.include', []);
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $content, $matches);
            foreach ($matches[2] as $key => $value) {

                if ($this->isClassOrParentIncluded($this->resolveClassName($matches[1][$key], $content), $include)) {
                    $values[] = (string) str($value)
                        ->afterLast('.')
                        ->kebab()
                        ->replace(['-', '_'], ' ')
                        ->ucfirst();

                }
            }
        }

        return $values;
    }

    /**
     * Check if a class or any of its parents or interfaces are in the include list.
     *
     * @param  string|null  $className  The name of the class to check.
     * @param  array  $include  The array of included classes, parents, or interfaces.
     * @return bool True if the class or any of its parents/interfaces are in the include list, false otherwise.
     */
    public function isClassOrParentIncluded(?string $className, array $include): bool
    {
        if (empty($className) || str_contains($className, '//')) {
            return false;
        }
        // Get parent classes and interfaces of the class
        $parentClasses = class_parents($className) ?: [];
        $interfaces = class_implements($className) ?: [];

        // Check if the class, any of its parents, or interfaces exist in the include array
        return in_array($className, $include) ||
            array_intersect($parentClasses, $include) ||
            array_intersect($interfaces, $include);
    }

    private function resolveClassName(string $className, string $content): ?string
    {
        // Trim and normalize the class name
        $className = trim($className);
        if (empty($className)) {
            return null;
        }

        // Escape the class name for use in a regex pattern
        $escapedClassName = preg_quote($className, '/');

        // Try to match 'use' statements for the full class name
        if (preg_match("/use\s+(.*?\\\\{$escapedClassName});/", $content, $match)) {
            return $match[1];
        }

        // Check if the class is in the same namespace
        if (preg_match("/namespace\s+(.*?);/", $content, $namespaceMatch)) {
            $namespace = $namespaceMatch[1];
            $fullyQualifiedName = "{$namespace}\\{$className}";

            // Verify if the class actually exists
            if (class_exists($fullyQualifiedName)) {
                return $fullyQualifiedName;
            }
        }

        // Return null if the class cannot be resolved
        return null;
    }

    /**
     * Save extracted values to a JSON translation file.
     */
    private function saveToJsonFile(array $values, string $locale, bool $dryRun): void
    {
        $translationFilePath = str_replace(
            '{locale}',
            $locale,
            config('heebowtrans.translation_file_path')
        );

        // Load existing translations if the file exists
        $existingTranslations = [];
        if (File::exists($translationFilePath)) {
            $existingTranslations = json_decode(File::get($translationFilePath), true) ?? [];
        }

        // Preserve existing translations and add new keys
        $mergedTranslations = $existingTranslations;

        foreach ($values as $key) {
            if (! isset($mergedTranslations[$key])) {
                $mergedTranslations[$key] = ''; // Add new keys with empty values
            }
        }

        // Sort translations alphabetically
        ksort($mergedTranslations);

        if (! $dryRun) {
            // Save the updated translations to the JSON file
            File::put($translationFilePath, json_encode($mergedTranslations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info("Translation file updated successfully: {$translationFilePath}");
        } else {
            $this->info('Dry run: Translation file would be updated with the following keys:');
            foreach ($mergedTranslations as $key => $value) {
                $this->line(" - {$key}: {$value}");
            }
        }
    }
}
