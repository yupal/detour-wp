<?php
/*
Plugin Name: Detour WP
Description: Create Routes
Author: Iulian Palade

 */

require __DIR__ . "/src/DetourWP.php";

Yupal\DetourWP::instance();

add_action('detour\handle', function ($detour) {

  $detour->get('/hello-world',function($query){
      return 'Hello World';
  });

    $detour->after('/sample-page',function(){
                // add some script after page loads
           ?>
              <script type="text/javascript">
                  alert('I was here');
              </script>
           <?php
        
    });
    
    $detour->addTokens(array(
       ':letter' => '([a-zA-Z])' 
    ));
    
    
    // redirect test
    $detour->get('/ascii-from/:letter',function($letter){
    
        wp_safe_redirect('/ascii-to/' . ord ($letter));
        return true;
    });
    
    $detour->get('/ascii-to/:number',function($number){
        return 'ASCII CODE: ' . $number ;
    });
    
});
