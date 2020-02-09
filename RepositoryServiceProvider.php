<?php

namespace Rumur\WPUtils\Repository;

use Rumur\WPUtils\Contracts\Repository\ITermRepository;
use Rumur\WPUtils\Contracts\Repository\IPostRepository;
use Rumur\WPUtils\Contracts\Repository\ITermResourceRepository;

use Pimple\Container;
use Pimple\ServiceProviderInterface;

class RepositoryServiceProvider implements ServiceProviderInterface
{
    /**
     * @param Container $app
     */
    public function register(Container $app): void
    {
        /**
         * @param $app
         * @return IPostRepository
         */
        $app[IPostRepository::class] = function ($app) {
            return new PostRepository();
        };

        /**
         * @param $app
         * @return ITermRepository
         */
        $app[ITermRepository::class] = function ($app) {
            // We need such functionality for repository to functional properly.
            if (defined('ABSPATH') && !function_exists('wp_create_term')) {
                include_once(ABSPATH . 'wp-admin/includes/taxonomy.php');
            }
            return new TermRepository();
        };

        /**
         * @param $app
         * @return ITermResourceRepository
         */
        $app[ITermResourceRepository::class] = function ($app) {
            // We need such functionality for repository to functional properly.
            if (defined('ABSPATH') && !function_exists('wp_create_term')) {
                include_once(ABSPATH . 'wp-admin/includes/taxonomy.php');
            }
            return new TermResourceRepository();
        };
    }
}
