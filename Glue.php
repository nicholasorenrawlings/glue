<?php namespace Glue;

use \Exception, \BadMethodCallException, \ReflectionClass;


/**
 *  A simple router that maps URIs to classes.
 *  
 *  Derived from [GluePHP](http://gluephp.com) created by Joe Topjian.
 */
class Glue {
	
	protected $baseUrl = '';
	protected $routes = array();
	protected $methodTranslator = null;
	
	
	/**
	 *  Constructor.
	 *  
	 *  @param  string $baseUrl=''
	 */
	public function __construct($baseUrl = '') {
		$this->baseUrl = preg_quote($baseUrl, '#');
	}
	
	
	/**
	 *  Add a single route.
	 *  
	 *  Regular expressions are automatically anchored to the beginning and end
	 *  of the URI.  An optional trailing slash is also added.  Regular expressions
	 *  are evaluated in a case-insensitive manner.
	 *  
	 *  @param  string  $pattern     The regular expression to match URIs against.
	 *  @param  string  $controller  The name of the controller class.
	 *  @param  mixed[] $args        Optional arguments to pass to the controller's constructor.
	 */
	public function addRoute($pattern, $controller, $args = array()) {
		$pattern = '#^' . $this->baseUrl . $pattern . '/?$#i';
		$this->routes[$pattern] = array($controller, $args);
	}
	
	
	/**
	 *  Add multiple routes.
	 *  
	 *  @param  array $routes   The associative array in which the keys are regular
	 *  expressions and the values are either a string containing the name of a 
	 *  controller class or an array where the first item is a string containing 
	 *  the name of a controller class and any subsequent values are parameters
	 *  to be passed to the controller's constructor.
	 */
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
	
	
	/**
	 *  Translate an HTTP method to a controller method.
	 *  
	 *  @param   string $httpMethod   The HTTP method to translate.
	 *  @return  string               The name of the controller method to invoke.
	 */
	protected function getControllerMethod($httpMethod) {
		if ($this->methodTranslator) {
			return call_user_func($this->methodTranslator, $httpMethod);
		} else {
			return $httpMethod;
		}
	}
	
	
	/**
	 *  Remove the query string from a request URI.
	 *  
	 *  @param   string $uri   The original URI.
	 *  @return  string        The URI with the query string removed.
	 */
	public static function removeQueryString($uri) {
		return preg_replace('/\\?.*$/', '', $uri);
	}
	
	
	/**
	 *  Set the routine Glue uses to translate HTTP methods into controller methods.
	 *  
	 *  @param  callable? $translator   A function that converts an HTTP method into a controller method names.
	 */
	public function setMethodTranslator(callable $translator) {
		$this->methodTranslator = $translator;
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
			if (preg_match($regex, $path, $matches)) {
				list($className, $classArgs) = $routeInfo;
				
				if (class_exists($className)) {
					$class = new ReflectionClass($className);
					$controller = $class->newInstanceArgs($classArgs);
					$methodName = $this->getControllerMethod($httpMethod);
					
					if (method_exists($controller, $methodName)) {
						$method = $class->getMethod($methodName);
						
						if ($method->isPublic()) {
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
						
						throw new BadMethodCallException("$className::$methodName() is not accessible");
					}
					
					throw new BadMethodCallException("$className::$methodName() is not defined");
				}
				
				throw new ControllerNotFoundException("$className is not defined");
			}
		}
		
		throw new ResourceNotFoundException("The URI $path does not match any defined routes");
	}
	
}


class ControllerNotFoundException extends Exception {}
class ResourceNotFoundException extends Exception {}
