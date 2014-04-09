<?php namespace Glue;

use \Exception, \BadMethodCallException, \ReflectionClass;


/**
 *  A simple router that maps URIs to classes.
 *  
 *  Derived from [GluePHP](http://gluephp.com) created by Joe Topjian.
 *  
 *  When the URLs are processed:
 *		* deliminators (/) are automatically escaped: (\/)
 *		* The beginning and end are anchored (^ $)
 *		* An optional end slash is added (/?)
 *		* The i option is added for case-insensitive searches
 */
class Glue {
	
	protected $baseUrl = '';
	protected $routes = array();
	protected $methodTranslator = null;
	
	
	public function __construct($baseUrl = '') {
		$this->baseUrl = preg_quote($baseUrl, '#');
	}
	
	
	/**
	 *  Match a URI and method to a controller.
	 *  
	 *  @param   string? $path           The URI to match.  If not provided, the value of `$_SERVER['REQUEST_URI']` is used.
	 *  @param   string? $httpMethod     The HTTP method that was used.  If not provided, the value of `$_SERVER['REQUEST_METHOD']` is used.
	 *  @throws  ControllerNotFound		 Thrown if corresponding class is not found.
	 *  @throws  URLNotFoundException	 Thrown if no match is found.
	 *  @throws  BadMethodCallException  Thrown if a corresponding method is not found.
	 */
	public function stick($path = null, $httpMethod = null) {
		$path = $path ?: self::removeQueryString($_SERVER['REQUEST_URI']);
		$httpMethod = $httpMethod ?: strtoupper($_SERVER['REQUEST_METHOD']);
		krsort($this->routes);

		foreach ($this->routes as $regex => $routeInfo) {
			if (preg_match("#$regex#i", $path, $matches)) {
				list($className, $classArgs) = $routeInfo;
				
				if (class_exists($className)) {
					$class = new ReflectionClass($className);
					$controller = $class->newInstanceArgs($classArgs);
					$methodName = $this->getControllerMethod($httpMethod);
					
					if (method_exists($controller, $methodName)) {
						$method = $class->getMethod($methodName);
						$params = $method->getParameters();
						$methodArgs = array();
						
						foreach ($params as $parameter) {
							if ($parameter->name === 'matches') {
								$methodArgs[] = $matches;
							} else if (isset($matches[$parameter->name])) {
								$methodArgs[] = $matches[$parameter->name];
							} else {
								$methodArgs[] = null;
							}
						}
						
						return $method->invokeArgs($controller, $methodArgs);
					}
					
					throw new BadMethodCallException("Method $httpMethod not supported by class $className");
				}
				
				throw new ControllerNotFoundException("Class $className not found");
			}
		}
		
		throw new URLNotFoundException("URI $path not found");
	}
	
	
	public function addRoute($pattern, $controller, $args = array()) {
		$pattern = '^' . $this->baseUrl . $pattern . '/?$';
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
	
	
	public static function removeQueryString($uri) {
		return preg_replace('/\\?.*$/', '', $uri);
	}
	
}


class ControllerNotFoundException extends Exception {}
class URLNotFoundException extends Exception {}
