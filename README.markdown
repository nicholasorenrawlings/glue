# Glue

* Original Author: Joe Topjian, joe@topjian.net
* Modified by powerpak@github
* The original Glue's site is found at http://gluephp.com.

Glue is a simple PHP class that maps URLs to classes. The concepts are similar to web.py for Python.

## Intro

### Setup

Download and unzip/untar the whole repository into a folder, which should be the `DocumentRoot` for your site.
    
Now, let's configure Apache.  Glue requires `mod_rewrite` for pretty-URLs.  Copy the `setup.htaccess` file to `.htaccess` in the same directory.  (If your web host does not allow `.htaccess` files, you will have to ask them to enable `AllowOverride All`.)

If your new site is based in a subfolder of a domain, e.g. `http://example.com/sub/folder/` is the index, edit the `RewriteBase`:

    RewriteBase /sub/folder/
    
Otherwise, leave it as is.
    
By default, this `.htaccess` file hides all paths starting with `setup.` and `README` and anything within the folders `private/` and `includes/` from direct access.  You can put PHP libraries in those folders, for example, and they will not be directly accessible from the web.  You can change this by editing the following line in `.htaccess`:

    # These directories should never be accessed directly (add more as needed)
    RewriteRule ^(setup\.|README|private/|includes/) - [F,L]

Also by default, this `.htaccess` file allows direct access to any paths starting with `css/`, `js/`, and `images/`.  If you create these folders, you can put resources in them that will be accessible directly.  You can change this by editing the following line in `.htaccess`:

    # Only the images, css, and js directories can be accessed directly (add more as needed)
    RewriteCond $1 ^(index\.php|css/|js/|images/)

### Hello, World!

The following example illustrates a simple “Hello, World!”. Copy and paste the below code into `index.php` and then access it. Ensure you have glue.php in the same directory.

    <?php
        require_once('glue.php');

        $urls = array(
            '/' => 'index'
        );

        class index {
            function GET() {
                echo "Hello, World!";
            }
        }

        glue::stick($urls);
    ?>

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

        glue::stick($urls);
    ?>

When you visit http://example.com you will see “You did not enter a number.”.

However, visiting http://example.com/500 will output “The magic number is 500”.

You are not restricted to using the variable name `$matches`. This can be any name you want. It will always contain an array of matched regular expressions from `$urls`.

### Using Named Regular Expressions

Named Regular Expressions are a rather unknown regular expression feature. They allow you to “tag” or name a regular expression for later reference. By using them in Glue, you’re able to have an associative `$matches` array instead of a simple index-based array.

    <?php
        require_once('glue.php');

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

        glue::stick($urls);
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

The final component of Glue is the glue::stick() Static Method. It takes one argument: the $urls array.

    <?php
        glue::stick($urls);
    ?>
  
`glue::stick`’s job is to process the requested URL with your `$url`’s and run a matching class if one exists. If a matching URL does not exist, Glue will throw an error.