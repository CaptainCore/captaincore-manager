# Changelog

## **v1.0.0** - Unreleased

The interface release. CaptainCore Manager reaches 1.0 with a rebuilt `/account` experience: a fast, hand-maintained single-page interface (core-v3) that replaces the original Vue dashboard as the default, while the legacy app remains one switch away. This release also puts the project on a proper release cycle with GitHub Releases, a signed update manifest, and a self-updater.

### Added

- New core-v3 interface: a rebuilt fleet dashboard served at `/account`, wearing the Minn Admin design system with light and dark themes, a command palette, and a working terminal dock.
- Self-updater: the plugin now checks a release manifest on GitHub and offers updates through the WordPress Plugins screen, verifying each download against the sha256 published in the manifest before install.
- Release tooling: `bin/build-zip.sh` builds the distributable zip with dev files excluded and prints the sha256 for the manifest stamp.

### Improved

### Fixed
