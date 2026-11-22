# Website service

`website.Dockerfile` builds the stateless example application and its test tools. Composer installs the package using its path repository. The final container runs as `www-data` and listens on internal port 8000. Compose publishes it on loopback port 8080 by the owner's explicit example-application exception. Use `EXAMPLE_PORT` to choose another local port.

Both package-copy stages retain `lang/` alongside `src/` and `config/`, so the native doctor command resolves its catalogs in the same path package used by the application. The consuming tests verify that bundled command text and JSON work in the final image.
