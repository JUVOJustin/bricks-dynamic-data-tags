# God damn easy Bricks Builder Dynamic Data Tags
You are a developer and want to add dynamic data tags to the bricks builder? This package is for you. From now on you can add your dynamic data tags with 3 lines of code.
It automatically registered tags, parses filters and even allows you to add your very own pattern parsing.

Feature overview:
- Simple registration of dynamic data tags
- Automatic parsing of filters
- Custom pattern parsing
- Output Filtering with wp_kses

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
    ->set('my_tag', 'My Tag', 'My Tag Group', function($post, $context, $filters) {
        return "Hello World";
    });
```

To register another tag to the same group you simply do:
```php
DDT_Registry::getInstance()
    ->set('my_tag2', 'My Tag 2', 'My Tag Group', function($post, $context, $filters) {
        return "Hello World 2";
    });
```

[![Bricks Builder Dynamic Data Tags List](https://i.postimg.cc/bNP0y8Tr/Capture-2024-02-17-140801.png)](https://postimg.cc/7bBJ9Fjr)


## Hooks
### Filter tags that get registered
The `juvo/register_dynamic_tags` filter allows you to modify the tags that get registered. This filter passes the at this point added data tags as array. You can use this to remove tags.
```php
apply_filters('juvo/dynamic_tags/register', $tags);
```

### Filter the tag pattern
The `juvo/dynamic_data_tag/parse_tag` filter allows you to modify how all tags are parsed. This filter passes the tag pattern and the tag name as arguments. It allows you to parse a custom structure of for your data tags. To add new variables that are passed to the callback make sure to add them as a [named capturing group](https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Regular_expressions/Named_capturing_group) with the regex.
```php
// Adds "modifier" as a named capturing group to the tag pattern
add_filter("juvo/dynamic_data_tag/parse_tag", function($pattern, $tag) {
    $pattern = str_replace("}/", "", $pattern);
    return $pattern . "(\~(?<modifier>[1-9a-zA-Z_]+(\~[1-9a-zA-Z_]+)*))?}/";
}, 10, 2);
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

### Modify allowed html tags
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

## Full customization example:
```php
// Register 'collection' tag
DDT_Registry::getInstance()
    ->set('collection', 'Collection', 'Collections', function($post, $context, $filters) {
        return "<iframe src='" . get_permalink($post) . "'></iframe>";
    });

// Add custom dynamic data tag structure by adding a modifier group. Tag will look like this: {collection~modifier1~modifier2}
add_filter("juvo/dynamic_data_tag/parse_tag/collection", function($pattern, $tag) {
    $pattern = str_replace("}/", "", $pattern);
    return $pattern . "(\~(?<modifier>[1-9a-zA-Z_]+(\~[1-9a-zA-Z_]+)*))?}/";
}, 10, 2);

// Split the modifier by ~. Needed for multiple modifiers
add_filter("juvo/dynamic_data_tag/parse_tag/pattern_modifier", function($value) {
    return explode("~", $value);
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
