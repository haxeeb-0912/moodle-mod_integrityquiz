# Moodle Marketplace review fixes

This release addresses the reviewer findings reported on 17 September 2026.

1. **Incorrect repository name** — repository should be renamed externally to `moodle-mod_integrityquiz`; plugin component remains `mod_integrityquiz`.
2. **Missing plugin license file** — added root `LICENSE` containing GNU GPL v3.
3. **Invalid or stale AMD build artifact** — `amd/src/secure.js` is now ESM using `core/ajax`; `amd/build/secure.min.js` is a separate AMD deployment artifact.
4. **Activity module missing required events** — added `course_module_viewed` and `course_module_instance_list_viewed` event classes and triggers.
5. **Privacy API class does not include all personal data** — expanded metadata, export, deletion, reviewer data, evidence files, gradebook link, and user-list provider support.
6. **Missing file boilerplate headers** — added Moodle GPL/file headers to PHP, JavaScript, and CSS source files.
7. **Update AJAX implementation to External Services** — removed the custom `ajax/` endpoints; added `classes/external/`, `db/services.php`, and `core/ajax` calls.
8. **Avoid manual `requires->css()` for plugin styles.css** — removed all manual `styles.css` requests.
9. **Enforce declared management and evidence capabilities** — management pages/actions and pluginfile evidence access now use the declared plugin capabilities directly.
10. **Remove invalid empty defaults from XMLDB fields** — removed empty defaults from `name`, `eventhash`, `oldstate`, and `newstate`; added upgrade handling.
