Sprockets-PHP
===============

# What is Sprockets-PHP

Sprockets-PHP is a port of Sprockets, the well-known Asset Manager for Rails.
Sprockets-PHP allows you to manage your assets by taking care of preprocessors, dependencies, minification and caching.
The Asset Pipeline will read your main file (usually "application.js" or "application.css"), read directives, and apply filters for all the files.
This is an example usage

`application.js`
```js
/**
 * (see the "directive syntax" section below)
 *= require jquery
 *= require lib/form
 *= require lib/inputs/{text,password}
 *= require_directory lib/loaders
 */
```

`lib/form/index.js.coffee`
```coffee
class @Form
  @Inputs = {}
  constructor: ->
//= require /base-input
```

`/lib/form/base-input.js.coffee`
```js
class @Form.BaseInput
```

`lib/inputs/text.js.coffee`
```coffee
class @Form.Inputs.Text extends @Form.BaseInput
  @type: 'Text'
```

`lib/inputs/password.js.ls`
```ls
class @Form.Inputs.Password extends @Form.BaseInput
  @type = 'Password'
  -> console.log <[base password]>
```

It's primary meant to deal with JS and CSS but can as well be used for HTML (HAML, Twig, Slim...).
You can add your own filters in a very flexible way (see below).

# How can I use it ?!

You have to create an instance of `Sprockets\Pipeline`.
The argument is the array of "base paths" from where the Pipeline has to search files.

If you want to call directly the Pipeline, you can then do `$pipeline($asset_type)`.
For example `$pipeline('css');`.
The CMS will load `application.css` in one of the base paths.
This file must contain "directives", like Sprockets's one.

```php
// require your autoloader
...

// read paths.json - see below
// you can of course pass a normal array !
$paths = str_replace('%template%', 'MyTemplate', file_get_contents('paths.json'));
$paths = json_decode($paths, true);

// create a pipeline
$pipeline = new Sprockets\Pipeline($paths);

// finds `application.css` in the paths
echo $pipeline('css');

// uses `layout.css`
echo $pipeline('css', 'layout');

// same as the first example, but will cache it into a file
$cache = new Sprockets\Cache($pipeline, 'css', $vars = array(), $options = array());
// $options you can pass :
// `minify` whether you want to minify the output or not
// - `.js` : Minified through [Esmangle](https://github.com/Constellation/esmangle)
// - `.css` : Minified through [Clean-CSS](https://github.com/GoalSmashers/clean-css)
$content = $cache->getContent();
$filename = (string) $cache;
//or
$filename = $cache->getFilename();
```

## Asset Paths

The asset paths are divided by "modules", allowing you for the greatest modularity :

```json
{
  "template": {
    "directories": [
      "app/themes/%template%/assets/",
      "app/themes/_shared/assets/",
      "lib/assets/",
      "vendor/assets/"
    ],
    "prefixes": {
      "js": "javascripts",
      "css": "stylesheets",
      "img": "images",
      "font": "fonts"
    }
  },
  "external": {
    "directories": [
      "vendor/bower/",
      "vendor/components/"
    ]
  }
}
```

You have 2 keys in each modules : the `directories`, which list directories where the Pipeline must search files, and `prefixes`, which will append the path for the extension to the directory (ie a `js` file will get `javascripts/` appended to its paths).

For example, if we run `$pipeline('js')`, the pipeline will try to find the following files :
 - `app/themes/%template%/assets/javascripts/application.js` (`%template%` being replaced in the example above)
 - `app/themes/_shared/assets/javascripts/application.js`
 - `lib/assets/javascripts/application.js`
 - `vendor/assets/javascripts/application.js`
 - `vendor/bower/application.js`
 - `vendor/components/application.js`

This example file, allowing to use a Rails-like `javascripts/` directory for js file gracefully, also supports `//= require jquery/jquery` to find `vendor/bower/jquery/jquery.js`

Only the "meaningful" extension matters (using a whitelist).
```js
/**
 * for example
 *= require datatables/js/jquery.dataTables
 * will find correctly the file named
 * "vendor/bower/datatables/js/jquery.dataTables.js.coffee"
 * and the "coffee" filter will be correctly applied.
 */
```

## Caching
Something to note : even if you're not using `Sprockets\Cache`, the asset pipeline will keep a file list cache in your cache directory, to speed up path lookups.

## Directive Syntax
There are three supported syntaxs at this moment.

```php
//= only for js
#= only for js
/**
 *= for any
 */
```

## Supported Directives
The directives disponibles are : `require`, `require_directory`, `require_tree` and `depends_on`

### require
Requires a file directly, from the relative path OR one of the base path.
You can also give a directory name, if this directory has a file named "index.$type" (here, "index.css") in.
This directive supports bracket expansion.

### require_directory
Requires each file of the directory. Not recursive.

### require_tree
Recursively requires each file of the directory tree.

### depends_on
Adds the file to the dependencies, even if the file isn't included.
For example, in application.css

```php
//= depends_on image.png
//= depends_on layout
```

If this file change, the whole stylesheet (and the dependencies) will be recompiled
 (this is meant for inlining of some preprocessors).

## Filters
The available filters are :

Languages :
 - .php : [PHP](http://php.net)

JavaScript :
 - .ls : [LiveScript](http://livescript.org)
 - .coffee : [CoffeeScript](http://coffeescript.org) (through [coffeescript-php](github.com/alxlit/coffeescript-php))

Stylesheet :
 - .styl : [Stylus](http://learnboost.github.io/stylus/)
 - .sass .scss : [Sass](http://sass-lang.com/) (through [PHPSass](http://phpsass.com/))
 - .less : [Less](http://lesscss.org) (through [lessphp](http://leafo.net/lessphp/))

Html :
 - .haml : [Haml](http://haml.info) (through [MtHaml](https://github.com/arnaud-lb/MtHaml/), upon which you can build a Twig version, for example)


Adding filter is very easy (to create a `.twig` filter or a `.md`, for example). Just add it to the pipeline :
```php
$pipeline->registerFilter('md', 'My\Markdown\Parser');
```

You must implement an interface like `\Sprockets\Filter\Interface` :
```php
interface Interface
{
	/**
	 * @return string processed $content
	 */
	public function __invoke($content, $file, $dir, $vars);
}
```

You can also inherit `Sprockets\Filter\Base` which gives you access to :
 - `$this->pipeline` current pipeline instance
 - `$this->processNode()` passing an argument array, auto-quoted, like this : `array('modulename/bin/mod', '-c', $file))`
   Note that the first argument gets the `NODE_MODULES_PATH` prepended automatically.