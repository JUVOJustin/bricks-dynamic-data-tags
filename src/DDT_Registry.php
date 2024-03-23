<?php

namespace juvo\Bricks_Dynamic_Data_Tags;

class DDT_Registry
{

    private static ?DDT_Registry $instance = null;
    private array $storage = [];

    private function __construct() {
        add_action('init', function() {
            $this->registerDataTags();
        }, 99);
    }

    public static function getInstance(): DDT_Registry {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    /**
     * @param string $tag
     * @param string $label
     * @param string $group
     * @param callable $callback Callable type for PHP callbacks.
     * @return void
     */
    public function set(string $tag, string $label, string $group, callable $callback): void {
        $this->storage[$tag] = new Dynamic_Data_Tag($tag, $label, $group, $callback);
    }

    /**
     * @param string $tag
     * @return Dynamic_Data_Tag|null
     */
    public function get(string $tag) {
        return $this->storage[$tag] ?? null;
    }

    public function getAll():array {
        return $this->storage;
    }

    /**
     * Legacy function to register triggers that do not use the factory
     *
     * @return void
     * @Deprecated
     */
    public function registerDataTags(): void
    {
        $tags = apply_filters('juvo/dynamic_tags/register', $this->storage);
        foreach ($tags as $tag) {
            add_filter('bricks/dynamic_tags_list', [$tag, 'add_tag_to_builder']);
            add_filter('bricks/dynamic_data/render_tag', [$tag, 'get_tag_value'], 10, 3);
            add_filter('bricks/dynamic_data/render_content', [$tag, 'render_tag'], 10, 3);
            add_filter('bricks/frontend/render_data', [$tag, 'render_tag'], 10, 2);
        }
    }

}
