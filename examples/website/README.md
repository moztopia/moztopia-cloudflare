# Moztopia Cloudflare example website

A self-contained Laravel application consuming the root package through a Composer path dependency. Its visitor, UI and request pages demonstrate real package behavior with isolated simulated scenarios or a live request.

From a complete repository checkout:

```sh
cd examples/website
docker compose up
```

Open **http://localhost:8080**. The image pins PHP 8.5.13 and builds its locked dependencies and browser assets inside Docker. No host PHP/Node, external database or Cloudflare account is needed for simulated scenarios. Stop with Ctrl+C; rebuild source changes with `docker compose up --build`.

**[Read the example guide in the wiki](https://github.com/moztopia/moztopia-cloudflare/wiki/Example-website)** for all scenarios, port overrides, live ingress, languages, themes, checks and distribution boundaries. The wiki is the source of truth for human-facing documentation.

The full repository is required for the `../../` path dependency. Examples are excluded from package source archives. Live requests require explicitly trusted ingress; unknown context on localhost is expected.
