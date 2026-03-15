# Stage File Proxy

Mirror (or header to) uploaded files from a remote production site on your local development copy. Saves the trouble of downloading a giant uploads directory without sacrificing the images that accompany content.

## Installation

1. Add the following snippet **above** the `"type": "composer"` entry within the `"repositories":` section of your `composer.json` file:

```json
{
  "type": "vcs",
  "url": "git@github.com:cleverington/stage-file-proxy.git"
},
```

2. Install via composer using `--dev` so it only installs on non-production deployments:

```bash
composer require cleverington/stage-file-proxy:"*" --dev
```

### No Composer Installation

1. Clone this repository into your `plugins/` directory.
2. If using version control, delete the `.git/` directory to prevent issues within parent Git history.

## Setup

Stage File Proxy runs when WordPress is serving a 404 response for a request to the uploads directory. If your server intercepts these requests instead of passing them to WordPress, the plugin will not work.

There are four options for this plugin, though only two are currently available via the UI. WP-CLI can be used to tweak the setting though, such as adjusting the mode to `header`.

```shell
wp option update sfp_mode header
```

## Available options

* `sfp_mode`: The method used to retrieve the remote image. Default is `header`. One of:
  * `download` (downloads the remote file to your machine)
  * `header` (serves the remote file directly)
  * `local` (like `download` but serves an image from a directory in the current parent theme if the download fails)
  * `photon` (like `header` but uses arguments compatible with []() to size the image)

* `sfp_url`: The absolute URL to the uploads directory on the source site.

* `sfp_local_dir`: The name of the directory in the parent theme where images are stored for `local` mode.
