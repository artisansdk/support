<?php

namespace ArtisanSdk\Support;

use ArrayAccess;
use ArtisanSdk\Contract\FieldMapping;
use ArtisanSdk\Support\Concerns\FieldMap;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SparseFields implements FieldMapping
{
    use FieldMap;

    /**
     * Fields for sparse set.
     *
     * @example ['field1', 'field2']
     *
     * @var string[]
     */
    protected $fields = ['*'];

    /**
     * Fields for eager loaded relations.
     *
     * @example ['relation1' => ['*'], 'relation2' => ['field1', 'field2']]
     *
     * @var array
     */
    protected $relations = [];

    /**
     * Fields to columns mappings.
     *
     * @var array
     */
    protected $mappings = [];

    /**
     * Setup a sparse field set.
     *
     * @param string[] $fields    for sparse set
     * @param array    $relations for eager loading with sparse fields
     * @param array    $mappings  of fields to columns
     */
    public function __construct(array $fields = null, array $relations = null, array $mappings = null)
    {
        $this->fields = empty($fields) ? ['*'] : $fields;
        $this->relations = $this->parse($relations ?? []);
        $this->mappings = $mappings ?? [];
    }

    /**
     * Make a new isntance of sparse fields from the request.
     *
     * @param \ArrayAccess                            $request
     * @param array|\ArtisanSdk\Contract\FieldMapping $mappings
     *
     * @return \ArtisanSdk\Support\SparseFields
     */
    public static function make(ArrayAccess $request, $mappings): SparseFields
    {
        return new static(
            (array) $request->offsetGet('fields'),
            (array) $request->offsetGet('relations'),
            $mappings instanceof FieldMapping
                ? $mappings->getFieldMap()
                : (array) $mappings
        );
    }

    /**
     * Get the column mappings for the sparse fields.
     *
     * @param string|null   $relation key in mappings
     * @param string[]|null $fields
     *
     * @return string[]
     */
    public function columns(string $relation = null, array $fields = null): array
    {
        $fields = $fields ?? $this->fields;

        $map = (array) ( ! empty($relation) ? Arr::get($this->mappings, $relation) : $this->mappings);

        if (empty($map) || $map === ['*']) {
            return $fields;
        }

        $columns = [];

        foreach ($fields as $key) {
            $columns[] = Arr::get($map, $key);
        }

        $columns = array_filter(array_unique($columns));

        return empty($columns) ? ['*'] : $columns;
    }

    /**
     * Get relations for eager loading with only sparse fields.
     *
     * @return \Closure[]
     */
    public function relations(): array
    {
        $relations = [];

        foreach ($this->relations as $related => $fields) {
            $relations[$related] = function ($relation) use ($related, $fields) {
                return $relation->select($this->columns($related, $fields));
            };
        }

        return $relations;
    }

    /**
     * Parse the relations to a related key and fields value pair.
     *
     * @example ['relation1', 'relation2:field1,field2'] --> ['relation1' => ['*'], 'relation2' => ['field1', 'field2']]
     *
     * Cases that this should handle:
     *
     *      ['related1']          --> 'related1' => ['*']
     *      ['related2:*']        --> 'related2' => ['*']
     *      ['related3' => '*']   --> 'related3' => ['*']
     *      ['related4' => ['*']] --> 'related4' => ['*']
     *      ['related5' => null]  --> 'related5' => ['*']
     *      ['related6' => []]    --> 'related6' => ['*']
     *      ['related7' => '']    --> 'related7' => ['*']
     *
     * @param array $relationships
     *
     * @return array
     */
    protected function parse(array $relationships): array
    {
        $relations = [];

        foreach ($relationships as $related => $fields) {
            $fields = empty($fields) || '*' === $fields ? [] : $fields;

            if (is_string($fields)) {
                if (Str::contains($fields, ':')) {
                    $relationship = explode(':', $fields);
                    $related = array_shift($relationship);
                    $fields = head($relationship);
                } elseif ( ! Str::contains($fields, ',')) {
                    $related = $fields;
                    $fields = [];
                }
                if (is_string($fields)) {
                    $fields = explode(',', $fields);
                }
            }

            $fields = array_filter(array_unique((array) $fields));

            $relations[$related] = empty($fields) ? ['*'] : $fields;
        }

        return $relations;
    }
}
