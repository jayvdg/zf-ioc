#ZF-IoC   

Controller action dependency injection in Zend Framework 1.

Does not have the framework as a composer dependency to support legacy projects (as that is the only reason this package exists).
 
Currently requires PHP 5.4+ (as required by illuminate/container 5.0)

```shell
composer require jeroenvandergeer/zf-ioc
```

```php
// Container of choice, can be any Laravel compatible container
$container = new \Illuminate\Container\Container();

// Build dispatcher with IoC container
$dispatcher = new \Jeroenvandergeer\ZfIoc\Dispatcher($container);

// Set / replace the dispatcher
$frontController = \Zend_Controller_Front::getInstance();
$frontController->setDispatcher($dispatcher);

// Optionally register the container with the Zend registry for global binding
\Zend_Registry::set('container', $container);

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
