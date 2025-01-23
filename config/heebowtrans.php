<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Locale
    |--------------------------------------------------------------------------
    |
    | The default locale for the translation file.
    |
    */
    'default_locale' => 'ar',

    /*
    |--------------------------------------------------------------------------
    | Translation File Path
    |--------------------------------------------------------------------------
    |
    | The path where the JSON translation file will be saved.
    | The `{locale}` placeholder will be replaced with the locale.
    |
    */
    'translation_file_path' => resource_path('lang/{locale}.json'),

    'include' => [
        \Filament\Forms\Components\Component::class,
        \Filament\Tables\Columns\Column::class,
        \Filament\Infolists\Components\Entry::class,
    ],



];
