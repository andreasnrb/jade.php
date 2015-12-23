# Jade.php
Jade.php adds inline PHP scripting support to the [Jade](http://jade-lang.com) template compiler.

## Implementation details
The fork is a complete rewrite, all the code is ported from the original jade project.

All the features from the original are supported but undertested, including inheritance
and mixins.

## Syntax
See [original Jade Docs](https://github.com/visionmedia/jade#readme).

## Examples

### Render
``` php
namespace Jade;
require __DIR__ . '/vendor/autoload.php';
$jade = new Jade('/tmp', true);
echo $jade->render('index.jade');
```

### Cache with Variables
``` php
namespace Jade;
require __DIR__ . '/vendor/autoload.php';
$jade = new Jade('/tmp', true);
$title = "Hello World";
$header = "this is append";
require $jade->cache('index.jade');
```

### Try out the Example in this Repository
```
git clone https://github.com/JumpLink/jade.php.git
cd jade.php/example
php -S localhost:8000
xdg-open http://localhost:8000/main.php
xdg-open http://localhost:8000/variables.php
```

## Tests
Note: Tests need to be fixed!

```
git clone https://github.com/JumpLink/jade.php.git
cd jade.php
composer install
php vendor/bin/phpunit src/tests/EachTest.php
```

## Notes
Please check the git commit history for the authoritative list of contributors.
