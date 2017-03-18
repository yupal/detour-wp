<?php
/*
Plugin Name: ToRo WP
Description: Create Routes
Author: Iulian Palade

 */

require __DIR__ . "/DetourWP.php";

Yupal\DetourWP::instance();
add_action('torowp\handle', function ($detour) {

  $detour->get('/nelu',function($query){
      return 'x';
  });

    $detour->after('/sample-page',function(){
        
           ?>
              <!--<script type="text/javascript">-->
              <!--    alert('I was here');-->
              <!--</script>-->
           <?php
        
    });
    
    $detour->addTokens(array(
       ':letter' => '([a-zA-Z])' 
    ));
    
    $detour->get('/nelu/:letter',function($letter){
    
        wp_safe_redirect('/gigi/' . ord ($letter));
        return true;
    });
    
    $detour->get('/gigi/:number',function($number){
        return 'Data: ' . $number ;
    });
    
});
