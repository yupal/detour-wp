# DetourWP
Simple Wordpress router ( inspired by ToroPHP router )

## Features
  - routing using strings, regular expressions, and defined types
  (`number`, `string`, `alpha`)
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
    
})

```
The `torowp\handle` action injects the `DetourWP` singleton
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
    
})

```
By default Detour provides a few general tokens ( `:any` , `:string`, `:number`, `:alpha` ) to help you build routes without writing regular expression. 
Pattern matches are passed in order as arguments to the callback attached to request method. Like in the previous example the `:string` match was passed as the first argument in the callback.

## Before / After route hooks
  Comming soon
  
## Custom tokens registration
  Comming soon
