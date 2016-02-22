# Slim-Simple-MVC

This framework is based on the "Slim-Framework", adds a simple MVC implementation.

Routing
--------

- Top level routing

"config/route.json"
```json
{
  '/': 'page' ,
  '/hoge': 'hoge'
}
```

- nested routing at PageController

"app/Controller/Page.php"
```php
<?php
namespace SlimMVC\Controller;

class Page extends \SlimMVC\Controller
{
  protected $route = array(
    '(/)' => 'index' ,
    '/login(/)' => 'login',
  );
  
  public function index()
  {
    // do something for http://[BASE_URL]/
  }
  
  public function login()
  {
    // do something for http://[BASE_URL]/login
  }
}
```

- nested routing at HogeController

"app/Controller/Hoge.php"
```php
<?php
namespace SlimMVC\Controller;

class Hoge extends \SlimMVC\Controller
{
  protected $route = array(
    '(/)' => 'index',
    '/show(/:id)(/)' => 'show',
  );
  
  public function index()
  {
    // do something for http://[BASE_URL]/hoge/
  }
  
  public function show($id = null)
  {
    // do something for http://[BASE_URL]/hoge/show  or  http://[BASE_URL]/hoge/show/1 ("1" set to $id)
  }
}
```

View
----

- using the "Twig" as View template.
- ex) Contoller\Page::index()
  - will use "View/page/index.html" as view template file.

"app/View/page/index.html"
```html
{% extends layout %}

{% block title %}Page title{% endblock %}

{% block contents %}
<h1> Page title </h1>
<p>Page contents</p>
{% endblock %}
```

- Inherited layout file

"app/View/layout/application.html"
```html
<!DOCTYPE html>
<html>
<head>
<title>{% block title %}{% endblock %}</title>
</head>
<body>
<div id="header">Headers</div>
<div id="contents">
{% block contents %}{% endblock %}
</div>
</body>
</html>
```

View Helpers
-------------

- Autoloading view helpers (as Twig-extension).
- ex) ControllerAction: Page::index()
  - will load helpers "app/Helper/Application.php" and "app/Helper/Page.php"

"app/Helper/Page.php"
```php
<?php
namespace SlimMVC\Helper;

class Page extends \SlimMVC\Helper
{
  protected $_name = "page";
  protected $_functions = array(
    'getHoge' => 'hoge'
  );
  protected $_filters = array(
    'getHoge' => 'h'
  );
  
  public static function getHoge($str)
  {
    return 'hoge' . $str;
  }
}
```

- How to use helper function or filter.

"app/View/page/index.html"
```html
{{ hoge("Test") }}  // as a function.
{{ "Test"|h }} // as a filter.
```

Model
------

- Database settings

"config/database.json"

```json
{
  "development": {
    "default": {
      "type": "mysql",
      "host": "localhost",
      "port": 3306,
      "name": "dbname",
      "user": "root",
      "pass": "password",
      "logging": true
    }
  },
  
  "production": {
    // for production settings.
  },
  
  "test": {
    // for test settings.
  }
}
```

- ex) "users" table

```sql
CREATE TABLE `dbname`.`users` (
  `id` integer AUTO INCREMENT PRIMARY KEY ,
  `name` varchar(32) NOT NULL,
  `account` varchar(32) NOT NULL,
  `password` char(40) NOT NULL,
  `created_at` DATETIME,
  `updated_at` DATETIME
);
```

- Model for "users" table

"app/Model/User.php"
```php
<?php
namespace SlimMVC\Model;

class User extends \SlimMVC\Model
{
  public static $_table = "users";
  protected $_fields = array(
    'id', 'name', 'account', 'password', 'created_at', 'updated_at'
  );
  
  public $somevar;
}
```

- use Model in Controller

"app/Controller/Page.php"
```php
<?php

// CREATE
$User = model('User')->set(array(
  'name' => 'John Doe',
  'account' => 'john@example.com',
  'password' => sha1('my-password')
));

if ($User->save()) // INSERT INTO users (name, account, password) SET ("John Doe", "john@exmple.com", "******");
{
  // It's OK.
}


// SELECT or UPDATE
$User = orm('User')->findOne(1);  // SELECT * FROM users WHERE id = 1;
if ($User->set('name', 'Jane Roe')->save()) // UPDATE users SET name = "Jane Roe" WHERE id = 1;
{
  // It's OK.
}
else
{
  print_r($User->getErrors());
}
```

Validating, Filtering and more
----------------------------------

"app/Model/User.php"
```php
<?php

class User extends \SlimMVC\Model
{
  public function validate()
  {
    $this->validatesPrecenseOf(array('name', 'account', 'password'));
    $this->validatesLengthOf('name', 1, 32);
    $this->validatesFormatOf('password', '/^[a-f\d]{40}$/');
  }
  
  public function beforeSave()
  {
    $this->updated_at = date('Y-m-d H:i:s');
    return parent::beforeSave(); // true
  }
  
  public function beforeCreate()
  {
    $this->created_at = date('Y-m-d H:i:s');
    return parent::beforeCreate();
  }
  
  public function afterDelete()
  {
    // After user deleted, call Hoge::onUserDeleted(), Fuga::onUserDeleted()
    return $this->notify('deleted', array(
      'Hoge',
      'Fuga'
    )) && parent::afterDelete();
  }
}
```

"app/Model/Hoge.php"
```php
<?php

class Hoge extends \SlimMVC\Model
{
  // snip
  
  protected static function onUserDeleted(\SlimMVC\Model\User $User)
  {
    return orm('Hoge')->where('user_id', $User->id)->delete_many();
  }
}
```


Translate
------------

If you want to support multiple languages ​​in your application

- 
"app/View/page/index.html"
```html

<h1>My Page</h1>  // Not multi-languages.

<h1>{{ 'My Page'|t }}</h1> // supported multi-languages.

```

- Language file

"config/locales/ja_JP.php"
```php
<?php
return array(
  'My Page' => '私のページ',
);
```

- Locale setting

"config/config.php"
```php
<?php

define('APP_LOCALE', 'ja_JP'); // default "en_US".
```
