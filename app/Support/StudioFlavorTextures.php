<?php

namespace App\Support;

/**
 * Texture files the owner dropped into public/img/studio/flavors/<slug>/ to replace the generated
 * surface of a Studio flavor (see scripts/build_studio3d_flavors.py for the layout they follow).
 * The builder hands this list to the 3D preview, so it only asks the server for files that exist.
 */
class StudioFlavorTextures
{
    public const MAPS = ['albedo.webp', 'surface.webp', 'frost.webp'];

    /**
     * @return array<string, list<string>>
     */
    public static function overrides(): array
    {
        $overrides = [];

        foreach (glob(public_path('img/studio/flavors/*'), GLOB_ONLYDIR) ?: [] as $directory) {
            $files = array_values(array_filter(self::MAPS, fn (string $file): bool => is_file($directory.DIRECTORY_SEPARATOR.$file)));

            if ($files !== []) {
                $overrides[basename($directory)] = $files;
            }
        }

        return $overrides;
    }
}
