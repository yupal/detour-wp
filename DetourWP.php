<?php


namespace Yupal;

if (!class_exists('\\Yupal\\DetourWP')) {
    class DetourWP
    {
        // singleton instance
        private static $instance = null;

        private $tokens    = array();
        private $routerKey = "detourwp";
        private $routes    = array();
        private $hooksKeys = array('before','after','query');
        
        private $hooks   = array(
            'before' => array(),
            'after'  => array(),
            'query'  => array(),
        );
        
        private $methods = array('*', 'GET', 'POST', 'PUT', 'PATCH', 'DELETE');

        public function __construct($routerKey = null)
        {

            // add wp query var
            if ($routerKey) {
                $this->routerKey = $routerKey;
            }

            // set user tokens

            // add defayult tokens
            $this->addTokens(
                array(
                    '*'       => '(.*)',
                    ':any'    => '(.*)',
                    ':string' => '([a-zA-Z]+)',
                    ':number' => '([0-9]+)',
                    ':alpha'  => '([a-zA-Z0-9-_]+)',
                )
            );

            // add wp actions
            add_action('init', function () {
                $this->addRewrites();
            }, PHP_INT_MAX);

            add_action('parse_request', function () {
                $this->handle();
            });
        }

        // magic methods / filters settings function
        public function __call($method, $arguments)
        {
            $_method = strtoupper($method);
            if (in_array($_method, $this->methods) || $_method === 'any') {
                if ($_method === 'any') {
                    $_method = '*';
                }
                
                array_unshift($arguments, $_method);
                call_user_func_array(array($this, 'route'), $arguments);
            } else {
                $when = strtolower($method);

                if (in_array($when, $this->hooksKeys)) {
                    array_unshift($arguments, $when);
                    call_user_func_array(array($this, 'routeHook'), $arguments);
                } else {
                    throw new Exception('Endpoint "' . $method . '" does not exist');
                }
            }
        }

        public function jsonHeader()
        {
            @header('Content-Type: application/json; charset=' . get_option('blog_charset'));
        }

        public function addTokens($tokens = array())
        {
            $this->tokens = array_merge($this->tokens, $tokens);
        }

        public function route($methods, $pattern, $callback = null)
        {
            $pattern = untrailingslashit($pattern);
            $regex = strtr($pattern, $this->tokens);

            if ($regex[0] === "/") {
                $regex = substr($regex, 1);
            }

            if ($methods) {
                if (!is_array($methods)) {
                    $methods = array($methods);
                    
                    foreach ($methods as $m) {
                        if ('*' === $m) {
                            $methods = null;
                            break;
                        }
                    }
                }
            }

            if (is_array($callback)) {
                if (isset($callback['before']) && is_callable($callback['before'])) {
                    $this->before($pattern, $callback);
                }
                
                if (isset($callback['after']) && is_callable($callback['after'])) {
                    $this->after($pattern, $callback);
                }
                
                if (isset($callback['callback']) && is_callable($callback['callback'])) {
                    $callback = $callback['callback'];
                } else {
                    $callback = null;
                }
            }

            $hash                = md5($pattern);
            $this->routes[$hash] = array(
                "callback" => $callback,
                "regex"    => $regex,
                "methods"  => $methods,
                "pattern"  => $pattern,
            );
        }

        public function routeHook($when, $pattern, $callback)
        {
            $pattern = untrailingslashit($pattern);
            if (!in_array($when, $this->hooksKeys)) {
                return;
            }
            $regex = strtr($pattern, $this->tokens);

            $this->filters[$when][] = array(
                'regex'    => $regex,
                'callback' => $callback,
            );
            
            
            // add an empty route if there is none to trigger the filter
            // that will be overriden if a real route is added
            $hash = md5($pattern);
            if (!isset($this->routes[$hash])) {
                $this->get($pattern);
            }
        }

        private function applyHooks()
        {
            $this->tokens = apply_filters('detour\tokens', $this->tokens);
            do_action('detour\handle', $this);
        }

        private function addRewrites()
        {
            global $wp, $wp_rewrite;

            if (WP_DEBUG) {
                flush_rewrite_rules();
            }

            $this->applyHooks();
            
            foreach ($this->routes as $hash => $data) {
                $regex     = $data['regex'];
                $rewriteTo = $wp_rewrite->index . '?' . $this->routerKey . '=' . $hash;

                $wp_rewrite->add_rule(
                    '^' . $regex . '/?$',
                    $rewriteTo,
                    'top'
                );

                $wp_rewrite->add_rule(
                    '^' . $wp_rewrite->index . '/' . $regex . '/?$',
                    $rewriteTo,
                    'top'
                );
            }

            $wp->add_query_var($this->routerKey);
        }

        private function getPath()
        {
            $path = '/';
            if (!empty($_SERVER['PATH_INFO'])) {
                $path = $_SERVER['PATH_INFO'];
            } elseif (!empty($_SERVER['ORIG_PATH_INFO']) && $_SERVER['ORIG_PATH_INFO'] !== '/index.php') {
                $path = $_SERVER['ORIG_PATH_INFO'];
            } else {
                if (!empty($_SERVER['REQUEST_URI'])) {
                    $path = (strpos($_SERVER['REQUEST_URI'], '?') > 0) ?
                        strstr($_SERVER['REQUEST_URI'], '?', true) :
                            $_SERVER['REQUEST_URI'];
                }
            }

            return $path;
        }

        private function handle()
        {
            global $wp, $wp_rewrite;

            if (isset($wp->query_vars[$this->routerKey])) {
                $hash   = $wp->query_vars[$this->routerKey];
                $method = $_SERVER['REQUEST_METHOD'];

                if (isset($this->routes[$hash])) {
                    $data = $this->routes[$hash];
                    
                    if ($data['methods']) {
                        if (!in_array($method, $data['methods'])) {
                            return;
                        }
                    }

                    $callback = $data['callback'];

                    $regex = $data['regex'];

                    $matches = array();
                    if (preg_match('#^/?' . $regex . '/?$#', $this->getPath(), $matches)) {
                        if (count($matches)) {
                            unset($matches[0]);
                        }

                        $this->executeHandler($callback, $matches);
                    }
                }
            }
        }

        private function executeHandler($callback, $arguments)
        {
            $path = $this->getPath();
    
            // look for wp query filters
            
            $queryFilters = $this->hooks['query'];
            add_action('pre_get_posts', function ($query) use ($queryFilters, $arguments, $path) {
                $queryFiltersData = array();
                
                $args = $arguments;
                array_unshift($args, $query);
                
                foreach ($queryFilters as $hook) {
                    if (preg_match('#^/?' . $hook['regex'] . '/?$#', $path)) {
                        $result = call_user_func_array($hook['callback'], $args);
                        
                        if (is_array($result)) {
                            $queryFiltersData = array_merge($queryFiltersData, $result);
                        }
                    }
                }

                if (!empty($queryFiltersData)) {
                    // Reset query variables, because `WP_Query` does nothing with
                    // detour query var
                    $query->init();
                
                    // Set date query based on custom vars
                    
                    if (isset($queryFiltersData['template'])) {
                        $tpl = $queryFiltersData['template'];
                       
                        // allow template filename without extension
                        $tpl = preg_replace('"\.php"', '', $tpl) . ".php";
                       
                        add_filter('template_include', function ($template) use ($tpl) {
                            $located = locate_template(array($tpl));
                           
                            if (empty($located)) {
                                // see if the template path is defined relative to wp path
                                if (file_exists(ABSPATH . "/" . $tpl)) {
                                    $tpl = ABSPATH . "/" . $tpl;
                                } else {
                                    // see if the template path is absolute
                                    if (!file_exists($tpl)) {
                                        $tpl = $template;
                                    }
                                }
                            } else {
                                $tpl = $located;
                            }
                           
                            return $tpl;
                        });
                       
                        unset($queryFiltersData['template']);
                    }
                    
                    foreach ($queryFiltersData as $key => $data) {
                        $query->set($key, $data);
                    }
                }
            });
            
            
            foreach ($this->hooks['before'] as $hook) {
                if (preg_match('#^/?' . $hook['regex'] . '/?$#', $path)) {
                    call_user_func_array($hook['callback'], $arguments);
                }
            }

            // keept things simple not echos in handles
            ob_start();
            $contentResponse = call_user_func_array($callback, $arguments);
            $contentBuffer   = ob_get_clean();

            if (empty($contentResponse)) {
                $afterFiltes = $this->hooks['after'];
                add_action('wp_footer', function () use ($afterFiltes, $arguments, $path) {
                    foreach ($afterFiltes as $hook) {
                        if (preg_match('#^/?' . $hook['regex'] . '/?$#', $path)) {
                            call_user_func_array($hook['callback'], $arguments);
                        }
                    }
                });
                    
                return;
            } else {
                echo $contentResponse;

                foreach ($this->hooks['after'] as $hook) {
                    if (preg_match('#^/?' . $hook['regex'] . '/?$#', $path)) {
                        call_user_func_array($hook['callback'], $arguments);
                    }
                }

                exit;
            }
        }

        public static function instance()
        {
            if (!self::$instance) {
                $routerKey = null;

                if (defined('DETOURWP_ROUTER_KEY')) {
                    $routerKey = DETOURWP_ROUTER_KEY;
                }

                self::$instance = new static($routerKey);
            }

            return self::$instance;
        }
    }
}
