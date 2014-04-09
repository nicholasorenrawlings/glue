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
	 *  @param   string? $method         The HTTP method that was used.  If not provided, the value of `$_SERVER['REQUEST_METHOD']` is used.
	 *  @throws  ControllerNotFound		 Thrown if corresponding class is not found.
	 *  @throws  URLNotFoundException	 Thrown if no match is found.
	 *  @throws  BadMethodCallException  Thrown if a corresponding method is not found.
	 */
	public function stick($path = null, $method = null) {

		$path = $path ?: preg_replace('/\\?.*$/', '', $_SERVER['REQUEST_URI']);
		$method = $method ?: strtoupper($_SERVER['REQUEST_METHOD']);
		krsort($this->routes);

		foreach ($this->routes as $regex => $controller) {
			if (preg_match("#$regex#i", $path, $matches)) {
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
	
}


class ControllerNotFoundException extends Exception {}
class URLNotFoundException extends Exception {}
