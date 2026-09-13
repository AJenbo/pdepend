# Upgrading PDepend

## From 2.x to 3.0

PDepend 3.0 is the first major release since 2.0 (2014). It drops every PHP
version below 8.1, adds native types to the whole public API, parses source
files in parallel and moves the configuration file to YAML.

Most changes affect people who embed PDepend as a library (custom analyzers,
visitors, report generators — PHPMD being the best known consumer). For plain
command line use the upgrade is mostly a matter of converting `pdepend.xml` to
`pdepend.yml`.

### Contents

- [Requirements](#requirements)
- [Command line](#command-line)
  - [Configuration file](#configuration-file)
  - [Parallel parsing](#parallel-parsing)
  - [Cache location](#cache-location)
  - [Removed options](#removed-options)
- [Library API](#library-api)
  - [Native types on every interface](#native-types-on-every-interface)
  - [`getName()` is gone, use `getImage()`](#getname-is-gone-use-getimage)
  - [The visitor pattern is inverted](#the-visitor-pattern-is-inverted)
  - [`ProcessListener` signatures](#processlistener-signatures)
  - [The parser is sealed](#the-parser-is-sealed)
  - [Metric value types](#metric-value-types)
  - [Removed classes and methods](#removed-classes-and-methods)
  - [Package layout](#package-layout)

---

### Requirements

|                    | 2.x                              | 3.0                             |
| ------------------ | -------------------------------- | ------------------------------- |
| PHP                | >= 5.3.7                         | >= 8.1 (tested through 8.5)     |
| Architecture       | 32 and 64 bit                    | 64 bit only (`php-64bit`)       |
| Symfony components | 2.3 – 7                          | 5.4, 6, 7 and 8                 |
| ext-sockets        | not used                         | required for parallel parsing on Windows |

The 64 bit requirement comes from NPath complexity, which is now computed with
native integers instead of the bcmath/`MathUtil` fallback.

### Command line

#### Configuration file

The configuration file is now YAML by default. PDepend looks for these files in
the current working directory, in order, and uses the first one it finds:

1. the file passed to `--configuration=<file>`
2. `pdepend.yml`
3. `pdepend.yml.dist`
4. `pdepend.php`
5. `pdepend.xml`
6. `pdepend.xml.dist`

The loader is picked from the file extension, so XML configurations still work —
**but only up to Symfony 7**. Symfony 8 removed `XmlFileLoader`, and PDepend then
fails with:

```
XML config is not supported when using Symfony 8+.
```

Convert your configuration now, even if you are not on Symfony 8 yet. A
`pdepend.xml` like:

```xml
<?xml version="1.0"?>
<symfony:container xmlns:symfony="http://symfony.com/schema/dic/services"
    xmlns="http://pdepend.org/schema/dic/pdepend">
    <config>
        <image-convert>
            <font-family>Arial</font-family>
            <font-size>12</font-size>
        </image-convert>
        <cache>
            <driver>file</driver>
            <location>/tmp/pdepend</location>
            <ttl>2592000</ttl>
        </cache>
        <parser>
            <nesting>65536</nesting>
        </parser>
    </config>
</symfony:container>
```

becomes:

```yaml
pdepend:
  image-convert:
    font-family: Arial
    font-size: 12
  cache:
    driver: file
    location: /tmp/pdepend
    ttl: 2592000
  parser:
    nesting: 65536
```

The setting names are unchanged; two cache defaults changed, see
[Cache location](#cache-location). The shipped sample file is now
`pdepend.yml.dist` instead of `pdepend.xml.dist`.

#### Parallel parsing

PDepend now parses files in parallel worker processes. This is enabled by
default when running through `bin/pdepend` and uses every core it detects.

```
--threads=<value>    Number of threads to use for parsing.
```

Use `--threads=1` to get the 2.x single-process behaviour back. Parallel parsing
is skipped automatically when there is only one file to parse, and on Windows
when `ext-sockets` is not loaded.

`--worker` is an internal option used by PDepend to start its own worker
processes. Do not pass it yourself.

Consequences worth knowing about:

- Parse errors and progress output from workers are collected and reported by
  the parent process, so the ordering of `ProcessListener` callbacks during
  parsing is no longer strictly file-by-file.
- Embedders that construct `PDepend\Engine` themselves stay single-process
  until they call `Engine::setMainScript()` with the script the worker
  processes should re-execute. `Engine::setThreads()` and
  `Engine::setWorkerCommandName()` are optional on top of that; without
  `setThreads()` the detected core count is used.

#### Cache location

The parser cache moved out of the home directory into the XDG cache directory:

|         | 2.x                              | 3.0                                                     |
| ------- | -------------------------------- | ------------------------------------------------------- |
| Default | `~/.pdepend`                     | `$XDG_CACHE_HOME/pdepend`, else `~/.cache/pdepend`      |
| Driver  | `file`, or `memory` on PHP builds with the serialize-reference bug | always `file` |

The old `~/.pdepend` directory is no longer used and can be deleted. The first
run after upgrading re-parses everything.

`PDepend\Util\FileUtil` changed accordingly:

| 2.x                                          | 3.0                                |
| -------------------------------------------- | ---------------------------------- |
| `FileUtil::getUserHomeDir()`                  | `FileUtil::getUserCacheDir()`      |
| `FileUtil::getUserHomeDirOrSysTempDir()`      | `FileUtil::getDefaultCacheDir()`   |

#### Removed options

- `--optimization` — removed. It has printed "Option --optimization is
  ambiguous." and done nothing since 2.0.
- The workaround banner ("Your PHP version requires some workaround") is gone
  together with `PDepend\Util\Workarounds`; none of the workarounds applied to
  PHP 8.1+.

### Library API

Class and interface names are unchanged. What changed is their signatures.

#### Native types on every interface

Every method in the public API now declares native parameter and return types.
Any class implementing a PDepend interface or extending a PDepend base class
must be updated to match, or PHP will refuse to load it.

The interfaces affected include `PDepend\Metrics\Analyzer`,
`PDepend\Report\ReportGenerator`, `PDepend\Source\ASTVisitor\ASTVisitor`,
`PDepend\Source\AST\ASTNode`, `PDepend\Source\AST\ASTArtifact`,
`PDepend\Source\Tokenizer\Tokenizer`, `PDepend\Source\Builder\Builder`,
`PDepend\ProcessListener` and `PDepend\Input\Filter`.

Two shape changes are worth calling out:

- `ASTArtifact` now `extends ASTNode` (in 2.x the `extends` was commented out
  and both interfaces declared overlapping methods).
- `Analyzer::analyze()` takes an `ASTArtifactList` instead of an untyped
  `$namespaces`, and returns `void`.

`Engine::getExceptions()` also widened: the parser now catches every
`Throwable` raised while parsing a file rather than only `ParserException`, so
the returned list is `Throwable[]`. Code that type-hinted `ParserException`
when iterating the result needs to relax that hint.

#### `getName()` is gone, use `getImage()`

The long-deprecated `getName()` was removed from artifacts. Every artifact —
classes, interfaces, traits, enums, methods, functions, namespaces, parameters,
properties, compilation units — exposes its name through `getImage()`, which is
what `ASTNode` has always used.

```php
// 2.x
$class->getName();

// 3.0
$class->getImage();
```

`setName()` is still available on `AbstractASTArtifact`.
`ASTNamedArgument::getName()` is unrelated and still exists.

#### The visitor pattern is inverted

In 2.x you called `accept()` on a node and passed it a visitor, and
`AbstractASTVisitor::__call()` forwarded unknown `visitXxx()` calls to
`visit($node, $data)`. Both are gone.

- `ASTNode::accept()` / `ASTArtifact::accept()` — removed.
- `ASTVisitor::__call()` — removed.
- `ASTVisitor::visit($node, $data)` — replaced by `visit(ASTNode $node): void`,
  which now means "descend into this node's children".
- `ASTVisitor::dispatch(ASTNode $node): void` — new. It routes a node to the
  matching `visitClass()`, `visitMethod()`, … method.

```php
// 2.x
$namespace->accept($visitor);

// 3.0
$visitor->dispatch($namespace);
```

`dispatch()` matches on the node's exact class, so nodes that are not one of the
ten artifact types (including subclasses such as `ASTAnonymousClass`) fall
through to `visit()` and have their children dispatched instead. If you rely on
a custom node subclass being routed to a dedicated method, override
`dispatch()`.

The `visitXxx()` methods all return `void` now; visitors that accumulated a
return value through the visit chain need to collect into the visitor instead.

#### `ProcessListener` signatures

`PDepend\ProcessListener` no longer receives the builder and tokenizer, and now
reports the total number of files up front:

```php
// 2.x
public function startParseProcess(Builder $builder);
public function endParseProcess(Builder $builder);
public function startFileParsing(Tokenizer $tokenizer);
public function endFileParsing(Tokenizer $tokenizer);

// 3.0
public function startParseProcess(int $fileCount): void;
public function endParseProcess(): void;
public function startFileParsing(): void;
public function endFileParsing(): void;
```

Listeners that used `$tokenizer->getSourceFile()` to report the file currently
being parsed no longer can — with parallel parsing the parent process does not
own a tokenizer. Use `$fileCount` from `startParseProcess()` to drive a progress
indicator instead.

#### The parser is sealed

`AbstractPHPParser` went from 130 `protected` methods to 28; the rest are now
`private`. The per-version parser classes for PHP 5.3 through 8.1 were removed
and their behaviour folded into `AbstractPHPParser` (feature level 8.0) and
`PHPParserVersion82`:

| 2.x                                       | 3.0                                     |
| ----------------------------------------- | --------------------------------------- |
| `PHPParserVersion53` … `PHPParserVersion81` | removed                               |
| `PHPParserVersion82` … `PHPParserVersion85` | `PHPParserVersion85` is the newest    |
| `PHPParserGeneric extends PHPParserVersion83` | `PHPParserGeneric extends PHPParserVersion85` |

`throwUnexpectedTokenException()` was removed; use
`throw $this->getUnexpectedTokenException($token)`.

If you subclassed the parser to hook into a specific `parseXxx()` method, check
whether the method still exists and is still `protected` before upgrading.

#### Metric value types

`getNodeMetrics()` and `getProjectMetrics()` return properly typed values now.
The one that changes shape is NPath complexity:

```php
// 2.x — arbitrary precision decimal string via bcmath
$metrics['npath'] === '17'

// 3.0 — native int
$metrics['npath'] === 17
```

Strict comparisons against strings will fail. Values beyond `PHP_INT_MAX` are no
longer represented exactly, which is why the package now requires a 64 bit
build.

#### Removed classes and methods

| Removed                                                   | Replacement                                   |
| --------------------------------------------------------- | --------------------------------------------- |
| `PDepend\Util\MathUtil`                                    | native integer arithmetic                     |
| `PDepend\Util\Workarounds`                                 | none, no longer needed on PHP 8.1+            |
| `PDepend\Metrics\AnalyzerIterator`                         | iterate the analyzer list directly            |
| `PDepend\Source\AST\ASTStringIndexExpression`              | none, `$string{0}` was removed in PHP 8.0     |
| `PDepend\Source\Builder\Builder::buildAstStringIndexExpression()` | as above                              |
| `PDepend\Source\Language\PHP\PHPParserVersion53` … `81`    | `AbstractPHPParser` / `PHPParserVersion82`    |
| `Lazy\PDepend\DependencyInjection\Configuration.{strong,weak}.php` | `PDepend\DependencyInjection\Configuration` |
| `Engine::TOKEN_STORAGE`, `Engine::PARSER_STORAGE`          | none, unused                                  |
| `ASTCompilationUnit::free()`, `AbstractASTCallable::free()` | none, unused                                 |
| `AbstractASTArtifact::getDocComment()` / `setDocComment()` | `getComment()` / `setComment()`               |
| `AbstractASTArtifact::getName()` and friends               | `getImage()`                                  |
| `AbstractPHPParser::throwUnexpectedTokenException()`       | `getUnexpectedTokenException()`               |

#### Package layout

The repository moved to the conventional layout. This only matters if you
reference files inside the package by path — the PSR-4 namespace root is
declared in `composer.json` and class names did not change.

| 2.x                                | 3.0            |
| ---------------------------------- | -------------- |
| `src/main/php/PDepend/`            | `src/`         |
| `src/main/resources/`              | `resources/`   |
| `src/bin/pdepend`                  | `bin/pdepend`  |
| `src/test/php/PDepend/`            | `tests/php/PDepend/` |
| `src/conf/`                        | `conf/`        |
| `src/site/`                        | `site/`        |

`vendor/bin/pdepend` is unaffected. The DI service definitions moved from
`resources/services.xml` to `resources/services.php`, and the XSD schema for the
XML configuration (`src/main/resources/schema/configuration.xsd`) was dropped.
