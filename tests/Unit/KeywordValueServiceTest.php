<?php

namespace Tests\Unit;

use App\Services\KeywordValueService;
use App\Models\Utilities\KeyValue\Keyvalue;
use App\Models\Utilities\KeyValue\KeywordMaster;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class KeywordValueServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_get_code_returns_code()
    {
        KeywordMaster::create(['code' => 'VTRANS', 'keyword' => 'Vehicle Transmission']);
        Keyvalue::create([
            'keyword_code' => 'VTRANS',
            'code'         => 'AUTO',
            'value'        => 'Automatic'
        ]);

        $this->assertEquals('AUTO', KeywordValueService::getCode('VTRANS', 'AUTO'));
    }

    public function test_get_enum_returns_array()
    {
        $result = KeywordValueService::getEnum('VTRANS');
        $this->assertIsArray($result);
    }

    public function test_uppercase_consistency()
    {
        // Self-contained test data
        KeywordMaster::create(['code' => 'VTRANS', 'keyword' => 'Vehicle Transmission']);
        Keyvalue::create([
            'keyword_code' => 'VTRANS',
            'code'         => 'AUTO',
            'value'        => 'Automatic'
        ]);

        $this->assertEquals('AUTO', KeywordValueService::getCode('vtrans', 'auto'));
    }
}