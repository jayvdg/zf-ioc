#ZF-IoC   

```shell
composer require jeroenvandergeer/zf-ioc
```

```php
// Container of choice, can be any Laravel compatible container
$container = new \Illuminate\Container\Container();

// Optionally register the container with the Zend registry for global binding
\Zend_Registry::set('container', $container);

// Register the container with the front controller
$frontController = \Zend_Controller_Front::getInstance();
$frontController->setParam('container', $container);

// Replace the dispatcher with the IoC implementation
$frontController->setDispatcher(new \Jeroenvandergeer\ZfIoc\Dispatcher());

// Register binding
$container->bind('\App\FooInterface', function($container){
    return new \App\Foo($container['\App\Bar']);
});
```

## Example #1
```php
public function indexAction(\App\FooInterface $foo) 
{
    var_dump($foo);    
}
```

```
object(App\Foo)
  public 'bar' => 
    object(App\Bar)
```

## Example #2
```php
public function indexAction() 
{
    $container = $this->getInvokeArg('container');
    var_dump($container->make('\App\Foo'));
}
```

```
object(App\Foo)
  public 'bar' => 
    object(App\Bar)
```
