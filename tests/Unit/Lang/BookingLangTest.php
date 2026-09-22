<?php

namespace Tests\Unit\Lang;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class BookingLangTest extends TestCase
{
    public function test_booking_fields_lang_file_returns_the_expected_array_shape(): void
    {
        $fields = trans('booking.fields');

        $this->assertIsArray($fields);
        $this->assertArrayHasKey('mobile', $fields);
        $this->assertArrayHasKey('booking_amount', $fields);
        $this->assertSame('Mobile Number', $fields['mobile']);
    }

    public function test_validator_uses_the_centralized_label_for_a_required_field_message(): void
    {
        $validator = Validator::make(
            [],
            ['mobile' => 'required|string|max:15'],
            [],
            ['mobile' => __('booking.fields.mobile')]
        );

        $this->assertTrue($validator->fails());
        $this->assertSame(
            'The Mobile Number field is required.',
            $validator->errors()->first('mobile')
        );
    }

    /**
     * Every input name update()'s $customAttributes maps must have a
     * matching key in the fields registry - guards against a typo'd
     * lang key silently falling back to the raw input name.
     */
    public function test_every_field_referenced_by_updates_custom_attributes_exists_in_the_registry(): void
    {
        $controllerSource = file_get_contents(
            app_path('Http/Controllers/Admin/Sales/Booking/BookingCrudController.php')
        );

        preg_match_all("/__\('booking\.fields\.([a-z0-9_]+)'\)/", $controllerSource, $matches);

        $this->assertNotEmpty($matches[1], 'Expected to find booking.fields.* references in the controller.');

        $fields = trans('booking.fields');

        foreach (array_unique($matches[1]) as $key) {
            $this->assertArrayHasKey($key, $fields, "Missing booking.fields.{$key} in resources/lang/en/booking.php");
        }
    }
}
