<?php

namespace ArtisanSdk\Support\Tests;

use ArtisanSdk\Contract\FieldMapping;
use ArtisanSdk\Support\SparseFields;
use Illuminate\Support\Fluent;

class SparseFieldsTest extends TestCase
{
    /**
     * Test that sparse mappings does nothing by default and is passthru.
     */
    public function test_that_sparse_mappings_does_nothing_by_default()
    {
        $sparse = new SparseFields();
        $this->assertSame(['*'], $sparse->columns());
        $this->assertSame([], $sparse->relations());
        $this->assertSame([], $sparse->mappings());
    }

    /**
     * Test that fields are mapped to columns.
     */
    public function test_that_fields_are_mapped_to_columns()
    {
        // Top level map
        $sparse = new SparseFields(['foo', 'bar'], null, ['foo' => 'fizz', 'bar' => 'bazz']);
        $this->assertSame(['fizz', 'bazz'], $sparse->columns());

        // Nested map
        $sparse = new SparseFields(['foo', 'bar'], null, ['model' => ['foo' => 'fizz', 'bar' => 'bazz']]);
        $this->assertSame(['fizz', 'bazz'], $sparse->columns('model'));

        // Empty map
        $sparse = new SparseFields(['foo', 'bar'], null, ['model' => null]);
        $this->assertSame(['foo', 'bar'], $sparse->columns('model'));

        // Inclusive map
        $sparse = new SparseFields(['foo', 'bar', 'hot'], null, ['foo' => 'fizz', 'bar' => 'bazz']);
        $this->assertSame(['fizz', 'bazz'], $sparse->columns());
    }

    /**
     * Test that relations default to having wildcard field mappings.
     */
    public function test_that_relations_default_to_wildcard_field_mappings()
    {
        $sparse = new SparseFields(null, [
            'related1',
            'related2:*',
            'related3' => '*',
            'related4' => ['*'],
            'related5' => null,
            'related6' => [],
            'related7' => '',
        ]);

        foreach ($sparse->relations() as $relation => $closure) {
            $this->assertSame(['*'], $closure($this->queryBuilder())->columns);
        }

        $sparse = new SparseFields(null, [
            'related1:foo,bar',
            'related2' => 'foo,bar',
            'related3' => ['foo', 'bar'],
        ], [
            'related1' => ['foo' => 'fizz', 'bar' => 'bazz'],
            'related2' => ['foo' => 'fizz', 'bar' => 'bazz'],
            'related3' => ['foo' => 'fizz', 'bar' => 'bazz'],
        ]);

        foreach ($sparse->relations() as $relation => $closure) {
            $this->assertSame(['fizz', 'bazz'], $closure($this->queryBuilder())->columns);
        }
    }

    /**
     * Test that sparse fields can be constructed from request.
     */
    public function test_that_sparse_can_be_made_from_request()
    {
        $request = new Fluent(['fields' => ['foo', 'bar'], 'relations' => ['relation']]);
        $sparse = SparseFields::make($request, ['model' => ['foo' => 'fizz', 'bar' => 'bazz'], 'relation' => null]);
        $relations = $sparse->relations();
        $closure = head($relations);

        $this->assertCount(1, $relations);
        $this->assertSame(['fizz', 'bazz'], $sparse->columns('model'));
        $this->assertSame(['*'], $closure($this->queryBuilder())->columns);

        $request = new Fluent(['fields' => ['foo', 'bar']]);
        $sparse = SparseFields::make($request, new class() implements FieldMapping {
            public function mappings(): array
            {
                return ['foo' => 'fizz', 'bar' => 'bazz'];
            }
        });

        $this->assertSame(['fizz', 'bazz'], $sparse->columns());
    }

    /**
     * Create a double of the relationship query builder.
     *
     * @return \Class
     */
    protected function queryBuilder()
    {
        return new class() {
            public $columns = [];

            public function select(array $columns)
            {
                $this->columns = $columns;

                return $this;
            }
        };
    }
}
