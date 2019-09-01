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


Automatic unique ID for Pages, StructureObjects and Files including performant helpers to retrieve them. Bonus: Cache for collections and Tiny-URL.

1. [Why AutoID](https://github.com/bnomei/kirby3-autoid#why-autoid)
2. [Setup](https://github.com/bnomei/kirby3-autoid#setup)
3. [Usage autoid()](https://github.com/bnomei/kirby3-autoid#usage-autoid)
4. [Why modified()](https://github.com/bnomei/kirby3-autoid#why-modified)
5. [Usage modified()](https://github.com/bnomei/kirby3-autoid#usage-modified)
6. [Usage modifiedHash()](https://github.com/bnomei/kirby3-autoid#usage-modifiedhash)
7. [Tiny-URL](https://github.com/bnomei/kirby3-autoid#tiny-url)
8. [Settings](https://github.com/bnomei/kirby3-autoid#settings)

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

Kirby does not have a persistent unique id for Page- and File-Objects, which could be usefull in various situations. Using the `$page->id()` will not solve this since it changes when the `$page->slug()` / `$page->url()` changes, Files could get renamed. During K2s livespan web-developers hit the situation that an unique id for entries in Structures would be very helpfull as well since Structures can be used like simplified versions of Pages but changing the sort order them would mess it up.
What would be needed was a Field defined in Page/File Blueprints where that unique id will be stored and some logic to generate it automatically (using Hooks). Thus the idea of `autoid` was born. 

To sum it up with AutoID you can solve problems like these:

- Automatically get an unique id for each Page/File/StructureObject which has the autoid Field in its Blueprint.
- Store a reference to a Page/File/StructureObject which does not break if that objects or parents are renamed.
- Get a Page/File/StructureObject quickly using that reference.

## Setup

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
    anystructure:
      type: structure
      translate: false
      fields:
        text:
          type: text
        autoid:         # <-------
          type: hidden
          
```

> This Plugin has an optional Field called `autoid` which is a non-translatable and disabled Text-Field. Use it with `type: autoid`.

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

**Find single Object in PHP**

```php
$autoid = 'any-autoid-value';

$result = $kirby->pages()->autoid($autoid); // pagesMethod
// or
$result = autoid($autoid); // global helper function
// or
$result = $page->myFieldWithAutoIDReference()->fromAutoID(); // fieldMethod
// or
$result = Bnomei\AutoID::find($autoid); // static class method

dump($result);

if(is_a($result, 'Kirby\Cms\Page')) {
    // got a page
} else if(is_a($result, 'Kirby\Cms\StructureObject')) {
    // got a StructureObject
} else if(is_a($result, 'Kirby\Cms\File')) {
    // got a File
}
```

**Working with multiple Objects in PHP**

```php
// get collection
foreach(autoid() as $a) { dump($a); } // global helper function
// or
foreach(Bnomei\AutoID::collection() as $a) { dump($a); } // static class method

// do stuff with the collection

// like getting a random autoid for a file
$anyAutoID = autoid()->filterBy('type', 'file')->shuffle()->first()[Bnomei\AutoID::AUTOID]; 
// Bnomei\AutoID::AUTOID = 'autoid'

// get that file and print its url
$anyFile = $site->pages()->autoid($anyAutoID); // using pagesMethod for example
dump($anyFile->url());

// get array (max performance)
foreach(Bnomei\AutoID::array() as $a) { dump($a); }
```

## Why modified()

When coding Templates with Kirby you usually end up using its Collections. They can be filtered, sorted, queried, cloned and more. You can use the built in Page Cache to cache the final HTML-output to reduce the CPU time spend on the Template. But when editing a Page the **complete** cache will be flushed - everything, not just for that page. Why? Because its content might appear somewhere else as well.

In Kirby 3 you can now exclude pages from the cache which is great since some Pages might have content from external APIs or change very often. But now every time you call a collection on an exluded Page the CPU time will be spend again and again. When you call a collection Kirby does a lot of indexing in the background. That does not sound much but if you have a website with more than a few hundred Pages that could take a few hundred milliseconds and make your website seem slow.

So how to solve that? In caching what takes up the most CPU time using Collections – building the collections in the first place. This is where the `modified()` helper comes into play. I does just that.

- Check if a cache for a Collecion exists.
- Check if that cache needs refresh since any of its Page/File objects were modified. This is all done with the autoid-lookup-table cutting short the checks for modification to almost none. A `$page->modified()` call would be way to inefficient here.
- If the Collection needs refresh or did not exist then build the Collection, store the autoid of each object and the Collection index in a seperate cache.
- Otherwise return the Collection. That is almost instant since the modification check got it from the cache allready.

> Note: Collections are not only problem here. Use your own judgment and messasure time spend on calls. You can do that with plain `round(microtime(true) * 1000)` or tools like xdebug and [webgrind](https://github.com/jokkedk/webgrind).

> ATTENTION: Please be aware the the `modified()` helper [does not notice a change in collection object count](https://github.com/bnomei/kirby3-autoid/issues/2) yet. I am still working on this. This is currently solved by a very short expiration time.

## Usage modified()

```php
// setup a semi-unique id for this group
$collectionID = "page('autoid')->children()->visible()";

// get cached collection, returns null if modified
$collection = modified($collectionID);

// if does not exist yet or was modified 
if(!$collection) {
  $collection = modified($collectionID, page('autoid')->children()->visible());
  echo '=> Collection Cache: created or refreshed because modified.'.PHP_EOL;
} else {
  echo '=> Collection Cache: read.'.PHP_EOL;
}

foreach($collection as $p) {
  dump($p->url());
}
```

## Usage modifiedHash()

If you are using some kind of [caching like my lapse plugin](https://github.com/bnomei/kirby3-lapse) you need a way to check if the collection changed. You can use the `modifiedHash()` helper to do that. It returns a unified hash for modified values using the almost zero-cpu-cost lookup-table of autoid.

```php
// continuing example from above...
$collectionHash = modifiedHash($collectionID);

$data = lapse(md5($page->id().$page->modified().$collectionHash), function () use ($site, $page, $kirby, $collection) {
    // do something with $page and $collection.
    // but if any are modified this cache is cleared since the id for lapse() based on the modified hashes changed.
}
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
| generator.break | `42` | try n-times to generate and verify uniqueness of hash |
| index.pages | `true` | |
| index.structures | `true` | |
| index.files | `true` | |
| index | callback | returning `kirby()->site()->pages()->index()` |
| tinyurl.url | callback | returning `site()->url()`. Use htaccess on that domain to redirect `RewriteRule (.*) http://www.bnomei.com/x/$1 [R=301]` |
| tinyurl.folder | `x` | Tinyurl format: yourdomain/{folder}/{hash} |
| log.enabled | `false` | |
| log | callback | with `kirbyLog()` |
| modified => expire | `30` | in seconds |

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
