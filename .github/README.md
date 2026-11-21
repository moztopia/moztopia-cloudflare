# GitHub checks

`workflows/ci.yaml` selects checks from changed paths on pull requests to `develop`/`main` and pushes to `develop`. Package changes run the PHP/Laravel matrix and the consuming example. Example implementation changes run its locked dependency/asset/application checks. Documentation-only changes skip those builds; manual dispatch selects both. The final `CI result` fails if an affected job fails or is cancelled.

The matrix resolves Laravel 12/Testbench 10 and Laravel 13/Testbench 11 separately for PHP 8.4.27, 8.5.12 and 8.5.13, then verifies the exact runtime inside the matching official PHP image. Dependency installation uses the official Composer image with the selected PHP platform. The example uses its existing Docker build and PHP 8.5.13, tests, formatting and a loopback HTTP smoke check.

The workflow uses read-only repository permissions, disables persisted checkout credentials and requires no Cloudflare secrets or live ingress. Dependency/image downloads are ordinary CI setup; synthetic tests do not establish live Cloudflare behavior. No releases, image publication, deployments or repository-protection changes are performed. The wiki is the source of truth for the supported contract and actual verification evidence.
