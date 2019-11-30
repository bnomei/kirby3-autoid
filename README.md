# Kirby 3 AutoID

![Release](https://flat.badgen.net/packagist/v/bnomei/kirby3-autoid?color=ae81ff)
![Stars](https://flat.badgen.net/packagist/ghs/bnomei/kirby3-autoid?color=272822)
![Downloads](https://flat.badgen.net/packagist/dt/bnomei/kirby3-autoid?color=272822)
![Issues](https://flat.badgen.net/packagist/ghi/bnomei/kirby3-autoid?color=e6db74)
[![Build Status](https://flat.badgen.net/travis/bnomei/kirby3-autoid)](https://travis-ci.com/bnomei/kirby3-autoid)
[![Coverage Status](https://flat.badgen.net/coveralls/c/github/bnomei/kirby3-autoid)](https://coveralls.io/github/bnomei/kirby3-autoid) 
[![Maintainability](https://flat.badgen.net/codeclimate/maintainability/bnomei/kirby3-autoid)](https://codeclimate.com/github/bnomei/kirby3-autoid) 
[![Demo](https://flat.badgen.net/badge/website/examples?color=f92672)](https://kirby3-plugins.bnomei.com/autoid) 
[![Gitter](https://flat.badgen.net/badge/gitter/chat?color=982ab3)](https://gitter.im/bnomei-kirby-3-plugins/community) 
[![Twitter](https://flat.badgen.net/badge/twitter/bnomei?color=66d9ef)](https://twitter.com/bnomei)


Automatic unique ID for Pages and Files including performant helpers to retrieve them. Bonus: Tiny-URL.

1. [Why AutoID](https://github.com/bnomei/kirby3-autoid#why-autoid)
1. [Setup](https://github.com/bnomei/kirby3-autoid#setup)
1. [Usage autoid()](https://github.com/bnomei/kirby3-autoid#usage-autoid)
1. [Usage modified()](https://github.com/bnomei/kirby3-autoid#usage-modified)
1. [Tiny-URL](https://github.com/bnomei/kirby3-autoid#tiny-url)
1. [Settings](https://github.com/bnomei/kirby3-autoid#settings)

## Commercial Usage

This plugin is free (MIT license) but if you use it in a commercial project please consider to
- [make a donation 🍻](https://www.paypal.me/bnomei/10) or
- [buy me ☕☕](https://buymeacoff.ee/bnomei) or
- [buy a Kirby license using this affiliate link](https://a.paddle.com/v2/click/1129/35731?link=1170)

## Installation

- unzip [master.zip](https://github.com/bnomei/kirby3-autoid/archive/master.zip) as folder `site/plugins/kirby3-autoid` or
- `git submodule add https://github.com/bnomei/kirby3-autoid.git site/plugins/kirby3-autoid` or
- `composer require bnomei/kirby3-autoid`

## Why AutoID

Kirby does not have a persistent unique id for Page- and File-Objects, which could be useful in various situations. Using the `$page->id()` will not solve this since it changes when the `$page->slug()` / `$page->url()` changes, Files could get renamed.
What would be needed was a Field defined in Page/File Blueprints where that unique id will be stored and some logic to generate it automatically (using Hooks). Thus the idea of `autoid` was born. 

To sum it up with the AutoID Plugin you can solve problems like these:

- Automatically get an unique id for each Page/File which has the `autoid` Field in its Blueprint.
- Store a reference to a Page/File which does not break if that objects or parents are renamed.
- Get a Page/File quickly using that reference.

## Setup
### Blueprints

Add a Field named `autoid` with type `hidden` to your blueprints. Also set `translate: false` unless you need different autoids for different languages. More examples can be found [here](https://github.com/bnomei/kirby3-autoid/tree/master/blueprints).

```yaml
 content:
  type: fields
  fields:
    text:
      type: textarea
    autoid:             # <-------
      type: hidden
      translate: false      
```

> This Plugin has an optional Field called `autoid` which is a non-translatable and disabled Text-Field. Use it with `type: autoid`.

### Generator
You can set a different Generator or define your own using the `bnomei.autoid.generator` option.

**site/config/config.php**
```php
return [
    'bnomei.autoid.generator' => function () {
        // override with custom callback if needed
        return (new \Bnomei\TokenGenerator())->generate();
        // return (new \Bnomei\IncrementingGenerator(0))->generate();
        // return (new \Bnomei\UUIDGenerator(site()->url()))->generate();
    },
    // ... other options
];
```

## Usage autoid()

**Store a single reference to a File from another Page**

```yaml
download:
  label: File from Download
  type: select
  options: query
  query:
    fetch: page("downloads").files
    text: "{{ file.filename }}"
    value: "{{ file.autoid }}"
```

**Store multiple references to child Pages**

```yaml
special:
  label: Special Child
  type: checkboxes
  options: query
  query:
    fetch: page.children.filterBy("template", "special")
    text: "{{ page.title }}"
    value: "{{ page.autoid }}"
```

**Find Page/File-Object in PHP**

```php
$autoid = 'any-autoid-value';

$result = autoid($autoid); // global helper function
// or
$result = $page->myFieldWithAutoIDReference()->fromAutoID(); // fieldMethod

if(is_a($result, 'Kirby\Cms\Page')) {
    // got a Page
} else if(is_a($result, 'Kirby\Cms\File')) {
    // got a File
}
```

**Create a Page/File programmatically and retrieve autoid**

Right after creating a Page/File programmatically the `$object->autoid()->value()` will be empty since the `page.create:after`/`file.create:after` hook triggered an `update`-hook but the Page/File-Object returned by `createChild()`/`createFile()` can not reflect this yet. But you can use the `autoid()` helper to retrieve the autoid from the database based on the `id` of your Page/File-Object. 

```php
$page = $parent->createChild($yourProps);
// return page.create:after but not [=> autoid => $page->update(...)] 
$willBeEmpty = $page->autoid()->value(); 
// but
$autoid = autoid($page->id());
// or
$autoid = autoid($page);

$file = $page->createFile($yourFileProps);
$willBeEmpty = $file->autoid()->value();
// but
$autoidOfFile = autoid($file->id());
// or
$autoidOfFile = autoid($file);
```

> ATTENTION: This only works in version 2 of this plugin.


## Usage modified()

The `modified()` helper lets you retrieve the modified timestamp from the AutoID database without requiring the file to be checked on disk.

```php
$modified = modified($autoid); // null or int
```

## Tiny-URL

```php
echo $page->url(); // https://devkit.bnomei.com/autoid/test-43422931f00e27337311/test-2efd96419d8ebe1f3230/test-32f6d90bd02babc5cbc3
echo $page->autoid()->value(); // 8j5g64hh
echo $page->tinyurl(); // https://devkit.bnomei.com/x/8j5g64hh
```

## Settings

| bnomei.autoid.            | Default        | Description               |            
|---------------------------|----------------|---------------------------|
| generator | callback | alphanumeric hash value generator (~2.8 trillion possibilites) |
| generator.break | `42` | try max n-times to generate and verify uniqueness of hash |
| tinyurl.url | callback | returning `site()->url()`. Use htaccess on that domain to redirect `RewriteRule (.*) http://www.bnomei.com/x/$1 [R=301]` |
| tinyurl.folder | `x` | Tinyurl format: yourdomain/{folder}/{hash} |

## What's new in Version 2

- New ID-Generators have been added: *Incrementing*, *UUID* and *Token* (default)
- Support for StructureObject has been removed after much consideration of use-cases and solutions. This reduces the code complexity of this plugin and avoids common pitfalls on your end when using StructureObjects instead of Pages. The Blocks of the new [Editor](https://github.com/getkirby/editor) are a good alternative.
- The *Collection Cache* has been removed since the current version of [Lapse plugin](https://github.com/bnomei/kirby3-lapse#objects) provides a cleaner solution.

## Disclaimer

This plugin is provided "as is" with no guarantee. Use it at your own risk and always test it yourself before using it in a production environment. If you find any issues, please [create a new issue](https://github.com/bnomei/kirby3-autoid/issues/new).

## License

[MIT](https://opensource.org/licenses/MIT)

It is discouraged to use this plugin in any project that promotes racism, sexism, homophobia, animal abuse, violence or any other form of hate speech.

## Credits

inspired by the following Kirby 2 Plugins:

- https://github.com/texnixe/kirby-structure-id
- https://github.com/helllicht/kirby-autoid
- K2 core tinyurl implementation
