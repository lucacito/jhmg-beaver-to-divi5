# Release checklist

| Plugin | Channel | Version source |
|---|---|---|
| `jhmg-converter-for-beaver-builder-to-divi` (free) | WordPress.org SVN | plugin header + `BBDC_PLUGIN_VERSION` + readme `Stable tag` |
| `jhmg-converter-for-beaver-builder-to-divi-pro` (Pro) | divi5lab.com | plugin header + `BDCP_PLUGIN_VERSION` |

`tests/ReleaseMetadataTest.php` and `ProPluginTest` fail when the three free places or the two Pro places disagree.

1. Working tree clean; decide the version; update it in every place above; write the readme changelog and upgrade notice.
2. `npm test` — PHPUnit and Playwright green. Playwright drives the Docker site on `localhost:8010`
   (`scripts/docker/setup_wp.sh` builds it). Read `Tested up to` off the container
   (`docker compose exec wordpress wp core version --allow-root`); do not carry it forward.
3. `scripts/plugin-check.sh free` — WordPress's Plugin Check must report nothing on the free plugin (the review team runs the same tool). Pro findings about the updater, missing readme and `load_plugin_textdomain` are expected off-directory.
4. `npm run i18n` when user-facing strings changed.
5. Free: `rsync -a --delete --exclude='.DS_Store' --exclude='.svn' plugin/jhmg-converter-for-beaver-builder-to-divi/ wporg-svn/trunk/`,
   review `svn status`, `svn add --force trunk`, `svn cp trunk tags/<version>`, `svn ci -m "Release <version>"`.
   `wporg-svn/` is gitignored; recreate with `svn co https://plugins.svn.wordpress.org/jhmg-converter-for-beaver-builder-to-divi/ wporg-svn`.
6. Pro: zip `plugin/jhmg-converter-for-beaver-builder-to-divi-pro/` (no `.DS_Store`), upload to divi5lab.com,
   confirm `/api/plugin/update-check?product=beaver-to-divi5-pro` serves the new `version` and a `package` URL,
   and that a clean site with a valid licence receives the update.
7. `AdminPage::PRO_PRICE` must match the divi5lab.com listing; nothing enforces it.
