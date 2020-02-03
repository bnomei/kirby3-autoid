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


Automatic unique ID for Pages, Files and nested Structures including performant helpers to retrieve them. Bonus: Tiny-URL.

1. [Why AutoID](https://github.com/bnomei/kirby3-autoid#why-autoid)
1. [Setup](https://github.com/bnomei/kirby3-autoid#setup)
1. [Usage autoid()](https://github.com/bnomei/kirby3-autoid#usage-autoid)
1. [Usage modified()](https://github.com/bnomei/kirby3-autoid#usage-modified)
1. [Tiny-URL](https://github.com/bnomei/kirby3-autoid#tiny-url)
1. [Settings](https://github.com/bnomei/kirby3-autoid#settings)
1. [Changelog](https://github.com/bnomei/kirby3-autoid#changelog)

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

**Structures**

To keep the AutoID value unique you must make the structure non-translatable.

```yaml
 content:
  type: fields
  fields:
    mystructure:
      type: structure
      translate: false      # <-------     
      fields:
        text:
          type: textarea
        autoid:             # <-------
          type: hidden
          translate: false 
```

### Generator
You can set a different Generator or define your own using the `bnomei.autoid.generator` option.

**site/config/config.php**
```php
return [
    'bnomei.autoid.generator' => function () {
        // override with custom callback if needed
        return (new \Bnomei\TokenGenerator())->generate();
        // return (new \Bnomei\IncrementingGenerator(0))->generate();
        // return (new \Bnomei\NanoGenerator())->generate();
        // return (new \Bnomei\UUIDGenerator(site()->url()))->generate();
    },
    // ... other options
];
```

**Get a new AutoID value not in use yet**
```php
$autoid = \Bnomei\AutoID::generate(); // null | string
// or
$autoid = autoid();
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

**Store multiple references to StructureObjects from a different Field from another Page**

**Page 'a'**
```yaml
categories:
  label: Define Categories
  type: structure
  translate: false
  fields:
    title:
      type: text
    autoid:
      type: hidden
      translate: false
```

**Page 'b'**
```yaml
category:
  label: Select Categories
  type: checkboxes
  options: query
  query:
    fetch: page('a').categories.toStructure
    text: "{{ structureItem.title }}"
    value: "{{ structureItem.autoid }}"
```

> TIP: This works from structures defined in the site blueprint as well (since v2.2.0).

**Find Page/File-Object in PHP**

```php
$autoid = 'any-autoid-value';

$result = autoid($autoid); // global helper function
// or
$result = $page->myFieldWithAutoIDReference()->fromAutoID(); // fieldMethod

if(is_a($result, 'Kirby\Cms\Page')) {
    // got a Page
} elseif(is_a($result, 'Kirby\Cms\File')) {
    // got a File
} elseif(is_a($result, 'Kirby\Cms\StructureObject')) {
    // got a StructureObject
    // $result->myFieldname()
    // $result->id: $autoid
    // $result->parent: Site|Page-Object hosting the Structure
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

The `modified()` helper lets you retrieve the modified timestamp from the AutoID database without requiring the file to be checked on disk. It even works for Collections of Pages/Files that have Objects with and without an `autoid`.

```php
// string
$modified = modified($autoid); // null or int
// array of strings
$modified = modified([$autoid1, $autoid2, $autoid3]); // null or int
```

Kirby will retrieve the modified timestamp for all files in a collection in reading them from the disk, but the `modified()` helper is still a nice way to get the max value easily. 
```php
// collection object
$modified = modified(site()->pages()->index()); // null or int
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
