# Backlog

## Heuristics / Tips / Environments / Gotchas

The interview process used in `amp config`/`amp tests` gives some tips/suggestions and evaluates some heuristics/gotchas.  Over time, as we
try `amp config` in more environments, we find more tips / gotchas / rules-of-thumb.  It's useful to list these (a) as tips for people
debugging their own problems and (b) as a backlog for future updates to `amp config`.

When a new tip/gotcha is identified, file it as an issue with label "environment-detection":

https://github.com/amp-cli/amp/labels/environment-detection

## API / Framework

* Callback support (eg `amp create` calls a script bundled with my-application)
* Load per-application config values (my-application/.amp.yml); eg:
    * Specify any callback(s)
    * Specify the Apache vhost template
    * Specify the nginx vhost template
    * Specify the PHP router script (for php 5.4's built-in web-server)
* For `amp export` and `amp create`, add option `--format=shell,json,yml`

## Services/Drivers/Integrations

* Add HttpdInterface for nginx
* Add HttpdInterface for PHP's built-in web-server
