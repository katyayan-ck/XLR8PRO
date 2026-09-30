<?php

namespace Tests\Feature\Utils;

use App\Support\ErrorRef;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

/**
 * Admin screens show `ErrorRef::userMessage($e)` for caught exceptions (to-do W6 remainder): business messages pass
 * through, technical failures become a generic text with the request's reference id.
 */
class ErrorRefUserMessageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ErrorRef::reset();
    }

    public function test_a_business_message_is_shown_as_thrown(): void
    {
        $this->assertSame('Receipt amount exceeds the balance.', ErrorRef::userMessage(new RuntimeException('Receipt amount exceeds the balance.')));
    }

    public function test_a_sql_failure_is_replaced_by_the_reference_text(): void
    {
        $e = new QueryException('mysql', 'insert into people (mobile) values (?)', ['9876543210'], new \PDOException('Duplicate entry 9876543210'));

        $message = ErrorRef::userMessage($e);

        $this->assertStringContainsString(ErrorRef::get(), $message);
        $this->assertStringNotContainsString('9876543210', $message);
        $this->assertStringNotContainsString('insert into', $message);
    }

    public function test_a_php_error_is_replaced_by_the_reference_text(): void
    {
        $this->assertStringContainsString(ErrorRef::get(), ErrorRef::userMessage(new \TypeError('Argument #1 must be of type string')));
    }

    public function test_a_validation_exception_shows_its_first_field_message(): void
    {
        $e = ValidationException::withMessages(['amount' => 'The amount must be at least 1.']);

        $this->assertSame('The amount must be at least 1.', ErrorRef::userMessage($e));
    }
}
