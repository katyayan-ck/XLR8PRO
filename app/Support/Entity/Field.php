<?php

declare(strict_types=1);

namespace App\Support\Entity;

use App\Services\IdentifierService;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\In;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * One field of an entity: the single definition of its format, transformation, validation,
 * label and immutability (DEC-050). Entity services list their fields; forms, imports and APIs
 * never re-declare any of this.
 *
 *   Field::code('code', 30)->label('Model Code')->required()->unique()->immutable()
 */
final class Field
{
    /** @var list<string|Closure> transformation pipeline (HasColumnTransformations names or callables) */
    public array $transforms = [];

    /** @var list<string|ValidationRule|Exists|In> */
    public array $rules = [];

    public string $label;

    public string $format = '';

    public bool $required = false;

    public bool $immutable = false;

    public bool $boolean = false;

    /** Validated but not a column (uploads, remove flags): excluded from persisted data. */
    public bool $virtual = false;

    public bool $raw = false;

    public bool $json = false;

    /** @var list<mixed> rules applied to each item of an array input ("name.*") */
    public array $eachRules = [];

    public mixed $default = null;

    /** @var array{table: ?string, column: ?string, scope: list<string>, trashed: bool}|null */
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

    /** Phone: +91 / leading 0 removed (IdentifierService::cleanMobile), 10 digits. */
    public static function phone(string $name = 'phone'): self
    {
        return self::make($name)
            ->format('10-digit phone; +91 or leading 0 is removed')
            ->transform(fn (string $v) => app(IdentifierService::class)->cleanMobile($v) ?? $v)
            ->rules('digits:10');
    }

    public static function email(string $name = 'email'): self
    {
        return self::make($name)->format('E-mail, lower-case')->transform('trim', 'lowercase')->rules('email', 'max:255');
    }

    public static function pincode(string $name = 'pincode'): self
    {
        return self::make($name)->format('6-digit PIN code')->transform('trim')->rules('digits:6');
    }

    public static function coordinate(string $name, int $limit): self
    {
        return self::make($name)->format("Decimal degrees, ±{$limit}")->rules('numeric', "between:-{$limit},{$limit}");
    }

    /**
     * One of a fixed list (DB enum): matched case-insensitively and stored in its canonical spelling.
     *
     * @param  list<string>  $allowed
     */
    public static function choice(string $name, array $allowed): self
    {
        return self::make($name)
            ->format('One of: '.implode(', ', $allowed))
            ->transform(function (string $value) use ($allowed): string {
                foreach ($allowed as $option) {
                    if (strcasecmp($option, $value) === 0) {
                        return $option;
                    }
                }

                return $value;
            })
            ->rules(Rule::in($allowed));
    }

    /** Calendar date stored as Y-m-d; accepts any parseable date or an Excel serial number. */
    public static function date(string $name): self
    {
        return self::make($name)
            ->format('Date (stored YYYY-MM-DD)')
            ->transform(function (string $value): string {
                try {
                    if (is_numeric($value) && (float) $value > 1000 && (float) $value < 100000) {
                        return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->format('Y-m-d');
                    }

                    return Carbon::parse($value)->format('Y-m-d');
                } catch (Throwable) {
                    return $value;
                }
            })
            ->rules('date');
    }

    /** Single image upload (not a column; the service stores it via media library). */
    public static function image(string $name): self
    {
        return self::make($name)->format('Image jpg/png/webp, max 2 MB')->rules('image', 'mimes:jpg,jpeg,png,webp', 'max:2048')->virtual();
    }

    /** Multiple document uploads (not a column). */
    public static function documents(string $name = 'documents'): self
    {
        return self::make($name)->format('Files, max 10 MB each')->rules('array')->each('file', 'max:10240')->virtual();
    }

    /** JSON object/array: an array as is, or JSON text (a form textarea) decoded to one. */
    public static function json(string $name): self
    {
        $field = self::make($name)->format('JSON object or list')->rules('array');
        $field->json = true;

        return $field;
    }

    /** Stored exactly as given — no trimming or transforms (passwords). */
    public function raw(): self
    {
        $this->raw = true;

        return $this;
    }

    public function virtual(): self
    {
        $this->virtual = true;

        return $this;
    }

    public function each(mixed ...$rules): self
    {
        $this->eachRules = array_values(array_merge($this->eachRules, $rules));

        return $this;
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

    public function transform(string|Closure ...$steps): self
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
     * $includeTrashed: the table's unique index also covers soft-deleted rows, so they count too.
     *
     * @param  list<string>  $scope
     */
    public function unique(array $scope = [], ?string $table = null, ?string $column = null, bool $includeTrashed = false): self
    {
        $this->unique = ['table' => $table, 'column' => $column, 'scope' => $scope, 'trashed' => $includeTrashed];

        return $this;
    }
}
