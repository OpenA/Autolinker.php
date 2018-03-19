# Autolinker.php
(**PHP >= 5.3**) implementation of [Autolinker.js](https://github.com/gregjacobs/Autolinker.js/) a usefull javascript library for extract urls from text string.

Also, it will be possible ease porting back to ES6.

## Usage
Using the static link() method:
```php
$linkedText = Autolinker::quickLink( $textToAutolink[, $options] );
```
Using as a class:
```php
$autolinker = new Autolinker( [ $options ] );

$linkedText = autolinker->link( $textToAutoLink );
```

## Options
Use the named array keys (equal js-object) for the `$options` argument:

```php
$options = [
   'newWindow'   => false,
   'stripPrefix' => false
];
```
Take params from [here](https://github.com/gregjacobs/Autolinker.js/#options)
