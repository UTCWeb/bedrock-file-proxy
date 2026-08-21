# WordPress Bedrock File Proxy

Mirror (or header to) uploaded files from a remote production Bedrock-flavored WordPress site or multisite on your local development copy. Saves the trouble of downloading a giant uploads directory without sacrificing the images that accompany content.

## Installation

1. Add the following snippet **above** the `"type": "composer"` entry within the `"repositories":` section of your `composer.json` file:

```json
{
  "type": "vcs",
  "url": "git@github.com:utcweb/bedrock-file-proxy.git"
},
```

2. Install via composer using `--dev` so it only installs on non-production deployments:

```bash
composer require utcweb/bedrock-file-proxy:"*" --dev
```

### No Composer Installation

1. Clone this repository into your `plugins/` directory.
2. If using version control, delete the `.git/` directory to prevent issues within parent Git history.

## Setup

Bedrock File Proxy runs when WordPress is serving a 404 response for a request to the web/app/uploads or wp-uploads directory.

There are four options for this plugin, though only two are currently available via the UI. WP-CLI can be used to tweak the setting though, such as adjusting the mode to `header`.

```shell
wp option update bfp_mode header
```

### Multisite configuration

For a Bedrock multisite installation, set `bfp_url` to the remote network's uploads root. Do not include a site's `sites/{blog_id}` directory in this value; that path is part of the requested file path and is appended automatically.

For example, to proxy uploads from `https://blog.example.com` in a local multisite environment:

```shell
wp plugin activate bedrock-file-proxy --url=https://local.example/
wp option update bfp_url https://blog.example.com/app/uploads/ --url=https://local.example/
wp option update bfp_mode download --url=https://local.example/
```

The plugin and options must be configured on the main site. Multisite upload URLs are network-rooted, so a request such as:

```text
https://local.example/app/uploads/sites/9/2026/08/Expedition-unknown.png
```

is handled by the main site even when the image belongs to site 9 (`/news/`). With the configuration above, the proxy downloads the missing file from:

```text
https://blog.example.com/app/uploads/sites/9/2026/08/Expedition-unknown.png
```


## Available options

* `bfp_mode`: The method used to retrieve the remote image. Default is `header`. One of:
	* `download` (downloads the remote file to your machine)
	* `header` (serves the remote file directly)
	* `local` (like `download` but serves an image from a directory in the current parent theme if the download fails)
	* `photon` (like `header` but uses arguments compatible with image resizing)

* `bfp_url`: The absolute URL to the uploads directory on the source site.

* `bfp_local_dir`: The name of the directory in the parent theme where images are stored for `local` mode.

## Legacy compatibility

Older `sfp_*` option values and filter names are still read as fallbacks during migration.
