<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class App extends BaseConfig
{
    /**
     * Default Locale
     *
     * @var string
     */
    public string $defaultLocale = 'es';

    /**
     * Negotiate Locale
     *
     * @var bool
     */
    public bool $negotiateLocale = false;

    /**
     * Supported Locales
     *
     * @var string[]
     */
    public array $supportedLocales = ['es', 'en'];

    // ... rest of the default App config
}
