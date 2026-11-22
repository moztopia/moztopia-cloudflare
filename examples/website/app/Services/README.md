# Example services

`ExampleHarness` reads the actual package service for live requests or constructs an explicit isolated container/request for a selected synthetic scenario. It returns visitor/context snapshots plus a fixed allowlist of raw diagnostic headers, including coordinate and metro headers. It never changes the application's active request or trust configuration, and it performs no HTTP lookup. The wiki documents the example's public behavior and ingress setup.
