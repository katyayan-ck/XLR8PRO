<?php

declare(strict_types=1);

namespace App\Support\Entity;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The only write path for an entity (DEC-050). Every create/edit — CRUD screen, import, API,
 * job, seeder — calls create(), update() or upsert(), which run the same steps:
 *
 *   normalise (field transforms) → validate (field rules; ValidationException) →
 *   business guards (beforeCreate/beforeUpdate) → persist through Eloquent, in a transaction
 *
 * Subclasses declare fields() once; nothing else may define rules for those fields.
 *
 * @template TModel of Model
 */
abstract class EntityService
{
    /** @return class-string<TModel> */
    abstract protected function model(): string;

    /** @return list<Field> */
    abstract public function fields(): array;

    /** Key fields used by upsert() when an import does not say otherwise. */
    protected function naturalKey(): array
    {
        return ['code'];
    }

    /**
     * Create a record.
     *
     * @param  array<string, mixed>  $input
     * @return TModel
     *
     * @throws ValidationException
     */
    public function create(array $input): Model
    {
        $data = $this->validate($input);
        foreach ($this->fieldMap() as $name => $field) {
            if (! array_key_exists($name, $data) && $field->default !== null) {
                $data[$name] = $field->default;
            }
        }
        $this->beforeCreate($data);

        return DB::transaction(fn () => $this->model()::create($data));
    }

    /**
     * Update a record. Immutable fields in the input are ignored; fields absent from the input
     * keep their stored value.
     *
     * @param  TModel  $model
     * @param  array<string, mixed>  $input
     * @return TModel
     *
     * @throws ValidationException
     */
    public function update(Model $model, array $input): Model
    {
        foreach ($this->fieldMap() as $name => $field) {
            if ($field->immutable) {
                unset($input[$name]);
            }
        }
        $data = $this->validate($input, $model);
        $this->beforeUpdate($model, $data);

        DB::transaction(fn () => $model->update($data));

        return $model->refresh();
    }

    /**
     * Create or update by natural key (imports).
     *
     * @param  array<string, mixed>  $input
     * @param  list<string>|null  $key
     * @return TModel
     *
     * @throws ValidationException
     */
    public function upsert(array $input, ?array $key = null): Model
    {
        $normalised = $this->normalise($input);
        $match = [];
        foreach ($key ?? $this->naturalKey() as $field) {
            $match[$field] = $normalised[$field] ?? null;
        }

        $existing = $this->model()::query()->where($match)->first();

        return $existing ? $this->update($existing, $input) : $this->create($input);
    }

    /**
     * Normalise then validate. Returns only defined fields, transformed.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(array $input, ?Model $current = null): array
    {
        $data = $this->normalise($input);

        // On update, required fields not being changed are validated against the stored value.
        $subject = $current ? array_merge($this->currentValues($current), $data) : $data;

        Validator::make($subject, $this->rules($subject, $current), [], $this->labels())->validate();

        return $data;
    }

    /**
     * Apply each field's transformation. Unknown keys are dropped; blank strings become null.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function normalise(array $input): array
    {
        $transformer = new ValueTransformer;
        $out = [];

        foreach ($this->fieldMap() as $name => $field) {
            if (! array_key_exists($name, $input)) {
                continue;
            }
            $value = $input[$name];

            if ($field->boolean) {
                $out[$name] = $value === null || $value === '' ? $field->default : (bool) filter_var($value, FILTER_VALIDATE_BOOL);

                continue;
            }
            if (is_string($value) || is_numeric($value)) {
                $value = trim((string) $value);
                if ($value === '') {
                    $out[$name] = null;

                    continue;
                }
                if ($field->transforms !== []) {
                    $value = $transformer->run($value, $field->transforms);
                }
            }
            $out[$name] = $value;
        }

        return $out;
    }

    /**
     * Validation rules for the given data.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, list<mixed>>
     */
    public function rules(array $data, ?Model $current = null): array
    {
        $instance = new ($this->model());
        $rules = [];

        foreach ($this->fieldMap() as $name => $field) {
            $fieldRules = [$field->required ? 'required' : 'nullable', ...$field->rules];

            if ($field->unique !== null) {
                $unique = Rule::unique($field->unique['table'] ?? $instance->getTable(), $field->unique['column'] ?? $name);
                if (in_array(SoftDeletes::class, class_uses_recursive($instance), true)) {
                    $unique->whereNull('deleted_at');
                }
                foreach ($field->unique['scope'] as $scopeField) {
                    $scopeValue = $data[$scopeField] ?? null;
                    $scopeValue === null ? $unique->whereNull($scopeField) : $unique->where($scopeField, $scopeValue);
                }
                if ($current) {
                    $unique->ignore($current->getKey());
                }
                $fieldRules[] = $unique;
            }

            $rules[$name] = $fieldRules;
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function labels(): array
    {
        return array_map(fn (Field $f) => $f->label, $this->fieldMap());
    }

    /**
     * Transformation pipelines per field — the model backstop (HasColumnTransformations) reads
     * these, so there is no second copy on the model.
     *
     * @return array<string, list<string>>
     */
    public function transformations(): array
    {
        return array_filter(array_map(fn (Field $f) => $f->transforms, $this->fieldMap()));
    }

    /** Field reference for documentation (format, rules, flags). @return list<array<string, mixed>> */
    public function describe(): array
    {
        return array_values(array_map(fn (Field $f) => [
            'field' => $f->name,
            'label' => $f->label,
            'format' => $f->format,
            'required' => $f->required,
            'immutable' => $f->immutable,
            'unique' => $f->unique ? ($f->unique['scope'] ? 'per '.implode(', ', $f->unique['scope']) : 'yes') : 'no',
            'transforms' => implode(' → ', $f->transforms),
        ], $this->fieldMap()));
    }

    /**
     * Business rules beyond single-field validation (throw via fail()).
     *
     * @param  array<string, mixed>  $data
     */
    protected function beforeCreate(array &$data): void {}

    /**
     * @param  TModel  $model
     * @param  array<string, mixed>  $data
     */
    protected function beforeUpdate(Model $model, array &$data): void {}

    /** @throws ValidationException */
    protected function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    /** @return array<string, Field> */
    protected function fieldMap(): array
    {
        $map = [];
        foreach ($this->fields() as $field) {
            $map[$field->name] = $field;
        }

        return $map;
    }

    /** @return array<string, mixed> */
    private function currentValues(Model $current): array
    {
        return array_intersect_key($current->getAttributes(), $this->fieldMap());
    }
}
