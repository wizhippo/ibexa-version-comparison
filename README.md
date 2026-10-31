# Ibexa Version Comparison

Independent, read-only comparison of saved content versions in the **Ibexa 5
admin UI**. Package: `wizhippo/ibexa-version-comparison`.

Repository: `https://github.com/wizhippo/ibexa-version-comparison`.

## Features

Inspired by the [Ibexa version workflow guide](https://doc.ibexa.co/projects/userguide/en/latest/content_management/workflow_management/work_with_versions/#compare-versions):

- **Compare versions** action in each accessible Versions-tab row.
- Select two saved versions and translations of the same content item. Each
  selector combines version number, status, language, and saved date.
- **Split** view by default and a **Unified** view using the compared document's
  structure, with removed and added text included inline.
- Yellow field headers for updates, blue for additions, red for deletions;
  status labels, insertion/deletion semantics, and a changed-fields filter.
- Word-level Unicode-aware differences for text and ordinary field values.
- Native Ibexa rendering of RichText, headings, tables, links, embeds and media.
  Text highlights are inserted into the rendered document without flattening its
  markup. Formatting-only changes remain marked as an updated field.
- Image/media previews and metadata comparisons. Unified media changes display
  the previous and new media separately.
- Strict content/version permissions and exact version/language selection.
  No writes, restore, publish, generation, AI service, or external diff service.

This is an original application bundle, independent of the proprietary
`ibexa/version-comparison` package and of the content assistant bundle.

## Requirements and installation

PHP 8.4+, Ibexa 5, Symfony 7.4, and the normal Ibexa Encore asset pipeline.
Runtime dependencies, including `sebastian/diff`, are declared in `composer.json`;
production comparison does not rely on PHPUnit's development dependencies.

Add a Composer VCS repository for
`https://github.com/wizhippo/ibexa-version-comparison.git` and require
`wizhippo/ibexa-version-comparison:dev-main` (or a published release tag).

Register the bundle:

```php
// config/bundles.php
Wizhippo\IbexaVersionComparison\WizhippoIbexaVersionComparisonBundle::class => ['all' => true],
```

Import its routes:

```yaml
# config/routes/version_comparison.yaml
wizhippo_ibexa_version_comparison:
    resource: '@WizhippoIbexaVersionComparisonBundle/config/routes.yaml'
```

Clear the cache and build assets:

```sh
php bin/console cache:clear
php bin/console ibexa:encore:compile --frontend-configs-name ibexa
```

The bundle registers its own services and policies. Ibexa automatically discovers
its Encore stylesheet entry. No application template override or JavaScript is
required. There are no database migrations or environment secrets to configure.

## Using comparison

1. Open a content item's **Versions** tab, outside Focus mode.
2. Click the comparison icon on a version row. The clicked version is the
   compared/right version. The base is the published version when different,
   otherwise another saved version (or the same one if no other exists).
3. Choose version/language combinations, Split or Unified, and click **Compare**.
4. Optionally hide unchanged fields. Return to Versions with the back link.

The page compares persisted versions; unsaved editor changes are excluded.
Two identical selections are allowed and show unchanged values. A missing
translation is rejected rather than silently falling back to another language.
Non-translatable fields use each version's initial language.

## Permissions and safety

Grant the **`wizhippo_ibexa_version_comparison/view`** policy to appropriate editorial roles.
Wildcard administrators already qualify. The user must be authenticated; remembered sign-ins are supported.
When upgrading from the former `version_comparison/view` policy, grant
`wizhippo_ibexa_version_comparison/view` to the same editorial roles. The old
policy is not reused, so it cannot grant access to this independent bundle.
The native repository must permit listing versions and reading each selected
version (`content/read` and `content/versionread`), including normal limitations.
No permission elevation, anonymous public link, or sudo is used. A row action is
hidden when its permissions do not qualify; direct requests are checked again.

All comparison routes are GET-only and never modify content. Responses are
private and `no-store`. Native rendered field HTML is displayed in sandboxed
iframes with scripts, forms, popups and top-level navigation disabled. Script
nodes are removed and text differences are escaped. The parent page never
inserts raw content field HTML. Source text and field values are not logged.

## Supported fields and limits

Supported types include text line/block, RichText, numbers, booleans, dates and
times, URL/email, author, keywords, selections, countries, relations, images,
image assets, files, media and ISBN. Simple structured values are compared using
their public field-type hash, with stable object-key ordering and list order
preserved. Image bookkeeping IDs are ignored, matching native equality behavior.

User account, Form, Landing Page and unknown custom types are explicitly labelled
unsupported. Their values are neither serialized nor rendered by this bundle.
Fields present on only one side are marked added/deleted. Field definitions use
the current content type; removed historical definitions may have identifier
labels and value-only comparison rather than a native preview.

RichText field values include stored markup, references, and custom-tag
configuration when detecting changes. The UI does not expose raw RichText XML.
Split previews retain each version's layout. Unified RichText uses the compared
layout, with deleted text inserted at matching text positions; it is not a
reconstruction of both historical layouts. Inspect split previews for structural
or formatting changes. Native embeds/referenced assets may render current target
content: the bundle compares stored references and does not recursively recreate
historical versions of referenced content or external media.

Field values and native rendered fragments are limited to 256 KiB each. A preview
failure is isolated to its field. Very large word edits use a coarse replacement
highlight after common prefixes/suffixes are removed. The diff work budget is
bounded at one million token pairs, and comparisons over 30,000 tokens use coarse
highlighting. No partial content is saved or applied.

See the [roadmap and open decisions](docs/roadmap.md) for proposed field type
support, historical previews, and future comparison improvements.

## Tests

For a separate checkout, install development dependencies and run:

```sh
vendor/bin/phpunit
```

Tests cover text reconstruction, Unicode and escaping, bounded diffs, rendered
HTML structure and media preservation, permissions, exact version/language
selection, missing fields, non-translatable fields, sensitive-field exclusion,
image metadata, menu integration, and absence of repository writes.

All tests belong to this package. Native Twig/RichText integration tests live in
`tests/Integration`; the default unit suite excludes them and needs no application
kernel. Run the separate integration suite against a configured Ibexa application
with this bundle registered:

```sh
KERNEL_CLASS='App\Kernel' \
WIZHIPPO_TEST_BOOTSTRAP="$PWD/tests/bootstrap.php" \
vendor/bin/phpunit -c vendor/wizhippo/ibexa-version-comparison/phpunit.integration.xml.dist
```

Run that command from the consuming application's root, replacing the example
kernel/bootstrap with its own values. The tests use Symfony's `KERNEL_CLASS`
configuration and never import an application kernel. A host PHPUnit suite may
also include the entire package test directory and supply `KERNEL_CLASS` itself.
No live provider service is used.

## Bundle conventions

The bundle class and dependency injection extension share the
`WizhippoIbexaVersionComparison` name. PHP services use the
`Wizhippo\IbexaVersionComparison` namespace; configuration, routes, menu items,
and policies use the `wizhippo_ibexa_version_comparison` prefix. The comparison
route is `wizhippo_ibexa_version_comparison_compare`; its URL remains
`/version-comparison/{contentId}`. Templates use the
`@WizhippoIbexaVersionComparison` namespace, and the Encore stylesheet entry is
`wizhippo-ibexa-version-comparison-css`. Rebuild assets when upgrading from the
former stylesheet entry name.

`config/`, `templates/`, `assets/`, `src/`, and `tests/` live at the package root.
`Resources/encore/ibexa.config.js` stays in Ibexa's asset discovery location.

## Authors

- [Douglas Hammond (wizhippo)](https://github.com/wizhippo) — author and maintainer.
- LLM — AI-assisted implementation, refactoring, and tests.

## License

**GPL-2.0-only**, matching Ibexa core's GPL option. See [LICENSE](LICENSE). This
bundle includes no Ibexa commercial-license grant.
