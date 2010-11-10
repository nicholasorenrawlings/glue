<?php

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
    class glue {

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
        static function stick ($urls, $base_url='', $args=array()) {

            $method = strtoupper($_SERVER['REQUEST_METHOD']);
            $path = preg_replace('/\\?.*$/', '', $_SERVER['REQUEST_URI']);
            $base_url = preg_quote($base_url, '/');

            $found = false;

            krsort($urls);

            foreach ($urls as $regex => $class) {
                $regex = str_replace('/', '\/', $regex);
                $regex = '^' . $base_url . $regex . '\/?$';
                if (preg_match("/$regex/i", $path, $matches)) {
                    $found = true;
                    if (class_exists($class)) {
                        if (count($args) && method_exists($class, '__construct')) {
                          $reflect = new ReflectionClass($class);
                          $obj = $reflect->newInstanceArgs($args);
                        } else {
                          $obj = new $class;
                        }
                        if (method_exists($obj, $method)) {
                            $obj->$method($matches);
                        } else {
                            throw new BadMethodCallException("Method, $method, not supported.");
                        }
                    } else {
                        throw new ControllerNotFoundException("Class, $class, not found.");
                    }
                    break;
                }
            }
            if (!$found) {
                throw new URLNotFoundException("URL, $path, not found.");
            }
        }
    }
    
    class ControllerNotFoundException extends Exception {}
    class URLNotFoundException extends Exception {}
