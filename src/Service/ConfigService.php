<?php

declare(strict_types=1);

namespace App\Service;

class ConfigService
{
    private string $projectDir;

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
    }

    /**
     * @return array<string, mixed>
     */
    public function getEmailConfig(string $emailType): array
    {
        $configFile = $this->projectDir . '/private/config.json';

        if (!file_exists($configFile)) {
            return [];
        }

        $contents = file_get_contents($configFile);
        $config = json_decode($contents, true);

        foreach ($config['emails'] as $emailConfig) {
            if ($emailConfig['type'] === $emailType) {
                return $emailConfig;
            }
        }

        return [];
    }
}
