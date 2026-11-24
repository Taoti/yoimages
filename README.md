# YoImages (Taoti fork)

Image cropping, image SEO hooks, and stock photo search for WordPress, in one plugin.

## Why this fork exists

- The original "YoImages" by Sirulli was abandoned in 2015.
- It never worked on PHP 8.0, let alone 8.4.
- Taoti upgraded the plugin and its four child modules for PHP 8.4.x and WordPress 6.7+, and hardened them for security.
- The child modules used to be separate, also-abandoned repositories. They now live in this one repo under `src/`. One repo, one package.

## Install

Add Taoti's Packistry to the site's `composer.json` if it isn't there already:

```json
{
  "repositories": [
    { "name": "packistry", "type": "composer", "url": "https://packages.taoti.io" }
  ]
}
```

Then:

```sh
composer require taoti/yoimages
```

The package type is `wordpress-plugin`, so `composer/installers` puts it in `wp-content/plugins/yoimages/`. Make sure the project's `installer-paths` maps `type:wordpress-plugin` to your plugins directory.

The package also declares `replace: sirulli/yoimages`, so nothing else on the site will try to pull in the abandoned upstream.

### Moving a site off the hard-coded copy

1. Remove the committed `wp-content/plugins/yoimages/` folder.
2. Run `composer require taoti/yoimages`.
3. Check the plugin is still active. The folder name is unchanged, so no re-activation should be needed, and settings live in the database, so they carry over.
4. Gitignore the installed folder if your project ignores other Composer-installed plugins.

## Layout

```
yoimages.php                 Plugin entry point
src/yoimages-commons/inc/    Shared code, settings page, module loader
src/yoimages-crop/inc/       Image cropping tools
src/yoimages-seo/inc/        Image SEO hooks
src/yoimages-search/inc/     Free stock photo search
```

`yoimg_register_module()` (in `src/yoimages-commons/inc/utils.php`) loads each module from `src/<module-id>/inc/init.php`.

## Development

```sh
composer install
composer lint        # php -l plus PHP 8.1+ compatibility
composer lint:wpcs   # full WordPress standard (legacy code, expect findings)
```

## License

GPL-2.0, same as the original. Original copyright notices are kept in the source headers.
