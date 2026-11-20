# Example runtime

The website image is built from the repository root so Composer can consume the package through `../../`. The assets stage builds local TypeScript and CSS. The runtime serves Laravel directly on PHP 8.5.13, with no separate web server, database, cache service or tunnel. All dependencies are installed in the image; none are required on the host.
