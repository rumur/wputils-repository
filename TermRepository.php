<?php

namespace Rumur\WPUtils\Repository;

use Rumur\WPUtils\Contracts\Repository\ITermRepository;
use Rumur\WPUtils\Support\Collection;

class TermRepository implements ITermRepository
{
    /**
     * The list of taxonomies that should be guarded
     * while making any query.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * @var array
     */
    protected $query_params = [];

    /**
     * @inheritdoc
     * @param int $term_id
     * @param string|null $taxonomy
     *
     * @return null|\WP_Term
     */
    public function find(int $term_id, string $taxonomy = null): ?\WP_Term
    {
        return $this->get(['include' => array_filter([$term_id]), 'taxonomy' => $taxonomy])->first();
    }

    /**
     * @inheritdoc
     * @return Collection
     */
    public function all(): Collection
    {
        return $this->get()->sortBy('taxonomy');
    }

    /**
     * Gets a specific resources.
     *
     * @param string ...$taxonomies
     * @return Collection
     */
    public function only(...$taxonomies): Collection
    {
        // That means the ids have been passed as one array of ids.
        if (is_array($taxonomies[0]) && func_num_args() === 1) {
            [$taxonomies] = array_values($taxonomies);
        }

        return $this->get(['taxonomy' => $taxonomies])->sortBy('taxonomy');
    }

    /**
     * Gets all except specific taxonomies.
     *
     * @param string ...$taxonomies
     * @return Collection
     */
    public function except(...$taxonomies): Collection
    {
        // That means the ids have been passed as one array of ids.
        if (is_array($taxonomies[0]) && func_num_args() === 1) {
            [$taxonomies] = array_values($taxonomies);
        }

        $desired_list = array_diff_key(
            $this->availableTaxonomies(),
            array_fill_keys((array)$taxonomies, 'null')
        );

        return $this->get(['taxonomy' => $desired_list])->sortBy('taxonomy');
    }

    /**
     * Queries for the []\WP_Term
     *
     * @param array $args
     * @return Collection
     */
    public function get(array $args = []): Collection
    {
        /**
         * @link https://developer.wordpress.org/reference/functions/get_terms/
         */
        var_dump($this->prepareForQuery($args));
        $terms = get_terms($this->prepareForQuery($args));

        if (is_wp_error($terms)) {
            error_log($terms->get_error_message());
            $terms = null;
        }

        return $this->prepareAfterQuery($this->makeCollection($terms));
    }

    /**
     * @inheritdoc
     * @return \WP_Term
     */
    public function update(int $term_id, array $data, string $taxonomy = null): \WP_Term
    {
        $desired_taxonomy = $taxonomy ?? $data['taxonomy'] ?? $this->query_params['taxonomy'] ?? null;

        if (!$desired_taxonomy) {
            throw new \InvalidArgumentException(
                'The `taxonomy` should be provided either within the `$data` param or `$taxonomy` param or via `where` method'
            );
        }

        $sanitised_taxonomy = $this->prepareTaxonomies($desired_taxonomy)[0];

        $prepared_data = $this->prepareForUpdate($data);

        /**
         * @link https://developer.wordpress.org/reference/functions/wp_update_term/
         */
        $term_info = wp_update_term($term_id, $sanitised_taxonomy,
            $this->makeCollection($prepared_data)->except(['taxonomy'])->toArray()
        );

        if (is_wp_error($term_info)) {
            throw new \RuntimeException($term_info->get_error_message());
        }

        return $this->find((int)$term_info['term_id'], (string)$sanitised_taxonomy);
    }

    /**
     * @param array|string $data
     * @param string|null $taxonomy
     * @return null|\WP_Term
     */
    public function create($data, string $taxonomy = null): ?\WP_Term
    {
        $desired_taxonomy = $taxonomy ?? $data['taxonomy'] ?? $this->query_params['taxonomy'] ?? null;

        if (!$desired_taxonomy) {
            throw new \InvalidArgumentException(
                'The `taxonomy` should be provided either within the `$data` param or `$taxonomy` param or via `where` method'
            );
        }

        $sanitised_taxonomy = $this->prepareTaxonomies($desired_taxonomy)[0];

        if (is_string($data)) {
            $data = ['name' => $data];
        }

        $prepared_data = $this->prepareForCreate($data);

        /**
         * @link https://codex.wordpress.org/Function_Reference/wp_insert_term
         */
        $term_info = wp_create_term($prepared_data['name'], $sanitised_taxonomy);

        if (is_wp_error($term_info)) {
            throw new \RuntimeException($term_info->get_error_message());
        }

        $update_data = $this->makeCollection($prepared_data)->except(['taxonomy', 'name'])->toArray();

        if (!empty($update_data)) {
            /**
             * @link https://developer.wordpress.org/reference/functions/wp_update_term/
             */
            $term_info = wp_update_term($term_info['term_id'], $sanitised_taxonomy, $update_data);

            if (is_wp_error($term_info)) {
                throw new \RuntimeException($term_info->get_error_message());
            }
        }

        return $this->find((int)$term_info['term_id'], (string)$sanitised_taxonomy);
    }

