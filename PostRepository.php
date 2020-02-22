<?php

namespace Rumur\WPUtils\Repository;

use Rumur\WPUtils\Contracts\Repository\IPostRepository;
use Rumur\WPUtils\Repository\Exception\InvalidPostType;
use Rumur\WPUtils\Repository\Exception\PostNotCreated;
use Rumur\WPUtils\Repository\Exception\PostNotFound;
use Rumur\WPUtils\Repository\Exception\PostNotUpdated;
use Rumur\WPUtils\Support\Collection;

class PostRepository implements IPostRepository
{
    /**
     * @var array
     */
    protected $query_params = [];

    /**
     * The default post type.
     * If the post won't be provided the `post` will be used as a default.
     *
     * @var string
     */
    protected $post_type;

    /**
     * Adds a scope by a `post_type`.
     *
     * @param string $post_type
     * @return static
     * @throws InvalidPostType
     */
    public function usePostType(string $post_type)
    {
        if (!post_type_exists($post_type)) {
            throw new InvalidPostType($post_type);
        }

        $this->post_type = $post_type;

        return $this;
    }

    /**
     * @param array $args
     * @return \WP_Query
     */
    public function query(array $args = []): \WP_Query
    {
        /**
         * @link https://developer.wordpress.org/reference/classes/wp_query/
         * @link https://kinsta.com/blog/wp-query/
         */
        $query = new \WP_Query(
            $this->prepareForQuery($args)
        );

        $query->posts = $this->prepareAfterQuery($query->posts);

        return $query;
    }

    /**
     * Queries for the WP_Post
     *
     * @param array $args
     * @return Collection
     */
    public function get(array $args = []): Collection
    {
        return $this->makeCollection($this->query($args)->posts);
    }

    /**
     * @inheritdoc
     * @param int $limit The `-1` sets no limit to the query.
     * @return Collection
     */
    public function all($limit = -1): Collection
    {
        $args = [
            'posts_per_page' => max(-1, $limit),
        ];

        if ($args['posts_per_page'] < 0) {
            /**
             * When we don’t need pagination, we should ever set no_found_rows to true,
             * making the query run dramatically faster.
             */
            $args['no_found_rows'] = true;
        }

        return $this->get($args);
    }

    /**
     * @inheritdoc
     *
     * @param int $id
     * @return mixed
     * @throws PostNotFound
     */
    public function find(int $post_id)
    {
        $found_post = $this->get(['post__in' => [$post_id], 'post_status' => 'any'])->first();

        if (null === $found_post) {
            throw PostNotFound::byId($post_id);
        }

        return $found_post;
    }

    /**
     * @inheritdoc
     * @param int ...$post_ids
     *
     * @return Collection
     */
    public function only(...$post_ids): Collection
    {
        // That means the ids have been passed as one array of ids.
        if (is_array($post_ids[0]) && func_num_args() === 1) {
            [$post_ids] = array_values($post_ids);
        }

        return $this->get(['post__in' => $post_ids]);
    }

    /**
     * @param int ...$post_ids
     * @return Collection
     */
    public function except(...$post_ids): Collection
    {
        // That means the ids have been passed as one array of ids.
        if (is_array($post_ids[0]) && func_num_args() === 1) {
            [$post_ids] = array_values($post_ids);
        }

        return $this->get(['post__not_in' => $post_ids]);
    }

    /**
     * @inheritdoc
     * @param array $data
     * @return \WP_Post
     * @throws PostNotCreated
     * @throws PostNotFound
     */
    public function create(array $data): \WP_Post
    {
        try {

            $prepared_data = $this->prepareForCreate($data);

            $post_id = wp_insert_post($prepared_data, true);

            if (is_wp_error($post_id)) {
                throw new PostNotCreated($post_id->get_error_message());
            }

            $this->updateOrCreate($prepared_data, $post_id);

        } catch (\Throwable $e) {
            throw new PostNotCreated($e->getMessage());
        }

        return $this->find($post_id);
    }

    /**
     * Updates the resource
     *
     * @param int $post_id
     * @param array $data
     * @return \WP_Post
     * @throws PostNotUpdated
     * @throws PostNotFound
     */
    public function update(int $post_id, array $data): \WP_Post
    {
        try {

            $prepared_data = $this->prepareForUpdate($post_id, $data);

            $prepared_data['ID'] = $post_id;

            $post_id = wp_update_post($prepared_data, true);

            if (is_wp_error($post_id)) {
                throw new PostNotUpdated($post_id->get_error_message());
            }

            $this->updateOrCreate($prepared_data, $post_id);

        } catch (\Throwable $e) {
            throw new PostNotUpdated($e->getMessage());
        }

        return $this->find($post_id);
    }

    /**
     * @inheritdoc
     * @param int $post_id
     * @return bool
     */
    public function delete(int $post_id, $force = false): bool
    {
        /**
         * @link https://codex.wordpress.org/Function_Reference/wp_delete_post
         */
        return (bool)wp_delete_post($post_id, $force);
    }

    /**
     * Deletes entirely, without trash.
     *
     * @param int $post_id
     * @return bool
     */
    public function forceDelete(int $post_id): bool
    {
        return $this->delete($post_id, true);
    }

    /**
     * Tels to the query to ignore the sticky posts.
     *
     * @return static
     */
    public function ignoreSticky()
    {
        return $this->where('ignore_sticky_posts', true);
    }

    /**
     * Tels to the query to ignore the sticky posts.
     *
     * @return static
     */
    public function ignoreFilters()
    {
        return $this->where('suppress_filters', true);
    }

    /**
     * Tels to the query to ignore the pagination.
     *
     * @return static
     */
    public function ignorePagination()
    {
        return $this
            ->where('nopaging', true)
            ->where('no_found_rows', true);
    }

    /**
     * Build a chain query params.
     *
     * @param $key
     * @param $value
     * @return static
     */
    public function where($key, $value)
    {
        return $this->addQueryParam($key, $value);
    }

    /**
     * Resets the query params.
     *
     * @return static
     */
    public function reset()
    {
        $this->query_params = [];

        return $this;
    }

    /**
     * @param $key
     * @param $value
     * @return static
     */
    protected function addQueryParam($key, $value)
    {
        switch ($key) {
            case 'id':
            case 'ids':
                $key = 'post__in';
                $value = is_string($value) ? explode(',', $value) : (array)$value;
                break;
            case 'type':
            case 'parent':
            case 'status':
            case 'password':
            case 'parent__in':
                $key = 'post_' . $key;
                break;
            case 'parent_in':
                $key = 'post_parent__in';
                break;
            case 'per_page':
                $key = 'posts_per_page';
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
     * Creates or updates MetaFields
     *
     * @param array $data
     * @param int $post_id
     */
    protected function updateOrCreate(array $data, int $post_id): void
    {
        // Do something here.
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
     * Makes some data manipulation with data before being updated.
     *
     * @param int $post_id
     * @param array $data
     * @return array
     */
    protected function prepareForUpdate(int $post_id, array $data): array
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
        $data['post_type'] = $this->post_type ?? 'post';

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
        return array_merge($this->query_params, $args,
            [
                'post_type' => $this->post_type ?? 'post',
            ]
        );
    }

    /**
     * Prepares items after the query.
     *
     * @param array $posts
     * @return array
     */
    protected function prepareAfterQuery(array $posts): array
    {
        return $posts;
    }
}
