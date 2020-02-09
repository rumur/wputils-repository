<?php

namespace Rumur\WPUtils\Repository;

use Rumur\WPUtils\Contracts\Repository\ITermResourceRepository;

class TermResourceRepository extends TermRepository implements ITermResourceRepository
{
    /**
     * @var object;
     */
    protected $resource;

    /**
     * @inheritdoc
     * @return static
     */
    public function useResource($resource = null)
    {
        $this->resource = $resource;

        return $this;
    }

    /**
     * Provides the full list of available taxonomies for a specific repository
     *
     * @return array
     */
    protected function availableTaxonomies(): array
    {
        $all = parent::availableTaxonomies();

        if (!$this->resource) {
            return $all;
        }

        $origin_taxonomies = (array)get_object_taxonomies($this->resource, 'names');
        // result should ['taxonomy_name' => 'taxonomy_name']
        $adjusted_to_comparison = array_combine($origin_taxonomies, $origin_taxonomies);

        return array_intersect_key($adjusted_to_comparison, $all);
    }

    /**
     * @inheritdoc
     * @param array $args
     * @return array
     */
    protected function prepareForQuery(array $args = []): array
    {
        $adjusted_args = array_merge($args, []);

        if ($this->resource) {
            $adjusted_args['object_ids'] = [$this->resource->ID];
        }

        return parent::prepareForQuery($adjusted_args);
    }
}
