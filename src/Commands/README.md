# Commands

Native Artisan commands use the `moztopia-cloudflare` namespace and are registered only in console mode. They require no account credentials, public route, scheduler or database. `DoctorCommand` checks local configuration and isolated samples; it does not inspect a live browser or probe the network. Classes are internal implementation details; the documented CLI is the public contract.
