// The one place the documentation menu is defined. The sidebar on every page is
// rendered from this, and docsCheck reads it to verify the links still resolve.
// Paths are relative to the site root, and the renderer adjusts them per page.
window.DOCS_NAV = [
    {
        title: "Introduction",
        items: [
            { url: "index.html",                          name: "Overview" },
            { url: "introduction/getting-started.html",    name: "Getting Started" },
            { url: "introduction/bootstrapping.html",      name: "Bootstrapping" },
            { url: "introduction/configuration.html",      name: "Configuration" },
            { url: "introduction/config-files.html",       name: "Config Files" },
            { url: "introduction/cli.html",                name: "CLI Commands" },
        ],
    },
    {
        title: "Core Concepts",
        items: [
            { url: "core/discovery.html",                  name: "Discovery" },
            { url: "core/build.html",                      name: "Build System" },
            { url: "core/routing.html",                    name: "Routing" },
            { url: "core/signals.html",                    name: "Signals &amp; Listeners" },
        ],
    },
    {
        title: "Database",
        items: [
            { url: "database/database.html",               name: "Database" },
            { url: "database/models.html",                 name: "Models" },
            { url: "database/validation.html",             name: "Validation" },
            { url: "database/framework-models.html",       name: "Framework Models" },
            { url: "database/schema.html",                 name: "Generated Schema" },
            { url: "database/schema-json.html",            name: "Schema JSON" },
            { url: "database/der.html",                    name: "DER" },
            { url: "database/query.html",                  name: "Query builder" },
            { url: "database/migrations.html",             name: "Migrations" },
        ],
    },
    {
        title: "Subsystems",
        items: [
            { url: "subsystems/auth.html",                 name: "Authentication" },
            { url: "subsystems/credentials.html",          name: "Credentials" },
            { url: "subsystems/settings.html",             name: "Settings" },
            { url: "subsystems/emails.html",               name: "Emails" },
            { url: "subsystems/notifications.html",        name: "Notifications" },
        ],
    },
    {
        title: "Features",
        items: [
            { url: "features/enums.html",                  name: "Enums" },
            { url: "features/intl.html",                   name: "Internationalization" },
            { url: "features/import-export.html",          name: "Import &amp; Export" },
            { url: "features/logging.html",                name: "Logging" },
            { url: "features/icons.html",                  name: "Icons" },
            { url: "features/providers.html",              name: "Providers" },
            { url: "features/curl.html",                   name: "Curl" },
            { url: "features/schema-json.html",            name: "JSON Schemas" },
            { url: "features/phpstan.html",                name: "PHPStan Rules" },
        ],
    },
    {
        title: "Utilities",
        items: [
            { url: "utilities/date.html",                  name: "Date &amp; Time" },
            { url: "utilities/file.html",                  name: "Files &amp; Storage" },
            { url: "utilities/io.html",                    name: "Requests &amp; IO" },
            { url: "utilities/utils.html",                 name: "Utils" },
        ],
    },
];
