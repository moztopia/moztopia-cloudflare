# Website service

`website.Dockerfile` builds the stateless example application and its test tools. Composer installs the package using its path repository. The final container runs as `www-data` and listens on internal port 8000. Compose publishes it on loopback port 8080 by the owner's explicit example-application exception. Use `EXAMPLE_PORT` to choose another local port.
