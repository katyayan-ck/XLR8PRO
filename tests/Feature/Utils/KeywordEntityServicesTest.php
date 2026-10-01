<?php

namespace Tests\Feature\Utils;

use App\Models\User;
use App\Models\Utilities\KeyValue\Keyvalue;
use App\Services\Utils\KeyvalueService;
use App\Services\Utils\KeywordMasterService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * DEC-055: keyword masters and values are written only through their entity services (the
 * Key Value / Keyword screens, the vehicle import and the enquiry import call these).
 */
class KeywordEntityServicesTest extends TestCase
{
    use DatabaseTransactions;

    private function keyword(): string
    {
        $code = 'TST'.strtoupper(substr(uniqid(), -6));
        app(KeywordMasterService::class)->create(['code' => $code, 'keyword' => 'test keyword '.$code]);

        return $code;
    }

    public function test_values_are_normalised_and_codes_are_unique_within_their_keyword(): void
    {
        $keyword = $this->keyword();
        $values = app(KeyvalueService::class);

        $value = $values->create(['keyword_code' => strtolower($keyword), 'code' => 'cng + petrol', 'key' => 'fuel', 'value' => '  CNG   + Petrol ']);
        $this->assertSame([$keyword, 'CNG-PLUS-PETROL', 'FUEL', 'CNG + Petrol', 1, true], [$value->keyword_code, $value->code, $value->key, $value->value, (int) $value->status, $value->is_active]);

        // The same code under another keyword is fine…
        $other = $values->create(['keyword_code' => $this->keyword(), 'code' => 'CNG-PLUS-PETROL', 'value' => 'x']);
        $this->assertSame('CNG-PLUS-PETROL', $other->code);

        // …but not twice in one keyword.
        $this->expectException(ValidationException::class);
        $values->create(['keyword_code' => $keyword, 'code' => 'CNG PLUS PETROL', 'value' => 'y']);
    }

    public function test_a_value_needs_an_existing_keyword(): void
    {
        $this->expectException(ValidationException::class);
        app(KeyvalueService::class)->create(['keyword_code' => 'NO_SUCH_KEYWORD_X', 'code' => 'A', 'value' => 'A']);
    }

    public function test_editing_a_legacy_value_never_rewrites_its_code(): void
    {
        $keyword = $this->keyword();
        // a legacy row, written past the entity rules
        $id = Keyvalue::query()->toBase()->insertGetId(['keyword_code' => $keyword, 'code' => 'OLD BIN A1', 'value' => 'Old', 'status' => 1, 'is_active' => 1]);

        app(KeyvalueService::class)->update(Keyvalue::find($id), ['value' => 'Renamed Bin']);
        $row = Keyvalue::find($id);
        $row->details = 'saved through the model directly';
        $row->save();

        $this->assertSame(['OLD BIN A1', 'Renamed Bin'], [Keyvalue::find($id)->code, Keyvalue::find($id)->value]);
    }

    public function test_parents_are_appended_once(): void
    {
        $values = app(KeyvalueService::class);
        $value = $values->create(['keyword_code' => $this->keyword(), 'code' => 'CHILD', 'value' => 'Child', 'parent_id' => ' 5 ']);

        $value = $values->addParent($value, 9);
        $value = $values->addParent($value, '5');

        $this->assertSame('5,9', $value->parent_id);
    }

    public function test_extra_data_accepts_json_text_and_rejects_invalid_json(): void
    {
        $values = app(KeyvalueService::class);
        $keyword = $this->keyword();

        $value = $values->create(['keyword_code' => $keyword, 'code' => 'J1', 'value' => 'J', 'extra_data' => '{"lang":"hi"}']);
        $this->assertSame(['lang' => 'hi'], $value->extra_data);

        $this->expectException(ValidationException::class);
        $values->create(['keyword_code' => $keyword, 'code' => 'J2', 'value' => 'J', 'extra_data' => '{not json']);
    }

    public function test_first_or_create_matches_the_normalised_value_and_never_overwrites(): void
    {
        $keyword = $this->keyword();
        $values = app(KeyvalueService::class);
        $created = $values->firstOrCreate(['keyword_code' => $keyword, 'code' => 'body type'], ['value' => 'Body Type']);

        $found = $values->firstOrCreate(['keyword_code' => strtolower($keyword), 'code' => 'BODY-TYPE'], ['value' => 'Changed']);

        $this->assertSame($created->id, $found->id);
        $this->assertSame('Body Type', $found->value);
    }

    public function test_the_key_value_screen_writes_through_the_service(): void
    {
        $keyword = $this->keyword();
        Permission::firstOrCreate(['name' => 'UTL_SETTINGS_MANAGE', 'guard_name' => 'web']);
        $user = User::create(['username' => 'kv_'.uniqid(), 'password' => bcrypt('password'), 'user_type' => 'Emp', 'is_active' => 1]);
        $user->givePermissionTo('UTL_SETTINGS_MANAGE');
        $this->app['auth']->guard('backpack')->setUser($user);

        $this->post(backpack_url('utils/key-value'), [
            'keyword_code' => $keyword, 'code' => 'screen value', 'value' => 'Screen Value', 'level' => 0, 'status' => 1,
        ])->assertRedirect();

        $this->assertTrue(Keyvalue::where('keyword_code', $keyword)->where('code', 'SCREEN-VALUE')->exists());
    }
}
