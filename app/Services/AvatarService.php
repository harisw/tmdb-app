<?php

namespace App\Services;

use Composer\InstalledVersions;

class AvatarService
{
    private const EXCLUDED_STYLES = [
        'disco',
        'glass',
        'glyphs',
        'icons',
        'identicon',
        'initials',
        'initial-face',
        'rings',
        'shape-grid',
        'shapes',
        'stripes',
        'thumbs',
        'triangles',
    ];

    public static function getStyles(): array
    {
        $basePath = InstalledVersions::getInstallPath('dicebear/styles');
        $files = glob($basePath.'/src/*.json');

        $styles = [];
        foreach ($files as $file) {
            $name = basename($file, '.json');
            if (in_array($name, self::EXCLUDED_STYLES, true)) {
                continue;
            }
            $styles[] = $name;
        }

        return $styles;
    }

    public static function getRandomStyle(): string
    {
        $styles = self::getStyles();

        return $styles[array_rand($styles)];
    }
}
