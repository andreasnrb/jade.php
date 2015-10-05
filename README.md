# Jade.php
Jade.php adds inline PHP scripting support to the [Jade](http://jade-lang.com) template compiler.

## Implementation details
The fork is a complete rewrite, all the code is ported from the original jade project.

All the features from the original are supported but undertested, including inheritance
and mixins.

## Syntax
See [original Jade Docs](https://github.com/visionmedia/jade#readme)

## Example
``` php
<?php
namespace Jade;
require __DIR__ . '/vendor/autoload.php';
$jade = new Jade('/tmp', true);
echo $jade->render('index.jade');
```

## Tests


## Notes
Please check the git commit history for the authoritative list of contributors.
