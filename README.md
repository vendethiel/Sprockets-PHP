# Sprockets-PHP

This is a port of Sprockets (Rails Asset Pipeline) for PHP.

You have to create an instance of Asset\Pipeline.
The argument is the array of "base paths" from where the Pipeline has to search files.

If you want to call directly the Pipeline, you can then do `$pipeline($asset_type)`.
For example `$pipeline('css');`.
The CMS will load `application.css` in one of the base paths.
This file must contain "directives", like Sprockets's one.
## Directives Syntax
There are two supported syntaxs at this moment : `//=` and `#=`.
## Supported Directives
The directives disponibles are : `require`, `require_directory`, `require_tree` and `depends_on`
### require
Requires a file directly, from the relative path OR one of the base path.
You can also give a directory name, if this directory has a file named "index.$type" (here, "index.css") in.
### require_directory
Requires each file of the directory. Not recursive.
### require_tree
Recursively requires each file of the directory tree.
### depends_on
Adds the file to the dependencies, even if the file isn't included.
For example, in application.css
```
//= depends_on image.png
//= depends_on layout
```
If this file change, the whole stylesheet (and the dependencies) will be reloaded.
## Filters
Filters are not supposed at this moment.
The filters list to be included is : .sass, .scss, .coffee, .less
Filters are gonna be PHP parsers and will not use backticks, thus allowing to be deployed anywhere.