# Glue

Glue is a simple PHP class that maps URLs to classes. The concepts are similar to web.py for Python.

Modifications from the original by Joe Topjian (joe@topjian.net, http://gluephp.com) include:

* Differentiated exceptions
* Configurable base URL
* Passing arguments to the controller class constructor

## Intro

### Setup

Download and unzip/untar the whole repository into a folder, which should be the `DocumentRoot` for your site.
    
Now, let's configure Apache.  Glue requires `mod_rewrite` for pretty-URLs.  Copy the `setup.htaccess` file to `.htaccess` in the same directory.  (If your web host does not allow `.htaccess` files, you will have to ask them to enable `AllowOverride All`.)

If your new site is based in a subfolder of a domain, e.g. `http://example.com/sub/folder/` is the base URL, edit the `RewriteBase`:

    RewriteBase /sub/folder
    
Also, **make sure** to follow all comments in this README's code examples dealing with sites within a subfolder.
    
By default, this `.htaccess` file hides all paths starting with `setup.` and `README` and anything within the folders `private/` and `includes/` from direct access.  You can put PHP libraries in those folders, for example, and they will not be directly accessible from the web.  You can change this by editing the following line in `.htaccess`:

    # These directories should never be accessed directly (add more as needed)
    RewriteRule ^(setup\.|README|private/|includes/) - [F,L]

Also by default, this `.htaccess` file allows direct access to any paths starting with `css/`, `js/`, and `images/`.  If you create these folders, you can put resources in them that will be accessible directly.  You can change this by editing the following line in `.htaccess`:

    # Only the images, css, and js directories can be accessed directly (add more as needed)
    RewriteCond $1 ^(index\.php|css/|js/|images/)

### Hello, World!

The following example illustrates a simple “Hello, World!”. Copy and paste the below code into `index.php`. Ensure you have glue.php in the same directory.

    <?php
        require_once('glue.php');
        $BASE_URL = '';
        // Replace the above line with the following line if your site lives in a subfolder
        // $BASE_URL = '/sub/folder');

        $urls = array(
            '/' => 'index'
        );

        class index {
            function GET() {
                echo "Hello, World!";
            }
        }

        glue::stick($urls, $BASE_URL);
    ?>

Now access the base URL of your site, e.g. `http://example.com/` or `http://example.com/sub/folder`.  If you see "Hello, World!" Apache has been configured correctly and your installation of Glue is working.

## URLs

### URL Basics

The main component of Glue is the `$urls` array. This is an associative array where the key is the URL you want to match and the value is the Class to run when matched.

The following are all “static” URLs that are being mapped to specific PHP classes:

    <?php
        $urls = array(
            '/' => 'index',
            '/contact.html' => 'contact',
            '/about.html' => 'about'
        );
    ?>

### Regular Expressions in URLs

You can also use Regular Expressions in your URL keys:

    <?php
        $urls = array(
            '/' => 'index',
            '/article/[a-zA-Z0-9]+.html' => 'article'
        );
    ?>

The above example would match:

* http://example.com
* http://example.com/article/HelloWorld.html
* http://example.com/article/abcdefg.html

### Capturing Data in URLs

You can also capture parts of the URLs and pass them on to the class methods:

    <?php
        require_once('glue.php');
        $BASE_URL = '';
        // Replace the above line with the following line if your site lives in a subfolder
        // $BASE_URL = '/sub/folder');

        $urls = array(
            '/' => 'index',
            '/(\d+)' => 'index'
        );

        class index {
            function GET($matches) {
                if ($matches[1]) {
                    echo "The magic number is: " . $matches[1];
                } else {
                    echo "You did not enter a number.";
                }
            }
        }

        glue::stick($urls, $BASE_URL);
    ?>

When you visit http://example.com you will see “You did not enter a number.”.

However, visiting http://example.com/500 will output “The magic number is 500”.

You are not restricted to using the variable name `$matches`. This can be any name you want. It will always contain an array of matched regular expressions from `$urls`.

### Using Named Regular Expressions

Named Regular Expressions are a rather unknown regular expression feature. They allow you to “tag” or name a regular expression for later reference. By using them in Glue, you’re able to have an associative `$matches` array instead of a simple index-based array.

    <?php
        require_once('glue.php');
        $BASE_URL = '';
        // Replace the above line with the following line if your site lives in a subfolder
        // $BASE_URL = '/sub/folder');

        $urls = array(
            '/' => 'index',
            '/(?P<number>\d+)' => 'index'
        );

        class index {
            function GET($matches) {
                if (array_key_exists('number', $matches)) {
                    echo "The magic number is: " . $matches['number'];
                } else {
                    echo "You did not enter a number.";
                }
            }
        }

        glue::stick($urls, $BASE_URL);
    ?>

## Class Methods

The second most important parts of Glue are the methods contained in each class. Each method corresponds to the type of HTTP Method requested. The majority of the time, these will be GET methods.

When a web page is requested, the browser issues GET. When submitting a form, the browser will issue POST with the submitted form data. There are a few other HTTP methods, but not used nearly as often as GET or POST.

This example shows how to use GET and POST to process a form:

    <?php
        require_once('glue.php');

        $urls = array(
            '/' => 'index'
        );

        class index {
            function GET() {
                echo '<form name="form1" method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
                echo '<input type="text" name="textbox1" />';
                echo '<input type="submit" name="submit" />';
                echo '</form>';
            }

            function POST() {
                echo 'The value you entered was ' . $_POST['textbox1'];
            }
        }
    ?>

## The Static Method

The final component of Glue is the glue::stick() Static Method. One argument is required: the $urls array.

    <?php
        glue::stick($urls);
    ?>
  
`glue::stick`’s job is to process the requested URL with your `$url`’s and run a matching class if one exists. If a matching URL does not exist, Glue will throw an exception.

### Configurable base URL

This version of Glue allows you to specify the base URL of the site as the second parameter of `glue::stick`.  This allows you to easily change where your Glue site is located within a domain without changing the contents of the `$urls` array.  By default this is `/`, meaning that the Glue site is at the root of the domain.

    <?php
        glue::stick($urls, '/sub/folder');
    ?>
    
### Passing arguments to the controller constructor

Sometimes it is helpful to be able to pass arguments to the constructor of the controller, usually global variables that are derived from the configuration of the site or loaded based on data in the session before URL routing.

Common uses include loading the current user's information if your site has a session-based login system, since every controller will want to have this data.  You could also imagine adjusting `$urls` based on who is logged in, e.g. to redirect unknown users to a login/registration form.

    <?php
        require_once('glue.php');
        $BASE_URL = '';
        // Replace the above line with the following line if your site lives in a subfolder
        // $BASE_URL = '/sub/folder');

        // You could imagine this function loading user info from the database, etc.
        function load_user_from_session() {
            session_start();
            return !empty($_SESSION['user']) ? $_SESSION['user'] : NULL;
        }
        $user = load_user_from_session();
        // Configuration stuff loaded from somewhere...
        $config = array('foo'=>'bar');
        
        // Arguments that glue will pass to every controller's constructor
        $args = array($user, $config);

        $urls = array(
            '/' => 'index'
        );

        abstract class Controller {
            // Let's have all controllers save the constructor arguments into member variables
            function __construct($user, $config) {
                $this->user = $user;
                $this->config = $config;
            }
        }

        class index extends Controller {
            // $this->user and $this->config will be accessible in this controller
            // and all other classes extending Controller...

            function GET() {
                var_dump($this->config);
            }
        }
        
        // extend $urls and add other classes as need be...

        glue::stick($urls, $BASE_URL, $args);
    ?>
    
This code simulates loading the `$user` from a session key and a `$config` array, and `glue::stick` will pass these as arguments to any controller it instantiates.  So, for the `/` route, it will call `$controller = new index($args[0], $args[1])` which is equivalent to `$controller = new index($user, $config)`.

It is possible to simulate this with global variables in your controllers, but this is messy and less maintainable, so passing in specific data via Glue is often preferable.

### Catching Errors: 404's, 405's, etc.

This version of Glue has been modified to throw particular exceptions when an HTTP request is received that Glue is not able to handle.

* `BadMethodCallException` is thrown when the user performs an HTTP action that does not have a corresponding method in your class; e.g., the user performed a `POST` on a URL but the class for that URL has no `POST()` method.
* `ControllerNotFoundException` is thrown when your `$urls` array maps a URL to a class that could not be loaded.
* `URLNotFoundException` is thrown when the user visits a URL that isn't in your `$urls` page, most likely the result of following a bad link--this is your typical 404 Not Found error.

Without any handlers, these exceptions will spew rather ugly looking errors to the browser.  You can catch them in the typical PHP manner, and display nice error pages instead:

    <?php
        try {
            glue::stick($urls, BASE_URL, array($g));
        } catch (BadMethodCallException $e) {
            // show your own 405 Method Not Allowed page.
        } catch (ControllerNotFoundException $e) {
            // Show your own 500 My Code Done Broke page.
        } catch (URLNotFoundException $e) {
            // Show your own 404 Not Found page.
        }
    ?>