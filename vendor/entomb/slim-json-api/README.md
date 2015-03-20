#slim-jsonAPI
[![Latest Stable Version](https://poser.pugx.org/entomb/slim-json-api/v/stable.png)](https://packagist.org/packages/entomb/slim-json-api)
[![Total Downloads](https://poser.pugx.org/entomb/slim-json-api/downloads.png)](https://packagist.org/packages/entomb/slim-json-api)
[![Bitdeli Badge](https://d2weczhvl823v0.cloudfront.net/entomb/slim-json-api/trend.png)](https://bitdeli.com/free "Bitdeli Badge")

This is an extension to the [SLIM framework](https://github.com/codeguy/Slim) to implement json API's with great ease.

##Installation
Using composer you can add use this as your composer.json

```json
    {
        "require": {
            "slim/slim": "2.3.*",
            "entomb/slim-json-api": "dev-master"
        }
    }

```

##Usage
To include the middleware and view you just have to load them using the default _Slim_ way.
Read more about Slim Here (https://github.com/codeguy/Slim#getting-started)

```php
    require 'vendor/autoload.php';

    $app = new \Slim\Slim();

    $app->view(new \JsonApiView());
    $app->add(new \JsonApiMiddleware());
```

###.htaccess sample
Here's an .htaccess sample for simple RESTful API's
```
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [QSA,L]
```

###example method
all your requests will be returning a JSON output.
the usage will be `$app->render( (int)$HTTP_CODE, (array)$DATA);`

####example Code 
```php

    $app->get('/', function() use ($app) {
        $app->render(200,array(
                'msg' => 'welcome to my API!',
            ));
    });

```


####example output
```json
{
    "msg":"welcome to my API!",
    "error":false,
    "status":200
}

```

##Errors
To display an error just set the `error => true` in your data array.
All requests will have an `error` param that defaults to false.

```php

    $app->get('/user/:id', function($id) use ($app) {

        //your code here

        $app->render(404,array(
                'error' => TRUE,
                'msg'   => 'user not found',
            ));
    });

```
```json
{
    "msg":"user not found",
    "error":true,
    "status":404
}

```

You can optionally throw exceptions, the middleware will catch all exceptions and display error mensages.

```php

    $app->get('/user/:id', function($id) use ($app) {

        //your code here

        if(...){
            throw new Exception("Something wrong with your request!");
        }
    });

```
```json
{
    "error": true,
    "msg": "ERROR: Something wrong with your request!",
    "status": 500
}

```


##routing specific requests to the API
If your site is using regular HTML responses and you just want to expose an API point on a specific route,
you can use Slim router middlewares to define this.

```php
    function APIrequest(){
        $app = \Slim\Slim::getInstance();
        $app->view(new \JsonApiView());
        $app->add(new \JsonApiMiddleware());
    }


    $app->get('/home',function() use($app){
        //regular html response
        $app->render("template.tpl");
    });

    $app->get('/api','APIrequest',function() use($app){
        //this request will have full json responses

        $app->render(200,array(
                'msg' => 'welcome to my API!',
            ));
    });
```


##middleware
The middleware will set some static routes for default requests.
**if you dont want to use it**, you can copy its content code into your bootstrap file.

***IMPORTANT: remember to use `$app->config('debug', false);` or errors will still be printed in HTML***
