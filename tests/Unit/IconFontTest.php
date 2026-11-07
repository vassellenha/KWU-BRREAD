<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class IconFontTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function configuredIcons(): array
    {
        return (require __DIR__.'/../../config/icons.php')['names'];
    }

    /**
     * @return list<string>
     */
    private function iconsUsedInViews(): array
    {
        $viewsPath = __DIR__.'/../../resources/views';
        $icons = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsPath, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            foreach (file($file->getPathname()) as $line) {
                if (preg_match_all("/'icon' => '([a-z0-9_]+)'/", $line, $matches)) {
                    array_push($icons, ...$matches[1]);
                }

                if (! str_contains($line, 'material-symbols-outlined')) {
                    continue;
                }

                $afterClass = substr($line, strpos($line, 'material-symbols-outlined'));
                $beforeClose = substr($afterClass, strpos($afterClass, '"') + 1);

                if (preg_match_all("/(?<!\[)'([a-z][a-z0-9_]+)'/", $beforeClose, $matches)) {
                    array_push($icons, ...$matches[1]);
                }

                if (preg_match('/>([a-z][a-z0-9_]+)</', $beforeClose, $match)) {
                    $icons[] = $match[1];
                }
            }
        }

        return array_values(array_unique($icons));
    }

    public function test_every_icon_used_in_a_view_is_served_by_the_icon_font(): void
    {
        $this->assertSame([], array_values(array_diff($this->iconsUsedInViews(), $this->configuredIcons())));
    }

    public function test_the_icon_list_is_sorted_as_google_fonts_requires(): void
    {
        $sorted = $this->configuredIcons();
        sort($sorted);

        $this->assertSame($sorted, $this->configuredIcons());
    }
}
