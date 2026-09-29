<?php

namespace Tests\Unit\Lang;

use Tests\TestCase;

/**
 * Admin flash messages live in resources/lang/en/{module}.php 'flash' groups (to-do W6). A mistyped key would show the
 * raw key to the user, so every key a controller uses must exist, and every :placeholder in its text must be passed.
 */
class FlashMessagesLangTest extends TestCase
{
    public function test_every_flash_key_used_by_a_controller_exists_with_its_placeholders(): void
    {
        $problems = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path('Http/Controllers')));
        foreach ($files as $file) {
            if (! str_ends_with($file->getFilename(), '.php')) {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            preg_match_all("/'([a-z]+\\.flash\\.[a-z0-9_]+)'(?:, \\[(.*?)\\])?/", $source, $calls, PREG_SET_ORDER);
            foreach ($calls as $call) {
                $key = $call[1];
                if (! trans()->has($key)) {
                    $problems[] = "{$file->getFilename()}: {$key} missing";

                    continue;
                }
                preg_match_all('/:([a-z_0-9]+)/', (string) trans($key), $needed);
                preg_match_all("/'([a-z_0-9]+)' =>/", $call[2] ?? '', $given);
                if (($call[2] ?? '') !== '' && array_diff($needed[1], $given[1]) !== []) {
                    $problems[] = "{$file->getFilename()}: {$key} needs :".implode(', :', array_diff($needed[1], $given[1]));
                }
            }
        }

        $this->assertSame([], $problems);
    }

    public function test_a_flash_message_keeps_its_wording_with_values_filled_in(): void
    {
        $this->assertSame('Pricelist [PV] has been put on Hold.', __('pricing.flash.pricelist_put_on_hold', ['scope' => 'PV']));
        $this->assertSame('Your profile photo was updated.', __('iam.flash.profile_photo_updated'));
    }
}