    /**
     * @inheritdoc
     * @param int $term_id
     * @param string $taxonomy
     * @return bool
     */
    public function delete(int $term_id, string $taxonomy = null): bool
    {
        $desired_taxonomy = $taxonomy ?? $this->query_params['taxonomy'] ?? null;

        if (!$desired_taxonomy) {
            throw new \InvalidArgumentException(
                'The `taxonomy` should be provided either as a `$taxonomy` param or via `where` method'
            );
        }

        $sanitised_taxonomy = $this->prepareTaxonomies($desired_taxonomy)[0];

        $is_deleted = wp_delete_term($term_id, $sanitised_taxonomy);

        if (is_wp_error($is_deleted)) {
            throw new \RuntimeException($is_deleted);
        }

        return (boolean) $is_deleted;
    }

    /**
     * Build a chain query params.
     *
     * @param $key
     * @param $value
     * @return static
     */
    public function where($key, $value): ITermRepository
    {
        return $this->addQueryParam($key, $value);
    }

    /**
     * Resets the query params.
     *
     * @return static
     */
    public function reset(): ITermRepository
    {
        $this->query_params = [];

        return $this;
    }

    /**
     * @param $key
     * @param $value
     * @return static
     */
    protected function addQueryParam($key, $value): ITermRepository
    {
        switch ($key) {
            case 'id':
            case 'term':
            case 'term_id':
                $key = 'include';
                break;
            case 'post':
            case 'posts':
            case 'post_id':
            case 'resource':
                $key = 'object_ids';
                break;
        }

        if (isset($this->query_params[$key]) && is_array($value)) {
            $existed = is_string($this->query_params[$key])
                ? explode(',', $this->query_params[$key])
                : (array) $value;

            $this->query_params[$key] = array_unique(
                array_merge($existed, $value)
            );
        } else {
            $this->query_params[$key] = $value;
        }

        return $this;
    }

    /**
     * Makes a collection from a passed $items
     *
     * @param $items
     * @return Collection
     */
    protected function makeCollection($items): Collection
    {
        return Collection::make($items);
    }

    /**
     * Provides the full list of available taxonomies for a specific repository
     *
     * @return array
     */
    protected function availableTaxonomies(): array
    {
        return (array)get_taxonomies(['_builtin' => false]);
    }

    /**
     * Prepares the passed taxonomies list.
     * 
     * If passed the 
     * 
     * @param null $taxonomies
     * @return array
     */
    protected function prepareTaxonomies($taxonomies = null): array
    {
        if (null !== $taxonomies) {
            $prepared = is_array($taxonomies) ? $taxonomies : [$taxonomies];
        } else {
            $prepared = $this->availableTaxonomies();
        }

        return array_filter(
            array_diff($prepared, $this->guarded), function ($taxonomy) {
            return taxonomy_exists($taxonomy);
        });
    }

    /**
     * Makes some data manipulation with data before being updated.
     *
     * @param array $data
     * @return array
     */
    protected function prepareForUpdate(array $data): array
    {
        return $data;
    }

    /**
     * Makes some data manipulation with data before being created.
     *
     * @param array $data
     * @return array
     */
    protected function prepareForCreate(array $data): array
    {
        return $data;
    }

    /**
     * Prepare the args for a query.
     *
     * @param array $args
     * @return array
     */
    protected function prepareForQuery(array $args = []): array
    {
        $taxonomy = $this->prepareTaxonomies($args['taxonomy'] ?? $this->query_params['taxonomy'] ?? null);

        return array_merge(
            $this->query_params,
            $args,
            [
                'hide_empty' => false,
                'taxonomy' => $taxonomy,
            ]
        );
    }

    /**
     * Prepares items after the query.
     *
     * @param Collection $items
     * @return Collection
     */
    protected function prepareAfterQuery(Collection $items): Collection
    {
        return $items;
    }
}
