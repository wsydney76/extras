# Using local plugins

## Mount plugin directory

* Copy `docker-compose.mounts.yaml` to `.ddev`
* Adjust paths if needed.
* ddev restart

```yaml
services:
  web:
    volumes:
      - "$HOME/dev/plugins:/var/www/plugins"
```

## Update composer.json

* Add the following to `composer.json` in order to use local plugins in development:

```json
{
  "minimum-stability": "dev",
  "prefer-stable": true
}
```

* Add the following to the `repositories` section of `composer.json` for each local plugin (replacing `extras` with the name of the plugin):

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "/var/www/plugins/extras"
    }
  ]
}
```

## Install plugin

* Run `composer require wsydney76/extras` to install the plugin, replacing `wsydney76/extras` with the correct plugin name as defined in its `composer.json`.
* Run `craft plugin/install _extras`. replacing `_extras` with the correct plugin handle as defined in `composer.json` `extra:handle: .

