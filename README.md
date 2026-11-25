# YoImages (Taoti fork)

Image cropping, image SEO hooks, and stock photo search for WordPress, in one plugin.

## Why this fork exists

- The original "YoImages" by Sirulli was abandoned in 2015.
- It never worked on PHP 8.0, let alone 8.4.
- Taoti upgraded the plugin and its four child modules for PHP 8.4.x and WordPress 6.7+, and hardened them for security.
- The child modules used to be separate, also-abandoned repositories. They now live in this one repo under `src/`. One repo, one package.

## Requirements

- PHP 8.3 or newer (checked against 8.5)
- WordPress 6.7 or newer

Need PHP 8.1 or 8.2? Stay on 0.2.0, the last release that supports them.

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

## Future features

- **Upgrade Cropper.** The crop screen still runs on Cropper v0.5.2, a 2014 jQuery plugin. The current release is [Cropper.js 2.x](https://github.com/fengyuanchen/cropperjs). Version 1.7 plus its jQuery wrapper is the smaller step. Version 2 is a rewrite, so the cropping JavaScript and markup in `src/yoimages-crop/inc/` will need reworking. Not a drop-in swap.
- Refresh the stock photo search providers. Splashbase is long gone, and the Unsplash client is built on an API that has since changed.
- **Create a unit testing suite.** There are no automated tests today. Start with PHPUnit for the crop, replace, restore, and stock photo import handlers (nonce and capability checks, input validation, failed-crop cleanup), then add Given, When, Then scenarios for the admin flows.
- A simple built-in image editor (effects, editing, color optimization).
- Image gallery templates.

Found a bug or want something added? [Open an issue](https://github.com/Taoti/yoimages/issues).

## How to contribute

Pull requests are welcome on [Taoti/yoimages](https://github.com/Taoti/yoimages). Run `composer lint` before you push, and keep changes small and focused. For the project's history, the original contribution notes live at [sirulli.org/yoimages](http://sirulli.org/yoimages/#contribute).

## Credits

YoImages is the work of **Sirulli** ([sirulli.org](http://sirulli.org/), [sirulli/yoimages](https://github.com/sirulli/yoimages)). Taoti's fork exists because of what they built. Everything here started with them.

Cropping runs on **Cropper**, the jQuery image cropper by **Fengyuan Chen** ([github.com/fengyuanchen/cropper](https://github.com/fengyuanchen/cropper), MIT licensed). We bundle v0.5.2 in `src/yoimages-crop/inc/js/cropper/`. Thank you, Fengyuan.

Thanks also to the upstream contributors:

- [wp-fred](https://profiles.wordpress.org/wp-fred-1/) for the Dutch translations, and for suggesting and designing the "user-friendly names" feature for crop formats.
- [Maxime Lafontaine](http://www.maximelafontaine.net/) for the French translations.
- [Thomas Meyer](https://github.com/tmconnect/) for code contributions and fixes to the German translations.
- [Robert Vermeulen](https://github.com/robert388) for better metadata support, WP-CLI commands, and Regenerate Thumbnails compatibility.
- [Elliot Coad](https://github.com/ecoad) for the action fired after cropping.
- [odie2](https://github.com/odie2/) for code contributions and the Polish translations.
- [Ben Bowler](https://github.com/benbowler) for his code contributions.

## License

GPL-2.0, same as the original. Original copyright notices are kept in the source headers.
