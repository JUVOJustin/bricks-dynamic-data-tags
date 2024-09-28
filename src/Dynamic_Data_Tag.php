<?php

namespace juvo\Bricks_Dynamic_Data_Tags;

use Exception;

class Dynamic_Data_Tag
{

    private string $tag;
    private string $label;
    private string $group;

    private $callback;

    /**
     * @param string $tag
     * @param string $label
     * @param string $group
     * @param callable $callback
     */
    public function __construct(string $tag, string $label, string $group, callable $callback)
    {
        $this->tag = $tag;
        $this->label = $label;
        $this->group = $group;
        $this->callback = $callback;
    }

    /**
     * Use the bricks/dynamic_tags_list filter to render your custom dynamic data tag in the builder.
     *
     * @param array $tags
     * @return array
     */
    public function add_tag_to_builder(array $tags): array
    {
        $tags[] = [
            'name'  => '{' . $this->tag . '}',
            'label' => $this->label,
            'group' => $this->group,
        ];
        return $tags;
    }

	/**
	 * Callback function for the actual tag. Called in get_tag_value() and render_tag(). This is where you should do your logic.
	 *
	 * @param $post
	 * @param string $context
	 * @param array $variables
	 *
	 * @return mixed
	 * @throws Exception
	 */
    public function run_tag($post, string $context, array $variables = []): mixed {
        if (is_callable($this->callback)) {
            // Post is always the first parameter
            $args = array_merge([$post, $context], $variables);
            return call_user_func_array($this->callback, $args);
        } else {
            throw new Exception("The provided callback is not callable.");
        }
    }

    /**
     * This will be used when \Bricks\Integrations\Dynamic_Data\Providers::render_tag() is called to parse a specific tag.
     *
     * @param mixed $tag
     * @param mixed $post
     * @param string $context
     * @return mixed
     * @throws Exception
     */
    public function get_tag_value( $tag, $post, string $context = 'text')
    {
        return $this->render_tag($tag, $post, $context);
    }

    /**
     * These will be used when \Bricks\Integrations\Dynamic_Data\Providers::render_content() is invoked to parse strings that may contain various dynamic tags within the content. One of the functions that perform this action is bricks_render_dynamic_data().
     *
     * @param mixed $content
     * @param mixed $post
     * @param string $context
     * @return mixed
     * @throws Exception
     */
    public function render_tag( $content, $post, string $context = 'text')
    {
        // Workaround: In some cases content is not a string, but an array. The reason is unclear.
        if (!is_string($content)) {
            return $content;
        }

        // Exit early if the tag is not in the content
        if ( ! str_contains( $content, $this->tag ) ) {
            return $content;
        }

        // Parse tags in content. Be aware that tags can occur multiple times
        $matches = $this->parse_tag($content);

        foreach ($matches as $match_groups) {

            $variables = [];

            // Iterate $match_groups and if key is a string make it a variable to be passed to run_tag
            foreach ($match_groups as $key => $value) {
                if (!is_string($key)) {
                    continue;
                }

                // Split the parameters and filters syntax
                switch ($key) {
                    case 'filters':
                        $value = explode(':', $value);
                        break;
                    default:
                        $value = apply_filters("juvo/dynamic_data_tag/parse_tag/pattern_$key", $value);
                        $value = apply_filters("juvo/dynamic_data_tag/parse_tag/$this->tag/pattern_$key", $value);
                        break;
                }

				// Enforce parameters to be an array
				if (is_string($value)) {
					$value = [
						$value
					];
				}

                $variables[$key] = $value;
            }

            // Start with filters to ensure they are first and second
            $sortedVariables = [
                'filters' => $variables['filters'] ?? [],
            ];

            // Remove filters from the original variables array
            unset($variables['filters']);

            // Create sorted variables
            $variables = array_merge($sortedVariables, $variables);

            // Run the tag with the variables
            $value = $this->run_tag($post, $context, array_values($variables));

            // Images need to be returned as array of ideas. If only the id is returned simplify that.
            if ($context === 'image') {
                if (is_numeric($value)) {
                    return [$value];
                }
                return $value;
            }

            // If the value is null, replace it with an empty string
            if ($value === null) {
                $value = "";
            }

            // Sanitize value if not empty
            if (is_string($value)) {
                $allowed_tags = wp_kses_allowed_html("juvo/dynamic_data_tag");
                $allowed_tags = apply_filters("juvo/dynamic_data_tag/allowed_html_tags/$this->tag", $allowed_tags);
                $value = wp_kses($value, $allowed_tags, []);
            }

            // Replace the tag with the transformed value
            $content = str_replace($match_groups[0], $value, $content);
        }

        return $content;
    }

    /**
     * Parse a string to get the parameters from the tag.
     * Support data separated by ":" and "|".
     *
     * @param string $content
     * @return string[][]
     */
    private function parse_tag(string $content): array
    {
        $pattern = "/{" .$this->tag. "(?::(?<filters>[0-9a-zA-Z_-]+(?::[0-9a-zA-Z_-]+)*))?}/";
        $pattern = apply_filters("juvo/dynamic_data_tag/parse_tag", $pattern, $this->tag);
        $pattern = apply_filters("juvo/dynamic_data_tag/parse_tag/$this->tag", $pattern, $this->tag);

        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);
        return $matches;
    }
}
