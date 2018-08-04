<?php

namespace Bnomei;

class AutoID {

    private static $fieldname = 'autoid';
    private static $index = null;

    private static $cache = null;
    private static function cache(): \Kirby\Cache\Cache {
        if(!static::$cache) {
            static::$cache = kirby()->cache('bnomei.autoid');
        }
        // create new index table on new version of plugin
        if(!static::$index) {
            static::$index = 'index'.str_replace('.','', kirby()->plugin('bnomei/autoid')->version());
        }
        return static::$cache;
    }

    private static function rebuild(): int {
        if($c = static::cache()) {
            $c->flush();
        }
        $indexed = 0;
        $commits = [];
        // NOTE: this operation will be very slow if index grows
        foreach(kirby()->pages()->index() as $page) {
            $newCommits = static::index($page);
            $indexed += count($newCommits);
            $commits = array_merge($commits, $newCommits);
        }
        // update cache in one push to improve performance
        // if cache is filebased
        static::push($commits);
        return $indexed;
    }

    private static function push($batch) {
        $index = static::cache()->get(static::$index);
        if(!$index) {
            $index = [];
        }
        $index = array_merge($index, $batch);
        return static::cache()->set(static::$index, $index);
    }

    private static function commit(
        array $wrapper, 
        string $autoid, 
        string $pageId, 
        string $structureFieldname = null, 
        string $filename = null
    ): bool {
        
        $wrapper[$autoid] = [
            'page.id' => $pageId,
            'page.structure' => $structureFieldname,
            'page.filename' => $filename,
        ];
        return $wrapper;
    }

    private static function remove($autoid) {
        $index = static::cache()->get(static::$index);
        if($index && is_array($index) && \Kirby\Toolkit\A::get($index, $autoid())) {
            unset($index, $autoid);
            return static::cache()->set(static::$index, $index);
        }
        return false;
    }

    private static function index(\Kirby\Cms\Page $page, array $commits = []): int {
        $update = [];

        foreach($page->blueprint()->fields() as $field) {
            if($field->key() == static::$fieldname) {
                if($field->isEmpty()) {
                    $autoid = static::generator();
                    $update[] = [
                        static::$fieldname => $autoid
                    ];
                    $commits = static::commit($commit, $autoid, $page->id());
                } else {
                    $commits = static::commit($commit, $field->value(), $page->id());
                }
                
            } else {
                foreach($field->toStructure() as $structureField) {
                    // TODO: is support for nested structures needed?
                    if($structureField->key() == static::$fieldname) {
                        // TODO: check is is empty or not

                        // TODO: update structure

                        // TODO: add commit
                        /*
                        $commits = static::commit($commit, $structureField->value(), $page->id(), $field->key());
                        */
                    }
                }
            }
        }

        // TODO: loop through each File of page and check blueprint and field
        foreach($page->files() as $file) {
            /*
            $commits = static::commit($commit, $autoid, $page->id(), null, $file->name()); // TODO: name or filename?
            */
        }
        

        try {
            $page->update($update);
        } catch(Exception $e) {
            // echo $e->getMessage();
            // TODO: throw exception again?
            
        }
        return $commits;
    }

    public static function generator(string $seed = null): string {
        $generator = kirby()->option('bnomei.autoid.generator');
        if($generator && is_callable($generator)) {
            return (string) $generator($seed);
        } 
        // else {
        //     // TODO: might throw exception if failed?
        // }
        return static::defaultGenerator($seed);
    }

    public static function defaultGenerator(string $seed = null): string  {
        return (string) \time(); // TODO: proper generator
    }

    public static function addPage(\Kirby\Cms\Page $page) {
        static::push(static::index($page));
    }

    public static function removePage(\Kirby\Cms\Page $page) {
        $field = $page->${static::$fieldname};
        if($field->isNotEmpty()) {
            remove($field->value());
        }
    }

    public static function addFile(\Kirby\Cms\File $file) {
        static::push(static::index($page));
    }

    public static function removeFile(\Kirby\Cms\File $file) {
        // TODO: untested
        $field = $file->${static::$fieldname};
        if($field->isNotEmpty()) {
            remove($field->value());
        }
    }
}