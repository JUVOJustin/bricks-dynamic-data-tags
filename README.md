# God damn easy Bricks Builder Dynamic Data Tags
You are a developer and want to add dynamic data tags to the bricks builder? This package is for you. From now on you can add your dynamic data tags with 3 lines of code.
It automatically registered tags, parses filters and even allows you to add your very own pattern parsing.

Feature overview:
- Simple registration of dynamic data tags
- Automatic parsing of filters
- Custom pattern parsing
- Output Filtering with wp_kses

## Quick Demo using within Child Theme
[<img src="https://img.youtube.com/vi/pJ4j5iZKKV0/maxresdefault.jpg" width="50%">](https://youtu.be/pJ4j5iZKKV0)

## Installation
To install the package you can use composer. Run the following command in your terminal:
```bash
composer require juvo/bricks-dynamic-data-tags
```

You should initiate the registry as early as possible. The best place to do this is in your plugin's main file or the functions.php of your theme.
```php
add_action('init', function() {
    juvo\Bricks_Dynamic_Data_Tags\DDT_Registry::getInstance();
});
```

## Usage
To register a simple dynamic data tag you can use the following code snippet. The first parameter is the tag name, the second parameter is the tag label, the third parameter is the tag group and the last parameter is the callback that returns the tag output.
```php
DDT_Registry::getInstance()
    ->set('my_tag', 'My Tag', 'My Tag Group', function($post, $context, array $filters = []) {
        return "Hello World";
    });
```

To register another tag to the same group you simply do:
```php
DDT_Registry::getInstance()
    ->set('my_tag2', 'My Tag 2', 'My Tag Group', function($post, $context, array $filters = []) {
        return "Hello World 2";
    });
```

[![Bricks Builder Dynamic Data Tags List](https://i.postimg.cc/bNP0y8Tr/Capture-2024-02-17-140801.png)](https://postimg.cc/7bBJ9Fjr)

## Filter tags that get registered
The `juvo/register_dynamic_tags` filter allows you to modify the tags that get registered. This filter passes the at this point added data tags as array. You can use this to remove tags.
```php
apply_filters('juvo/dynamic_tags/register', $tags);
```

## Filter the tag pattern
The `juvo/dynamic_data_tag/parse_tag` filter allows you to modify how all tags are parsed. This filter passes the tag pattern and the tag name as arguments. It allows you to parse a custom structure of for your data tags. To add new variables that are passed to the callback make sure to add them as a [named capturing group](https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Regular_expressions/Named_capturing_group) with the regex.
```php
// Adds "modifier" as a named capturing group to the tag pattern
add_filter("juvo/dynamic_data_tag/parse_tag", function($pattern, $tag) {
    $pattern = str_replace("}/", "", $pattern);
    return $pattern . "(\~(?<modifier>[0-9a-zA-Z_-]+(\~[0-9a-zA-Z_-]+)*))?}/";
}, 10, 2);
```

Your callback now needs one more parameter:
```
DDT_Registry::getInstance()->set( 'single_tag', 'Single Tag', 'Custom Tags', function( $post, $context, array $filters = [], array $modifier = [] )
``` 

### Filter the tag pattern by tag name
The `juvo/dynamic_data_tag/parse_tag` filter allows you to modify the tag pattern for a specific tag.
```php
apply_filters("juvo/dynamic_data_tag/parse_tag/$tag", $pattern, $this->tag);
```

### Modify the data passed to the callback
If you used one of the `parse_tag` filters to add your own structure and variables to a tag, you can use this filter to modify the variable itself. This is needed e.g. if you can add your new strucutre multiple times.
By default parameters are split with ":" and filters are split with "|". If you add a new structure that is for example split with "~" you should use this filter split the data accordingly.
```php
add_filter("juvo/dynamic_data_tag/parse_tag/pattern_modifier", function($value) {
    return explode("~", $value);
});
```

### Use named filters
It is possible to register dynamic data tags like these: `{single_tag:tag=tag_slug:link=true}`. In your callback you need to parse the filter values to work with key value pairs. This example allows you to display tags, filter which tag to display by slug and filter the tags name should be wrapped in a link to the term itself.
```php
DDT_Registry::getInstance()->set( 'single_tag', 'Single Tag', 'Custom Tags', function( $post, $context, array $filters = [] ) {
    // Parse filters to be key-value pairs
    $parsed_filters = [];
    foreach ( $filters as &$item ) {
        list( $key, $value ) = explode( '=', $item );
        $parsed_filters[ $key ] = $value;
    }
    $filters = $parsed_filters;
    
    $tags = get_the_terms( get_the_ID(), 'post_tag' );
    if ( empty( $tags ) || is_wp_error( $tags ) ) {
        return '';
    }
    
    // Filter to select a specific tag
    $selected_tag = $filters['tag'] ?? '';
    $output       = [];
    
    foreach ( $tags as $tag ) {
        if ((!empty($selected_tag) && $tag->slug === $selected_tag) || empty($selected_tag)) {
    
            // Check if we need links or not
            if ( isset( $filters['link'] ) && $filters['link'] === 'true' ) {
                $output[] = '<a href="' . esc_url( get_term_link( $tag ) ) . '">' . esc_html( $tag->name ) . '</a>';
            } else {
                $output[] = esc_html( $tag->name );
            }
        }
    }
    
    return implode( ', ', $output );
    } );
```
In this example

## Modify allowed html tags
The output of the callback is filtered with `wp_kses` to prevent XSS attacks. You can modify the allowed tags with the `wp_kses_allowed_html` filter. This filter passes the allowed tags and the context as arguments. You can use this to modify allowed html tags.
```php
add_filter("wp_kses_allowed_html", function($allowedtags, $context) {
    if ($context !== "juvo/dynamic_data_tag") {
        return $allowedtags;
    }
    $allowedtags['iframe'] = [
        'src' => true,
        'width' => true,
        'height' => true,
        'frameborder' => true,
        'allow' => true,
        'allowfullscreen' => true,
        'title' => true,
    ];
    return $allowedtags;
        }, 10, 2);
```

To allow different html tags per dynamic data tag there is also a special hook. As you can see in the code snippet, general tags are filtered first and then passed to a tag specific filter.
```php
$allowed_tags = wp_kses_allowed_html("juvo/dynamic_data_tag");
$allowed_tags = apply_filters("juvo/dynamic_data_tag/allowed_html_tags/$tag", $allowed_tags);
```

## Advanced examples:
### iFrame
Registers a dynamic data tag that displays the current post embedded in an iFrame.
```php
// Register '{collection}' tag
DDT_Registry::getInstance()
    ->set('collection', 'Collection', 'Collections', function($post, $context, array $filters = []) {
        return "<iframe src='" . get_permalink($post) . "'></iframe>";
    });

// Add custom allowed html tags for the collection tag. In this case we allow iframes.
add_filter("juvo/dynamic_data_tag/allowed_html_tags/collection", function($allowedtags) {
    $allowedtags['iframe'] = [
        'src' => true,
        'width' => true,
        'height' => true,
        'frameborder' => true,
        'allow' => true,
        'allowfullscreen' => true,
        'title' => true,
    ];
    return $allowedtags;
});
```
### Post Data
Register a dynamic data tag {post_data} to display post data. A filter allows to select which data to display. Another custom filter "bold" allows to mark certain data to be bolded.
The tag can be used like this: `{post_data:title:post_type~bold=post_type~bold=title}` will be displayed as "**Title**, Post Type".
```php
DDT_Registry::getInstance()->set(
    'post_data',
    'Post Data',
    'Posts',
    function( \WP_Post $post, string $context, array $filters= [], array $bold = [] ) {

        $output = [];
        foreach ( $filters as $filter ) {
            switch ( $filter ) {
                case 'title':
                    $output['title'] = get_the_title();
                    break;
                case 'excerpt':
                    $output['excerpt'] = get_the_excerpt();
                    break;
                case 'content':
                    $output['content'] = get_the_content();
                    break;
                case 'post_type':
                    $output['post_type'] = get_post_type();
                    break;
            }
        }

        // Add some of the filtered values tobe bold
        foreach($bold as $key) {
            if (in_array($key, $filters)) {
                $output[$key] = "<b>".$output[$key]."</b>";
            }
        }

        return implode( ', ', $output );
    }
);

// Add a custom pattern to introduce a "bold" capture group.
add_filter("juvo/dynamic_data_tag/parse_tag/post_data", function($pattern, $tag) {
    $pattern = str_replace("}/", "", $pattern);
    return $pattern . "(?:~bold=(?<bold>[0-9a-zA-Z_-]+))*}/"; // use ~bold= as separator
}, 10, 2);

// To allow the freshly added "bold" capture group to have multiple values we need to split the values by the separator
add_filter("juvo/dynamic_data_tag/parse_tag/pattern_bold", function($value) {
    return explode("~bold=", $value);
});
```
