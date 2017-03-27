#### GeckoPackages

# BSON file iterator

Iterator for [BSON](https://en.wikipedia.org/wiki/BSON) files, for example as produced by [mongodump](https://docs.mongodb.com/manual/reference/program/mongodump/).
The iterator can return the values it reads from the file by converting those into:
- *string*s with JSON encoded data
- associative *array*s
- *\stdClass* objects

### Requirements

PHP 7<br/>
This package is framework agnostic.

### Install

The package can be installed using [Composer](https://getcomposer.org/).
Add the package to your `composer.json`.

```json
"require": {
    "gecko-packages/gecko-bson-file-iterator" : "^v1.0"
}
```

### Usage

#### Example

```php
use GeckoPackages\Bson\BsonFileIterator;

$file = '/path/to/someFile.bson';

$iterator = new BsonFileIterator($file, BsonFileIterator::CONSTRUCT_ARRAY);
foreach ($iterator as $key => $value) {
    // $key is the index
    // $value is an array
}
```

#### Constructor

The `BsonFileIterator` constructor has 5 arguments, only the first is required:
- `$file` *string* or *\SplFileInfo*
  to point to the BSON file to be read.
- `$constructType` *int*
  `BsonFileIterator::CONSTRUCT_JSON` (default), `BsonFileIterator::CONSTRUCT_ARRAY` or `BsonFileIterator::CONSTRUCT_STD`.
  Defines the type of values that should be returned: JSON encoded *string*, *array* or *\stdClass*.
- `$maxUnpackSize` *int*
  Default is 5MiB (5242880 bytes), must be > 0. See `Handling of invalid files or values` for details.
- `$jsonDecodeMaxDepth` *int*
  Used when decoding the JSON data to *array*s or *\stdClass* objects.
- `$jsonDecodeOptions` *int*
  Used when decoding the JSON data to *array*s or *\stdClass* objects.

### Handling of invalid files or values

The BsonFileIterator is _not_ a BSON file validator and should not be used for such purposes.
When using the `BsonFileIterator::CONSTRUCT_JSON` type the returned JSON encoded strings are _not_ validated.
When using `BsonFileIterator::CONSTRUCT_ARRAY` or `BsonFileIterator::CONSTRUCT_STD` the iterator will decode the JSON strings using the `$jsonDecodeMaxDepth` and `$jsonDecodeOptions` ([details](https://secure.php.net/manual/en/function.json-decode.php)) options. If the decoding fails it will throw an `\UnexpectedValueException` exception.
When iterating a file different than a valid BSON file the iterator may return unexpected results.
To protect against trying to read more data into memory than is allowed `$maxUnpackSize` is used. Each read (i.e. on each iteration) this value is checked against the read length of the item before the item itself is read. If the length exceeds the max. value the iterator will throw an `\UnexpectedValueException` exception.

*Notes*
The behavior of the iterator is undefined when modifying the file during iteration,
which includes:
- (re)moving the file
- writing to the file
- changing the permissions of the file

When iterating on empty file no values are returned and no exceptions triggered.

### License

The project is released under the MIT license, see the LICENSE file.

### Contributions

Contributions are welcome!<br/>
Visit us on [github :octocat:](https://github.com/GeckoPackages/GeckoBSONFileIterator)

### Semantic Versioning

This project follows [Semantic Versioning](http://semver.org/).

<sub>Kindly note:
We do not keep a backwards compatible promise on code annotated with `@internal`, the tests and tooling (such as document generation) of the project itself
nor the content and/or format of exception/error messages.</sub>

This project is maintained on [github :octocat:](https://github.com/GeckoPackages/GeckoBSONFileIterator)
