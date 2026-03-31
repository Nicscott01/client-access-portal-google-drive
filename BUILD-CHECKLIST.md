# Client Access Portal - Google Drive Build Checklist

## Bootstrap And Compatibility

- [x] Register the addon as a separate plugin
- [x] Stop relying on a folder-specific `Requires Plugins` dependency header
- [x] Detect the active core plugin by stable PHP runtime constants/functions
- [x] Surface the detected core install in addon settings

## Configuration

- [x] Add Google Drive settings storage and sanitization
- [x] Add configuration validation and health summary helpers
- [x] Document which Google credential type the addon expects
- [x] Add an admin-side Google Drive connection test action
- [ ] Support constant-based credential path overrides for local/prod parity

## Provider Layer

- [x] Register a Google Drive provider skeleton against the core registry
- [x] Add a dedicated Drive service wrapper
- [x] Add client folder provisioning flow
- [x] Add admin tooling to relink existing clients to known folder IDs
- [x] Add admin tooling to provision replacement folder pairs for existing clients
- [ ] Add metadata sync flow
- [x] Add stream/download handling
- [x] Add upload-for-review flow

## Verification

- [x] Verify addon loads with the `-dev` core folder active
- [ ] Verify addon loads with a non-`-dev` core folder active
