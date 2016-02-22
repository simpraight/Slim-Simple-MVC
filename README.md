# Slim-Simple-MVC

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

- Page Controller

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

- Hoge Controller

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

- using Twig
- ex) Contoller\Page::index()
  - it will use view template file "View/page/index.html"

"app/View/page/index.html"
```html
{% extends layout %}

{% block title %}Page title{% endblock %}

{% block contents %}
<h1> Page title </h1>
<p>Page contents</p>
{% endblock %}
```

- Layout file extended

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
