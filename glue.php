<?php namespace Glue;

use \Exception, \BadMethodCallException, \ReflectionClass;

    /**
     * Modified from glue: http://gluephp.com/
     * Changed to allow a base URL, to throw better exceptions on failure,
     * and to be able to pass arguments to the controller class constructor,
     * and to ignore the query string.
     *
     * Provides an easy way to map URLs to classes. URLs can be literal
     * strings or regular expressions.
     *
     * When the URLs are processed:
     *      * deliminators (/) are automatically escaped: (\/)
     *      * The beginning and end are anchored (^ $)
     *      * An optional end slash is added (/?)
     *	    * The i option is added for case-insensitive searches
     *
     * Example:
     *
     * require_once('glue.php'); 
     *
     * $urls = array(
     *     '/' => 'index',
     *     '/page/(\d+) => 'page'
     * );
     *
     * class page {
     *      function GET($matches) {
     *          echo "Your requested page " . $matches[1];
     *      }
     * }
     *
     * glue::stick($urls);
     *
     */
    class Glue {

        protected $baseUrl = '';
        protected $routes = array();
        protected $methodTranslator = null;
        
        public function __construct($baseUrl = '') {
        	$this->baseUrl = preg_quote($baseUrl);
        }
        
        /**
         * stick
         *
         * the main static function of the glue class.
         *
         * @param   array     $urls         The regex-based url to class mapping
         * @param   string    $base_url     The base url for the website
         * @param   array     $globals      Global variables to be extracted into the class
         * @throws  ControllerNotFound      Thrown if corresponding class is not found
         * @throws  URLNotFoundException    Thrown if no match is found
         * @throws  BadMethodCallException  Thrown if a corresponding GET,POST is not found
         *
         */
        public function stick($path = null, $method = null) {

            $path = $path ?: preg_replace('/\\?.*$/', '', $_SERVER['REQUEST_URI']);
            $method = $method ?: strtoupper($_SERVER['REQUEST_METHOD']);
            krsort($this->routes);

            foreach ($this->routes as $regex => $controller) {
            	if (preg_match("/$regex/i", $path, $matches)) {
                    $found = true;
                    list($class, $args) = $controller;
                    if (class_exists($class)) {
                        if (count($args) && method_exists($class, '__construct')) {
                          $reflect = new ReflectionClass($class);
                          $obj = $reflect->newInstanceArgs($args);
                        } else {
                          $obj = new $class;
                        }
                        $methodName = $this->getControllerMethod($method);
                        if (method_exists($obj, $methodName)) {
                            $classReflection = new ReflectionClass($class);
                            $methodReflection = $classReflection->getMethod($methodName);
                            $methodParameters = $methodReflection->getParameters();
							$parameterValues = array();
							foreach ($methodParameters as $parameter) {
								if ($parameter->name === 'matches') {
									$parameterValues[] = $matches;
								} else if (isset($matches[$parameter->name])) {
									$parameterValues[] = $matches[$parameter->name];
								} else {
									$parameterValues[] = null;
								}
							}
							return $methodReflection->invokeArgs($obj, $parameterValues);
                        } else {
                            throw new BadMethodCallException("Method, $method, not supported.");
                        }
                    } else {
                        throw new ControllerNotFoundException("Class, $class, not found.");
                    }
                }
            }
            
            throw new URLNotFoundException("URL, $path, not found.");
        }
        
        
        public function addRoute($pattern, $controller, $args = array()) {
        	$pattern = str_replace('/', '\/', $pattern);
        	$pattern = '^' . $this->baseUrl . $pattern . '\/?$';
        	$this->routes[$pattern] = array($controller, $args);
        }
        
        public function addRoutes(array $routes) {
        	foreach ($routes as $pattern => $controller) {
        		if (is_array($controller)) {
        			$class = array_shift($controller);
        			$this->addRoute($pattern, $class, $controller);
        		} else {
        			$this->addRoute($pattern, $controller);
        		}
        	}
        }
        
        
        public function setMethodTranslator(callable $translator) {
        	$this->methodTranslator = $translator;
        }
        
        protected function getControllerMethod($httpMethod) {
        	if ($this->methodTranslator) {
        		return call_user_func($this->methodTranslator, $httpMethod);
        	} else {
        		return $httpMethod;
        	}
        }
        
    }
    
    class ControllerNotFoundException extends Exception {}
    class URLNotFoundException extends Exception {}
