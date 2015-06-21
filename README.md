#ZF-IoC   

```php
// Container of choice, can be any Laravel compatible container
$container = new \Illuminate\Container\Container();

// Register the container with the Zend registry
\Zend_Registry::set('container', $container);

// Register the container with the front controller
$frontController = \Zend_Controller_Front::getInstance();
$frontController->setParam('container', $container);

// Replace the dispatcher with the IoC implementation
$frontController->setDispatcher(new \Jeroenvandergeer\ZfIoc\Dispatcher());

// Register binding
$container->bind('FooInterface', function($container){
    return new Foo($container['Bar']);
});
```