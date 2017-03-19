# DetourWP
Simple WordPress router ( inspired by ToroPHP router )

## Features
  - routing using strings, regular expressions, and defined types  (`number`, `string`, `alpha`)
  - before and after route hooks
  - wp_query parameters hook ( `$detour->query` )
  
## Getting started

Simple route with hello world example.

```php
<?php

require __DIR__ . "/DetourWP.php";
Yupal\DetourWP::instance();

add_action('detour\handle', function ($detour) {

    $detour->get('/hello-world',function(){
      return 'Hello world';
    });
    
});

```
The `detour\handle` action injects the `DetourWP` singleton
and provides a simple way to add routes.

You can add routes directly using the HTTP verb:

```php

$detour->get($route, $callback);
$detour->post($route, $callback);
$detour->put($route, $callback);
$detour->patch($route, $callback);
$detour->delete($route, $callback);

// or if it does not metter you can use `any`

$detour->any($route, $callback);

```

**Note: If the `$callback` does not return any value, DetourWP will consider the function a hook to the main WordPress query. Any `echo` inside the `$callback` will be caught in a buffer and will be ignored ( this will ensure some consistencies on how the routes are handled )**

## Route Parameters

```php
<?php

add_action('detour\handle', function ($detour) {

    $detour->get('/say-my-name/:string',function($name){
      return 'Hello ' . $name;
    });
    
    // same example with regular expressions instead of tokens
    
    $detour->get('/say-my-name/([a-zA-Z]+)',function($name){
      return 'Hello ' . $name;
    });
    
});

```
By default DetourWP provides a few general tokens ( `:any` , `:string`, `:number`, `:alpha` ) to help you build routes without writing regular expression. 
Pattern matches are passed in order as arguments to the callback attached to request method. Like in the previous example the `:string` match was passed as the first argument in the callback.

## Before / After route hooks
  DetourWP provides two types of hooks before and after request `$callback` was called.
The hooks are called with the same arguments as the `$callback` function

```php
<?php

add_action('detour\handle', function ($detour) {

    $detour->get('/say-my-name/:string',function($name){
      return 'Hello ' . $name;
    });
   
    // before the callback handler is called 
    // if the user is not an admin send a `404` 
    $detour->before('/say-my-name/([a-zA-Z]+)',function($name){
        if(!is_admin()){
          global $wp_query;
          $wp_query->set_404();
          status_header(404);
          nocache_headers();
          die();
        }
    });
    
    $detour->after('/say-my-name/([a-zA-Z]+)',function($name){
      // call a custom function for logs, or send mails 
      log_same_data($name);
    });
});

```
  
## Custom tokens registration
  Adding your custom tokens can be made in two ways:
  - using the `detour\tokens` filter
  - calling the `$detour->addTokens` singleton in a `detour\handle` action


```php
<?php

add_action('detour\handle', function ($detour) {

    $detour->addTokens(
      array(
        ':char':'([a-zA-Z])',
      )
    );

    $detour->get('test/:char',function($char){
      return 'The char is: ' . $char;
    });
    
});

// OR

add_filter('detour\tokens',function($currentTokens){
  $currentTokens = array_merge(
    $currentTokens,
     array(
        ':char':'([a-zA-Z])',
      )
  );
  return $currentTokens;
});

```
  In each case tokens are added by usgin a `key => value` pair, where the `key` is the token and the  `value` is the regular expression that should be used.

## The `query` hook

The `query` hook is a special type of hook defined by DetourWP to make an easier interaction with the main wordpress query.

```php

add_action('detour\handle',function($detour){

    $detour->query('/last-post',function($wp_query /*[,other arguments] */){
        return array(
          'post_type' => 'post',
          'orderby' => 'post_date',
          'order' => 'DESC',
	        'numberposts'=>1,
          
          // `template` is a parameter used by DetourWP to overwrite the default template used by wp_query
        );
    });
});

```

In the previous example the `query` hook will return the last post added. The callback should return an array with a list of `WP_Query` parameters. Also a custom parameter was added in DetourWP: `template`.

### `Template` parameter

The optional `template` parameter added in the `query` hook will let you set a custom template file to be used by WordPress when displays the content. The template file value can be:
  - a filepath within the theme ( a relative path to the current theme root )
  - a filepath within the entire wordpress instance ( a relative path to the Wordpress root instance )
  - an absolute filepath
 
 The template value can ommit the `.php` extension

## License

DetourWP was created by [Iulian Palade](https://github.com/yupal/) and released under the MIT License.
