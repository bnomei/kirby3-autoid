# Kirby 3 AutoID

Performant index of Pages, StructureObjects and Files.

## Usage

```php
$autoid = 'any-autoid-value';
$result = $kirby->pages()->autoid($autoid);
// or
$result = Bnomei\AutoID::find($autoid);
// or
$result = autoid($autoid);

if(is_a($result, 'Kirby\Cms\Page')) {
    // got a page
} else if(is_a($result, 'Kirby\Cms\StructureObject')) {
    // got a StructureObject
} else if(is_a($result, 'Kirby\Cms\File')) {
    // got a File
}
```

## Settings

**generator**
- default: alphanumeric hash value generator (~2.8 trillion possibilites)

**generator.break**
- default: try `42` times to generate and verify uniqueness oh hash

**index.pages**
- default: `true`

**index.structures**
- default: `true`

**index.files**
- default: `true`


## Disclaimer

This plugin is provided "as is" with no guarantee. Use it at your own risk and always test it yourself before using it in a production environment. If you find any issues, please [create a new issue](https://github.com/bnomei/kirby3-autoid/issues/new).

## License

[MIT](https://opensource.org/licenses/MIT)

It is discouraged to use this plugin in any project that promotes racism, sexism, homophobia, animal abuse, violence or any other form of hate speech.

## Credits

based on V2 versions of
- https://github.com/texnixe/kirby-structure-id
- https://github.com/helllicht/kirby-autoid
