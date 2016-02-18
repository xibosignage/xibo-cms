# Custom Modules
This folder is provided as a reasonable place to copy/develop custom modules. This folder is auto-loaded based on the 
`Xibo\Custom` namespace.

This folder is also monitored by the Modules Page for `.json` files describing modules available to be installed, the 
structure of such a file is:

``` json
{
  "title": "Module Title",
  "author": "Module Author",
  "description": "Module Description",
  "name": "code-name",
  "class": "Xibo\\Custom\\ClassName"
}
```

The module class must `extend Xibo\Widget\ModuleWidget` and implement the installOrUpdate method.

We recommend that modules put their Twig Views in a sub-folder of this one, named as their module name. This should be
set in `installOrUpdate` like `$module->viewPath = '../custom/{name}';`.


## Web Accessible Resources
All web accessible resources must placed in the `/web/modules` folder and be installed to the library in `installFiles`.


# Theme Views
This location can also be used for theme views - we recommend a sub-folder for each theme. The theme `config.php` file
should set its `view_path` to `PROJECT_ROOT . '/custom/folder-name`.