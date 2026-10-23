---
category: fixed
type: minor
---

Preserve absolute resource-bundle target identity across install, update and removal,
and reject target changes before old ownership can overwrite or delete new-target
consumer files. Existing manifests without an explicit target format now stop for
deliberate destination verification instead of guessing a destructive path.
