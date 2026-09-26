<?php

declare(strict_types=1);

namespace App\Support\Entity;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\In;

/**
 * One field of an entity: the single definition of its format, transformation, validation,
 * label and immutability (DEC-050). Entity services list their fields; forms, imports and APIs
 * never re-declare any of this.
 *
 *   Field::code('code', 30)->label('Model Code')->required()->unique()->immutable()
 */
final class Field
{
    /** @var list<string> transformation pipeline (HasColumnTransformations names) */
    public array $transforms = [];

    /** @var list<string|ValidationRule|Exists|In> */
    public array $rules = [];

    public string $label;

    public string $format = '';

    public bool $required = false;

    public bool $immutable = false;

    public bool $boolean = false;

    public mixed $default = null;

    /** @var array{table: ?string, column: ?string, scope: list<string>}|null */
    public ?array $unique = null;

    private function __construct(public readonly string $name)
    {
        $this->label = ucwords(str_replace('_', ' ', $name));
    }

    public static function make(string $name): self
    {
        return new self($name);
    }

    /** Business code: upper-case, spaces → hyphen (THAR-ROXX), A-Z 0-9 - _ only (DEC-049). */
    public static function code(string $name, int $max): self
    {
        return self::make($name)
            ->format("Code: A-Z 0-9 - _, spaces become hyphens, max {$max}")
            ->transform('trim', 'uppercase_alphanumeric_dash_underscore')
            ->rules('string', "max:{$max}", 'regex:/^[A-Z0-9][A-Z0-9_\-]*$/');
    }

    /** Human name: tags stripped, spaces collapsed, Title Case keeping business acronyms. */
    public static function name(string $name, int $max = 255): self
    {
        return self::make($name)
            ->format("Name: Title Case, max {$max}")
            ->transform('strip_tags', 'trim_spaces', 'title_case')
            ->rules('string', "max:{$max}");
    }

    /** Free text kept as typed (trimmed). */
    public static function text(string $name, int $max = 255): self
    {
        return self::make($name)->format("Text, max {$max}")->transform('trim')->rules('string', "max:{$max}");
    }

    /** Yes/No flag: accepts 1/0, true/false, yes/no, on/off. */
    public static function flag(string $name, bool $default = true): self
    {
        $field = self::make($name)->format('Yes/No')->rules('boolean')->default($default);
        $field->boolean = true;

        return $field;
    }

    public static function integer(string $name, int $min = 0): self
    {
        return self::make($name)->format("Whole number ≥ {$min}")->rules('integer', "min:{$min}");
    }

    /** Reference to another entity by its code (exists, not soft-deleted). */
    public static function reference(string $name, string $table, int $max, string $column = 'code'): self
    {
        return self::code($name, $max)->rules(Rule::exists($table, $column)->whereNull('deleted_at'));
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function format(string $format): self
    {
        $this->format = $format;

        return $this;
    }

    public function transform(string ...$steps): self
    {
        $this->transforms = array_values(array_merge($this->transforms, $steps));

        return $this;
    }

    public function rules(mixed ...$rules): self
    {
        $this->rules = array_values(array_merge($this->rules, $rules));

        return $this;
    }

    public function required(): self
    {
        $this->required = true;

        return $this;
    }

    /** Set on create only; ignored on update (codes are keys other records reference). */
    public function immutable(): self
    {
        $this->immutable = true;

        return $this;
    }

    public function default(mixed $value): self
    {
        $this->default = $value;

        return $this;
    }

    /**
     * Unique in the entity table (live rows), optionally within the values of other fields
     * (e.g. variant `code` unique per `color_code`).
     *
     * @param  list<string>  $scope
     */
    public function unique(array $scope = [], ?string $table = null, ?string $column = null): self
    {
        $this->unique = ['table' => $table, 'column' => $column, 'scope' => $scope];

        return $this;
    }
}
